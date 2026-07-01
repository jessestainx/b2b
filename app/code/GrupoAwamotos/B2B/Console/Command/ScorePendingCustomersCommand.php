<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Console\Command;

use GrupoAwamotos\B2B\Api\ApprovalScoreServiceInterface;
use GrupoAwamotos\B2B\Api\Data\ApprovalScoreResultInterface;
use GrupoAwamotos\B2B\Model\CnpjDuplicateChecker;
use GrupoAwamotos\B2B\Model\RealB2BRegistrationChecker;
use GrupoAwamotos\B2B\Model\Customer\Attribute\Source\ApprovalScore;
use GrupoAwamotos\B2B\Model\Customer\Attribute\Source\ApprovalStatus;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class ScorePendingCustomersCommand extends Command
{
    private const OPTION_APPLY = 'apply';
    private const OPTION_LIMIT = 'limit';
    private const OPTION_OUTPUT = 'output';
    private const OPTION_REAL_REGISTER = 'real-register-only';
    private const OPTION_SEGMENT_REPORT = 'segment-report';

    private const EAV_ATTRIBUTES = [
        'b2b_approval_score',
        'b2b_approval_score_reason',
        'b2b_suggested_group_id',
    ];

    public function __construct(
        private readonly CustomerCollectionFactory $customerCollectionFactory,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly ApprovalScoreServiceInterface $approvalScoreService,
        private readonly CnpjDuplicateChecker $duplicateChecker,
        private readonly RealB2BRegistrationChecker $realRegistrationChecker,
        private readonly GroupRepositoryInterface $groupRepository,
        private readonly EavConfig $eavConfig,
        private readonly ModuleDirReader $moduleDirReader,
        private readonly State $appState,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('b2b:score-pending-customers')
            ->setDescription('Triagem retroativa de clientes B2B pendentes via ApprovalScoreService')
            ->addOption(
                self::OPTION_APPLY,
                null,
                InputOption::VALUE_NONE,
                'Aplica score nos atributos EAV (sem aprovar, sem e-mail, sem ERP). Omitir = dry-run.'
            )
            ->addOption(self::OPTION_LIMIT, null, InputOption::VALUE_REQUIRED, 'Limitar quantidade analisada', '0')
            ->addOption(
                self::OPTION_OUTPUT,
                null,
                InputOption::VALUE_REQUIRED,
                'Caminho do relatório JSON',
                '/tmp/b2b-score-pending-dry-run.json'
            )
            ->addOption(
                self::OPTION_REAL_REGISTER,
                null,
                InputOption::VALUE_NONE,
                'Triagem/score apenas em cadastros reais (/b2b/register/); exclui legado ERP da fila'
            )
            ->addOption(
                self::OPTION_SEGMENT_REPORT,
                null,
                InputOption::VALUE_NONE,
                'Gera relatório de segmentação real vs ERP legado (sem gravar score)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initializeAreaCode();

        $apply = (bool) $input->getOption(self::OPTION_APPLY);
        $limit = max(0, (int) $input->getOption(self::OPTION_LIMIT));
        $outputPath = (string) $input->getOption(self::OPTION_OUTPUT);
        $realOnly = (bool) $input->getOption(self::OPTION_REAL_REGISTER);
        $segmentReport = (bool) $input->getOption(self::OPTION_SEGMENT_REPORT);

        if ($segmentReport) {
            return $this->executeSegmentReport($outputPath, $output);
        }

        if (!$apply) {
            $output->writeln('<info>Modo DRY-RUN - nenhuma alteracao sera persistida.</info>');
        } else {
            $output->writeln('<comment>Modo APPLY - apenas atributos de score serao gravados (sem aprovar).</comment>');
        }
        if ($realOnly) {
            $output->writeln('<comment>Filtro: cadastros reais pendentes (exclui legado ERP).</comment>');
        }

        $validations = $this->runValidations();
        foreach ($validations['messages'] as $message) {
            $output->writeln($message);
        }

        if (!$validations['eav_ok']) {
            $output->writeln('<error>Abortado: atributos EAV de score ausentes.</error>');
            return Command::FAILURE;
        }

        $customers = $this->loadPendingCustomers($limit, $realOnly);
        $groupLabels = [];
        $rows = [];
        $counts = [
            ApprovalScoreResultInterface::SCORE_GREEN => 0,
            ApprovalScoreResultInterface::SCORE_YELLOW => 0,
            ApprovalScoreResultInterface::SCORE_RED => 0,
        ];
        $autoApproveList = [];
        $risks = [];

        foreach ($customers as $customerRow) {
            $customerId = (int) $customerRow['entity_id'];
            $beforeStatus = (string) ($customerRow['b2b_approval_status'] ?? '');

            if ($beforeStatus === ApprovalStatus::STATUS_APPROVED) {
                $risks[] = sprintf('Cliente #%d com status approved entrou na coleção pendente.', $customerId);
                continue;
            }

            $result = $this->approvalScoreService->evaluate($customerId);
            $score = $result->getScore();
            $counts[$score] = ($counts[$score] ?? 0) + 1;

            $suggestedGroupId = $result->getSuggestedGroupId();
            $suggestedGroupLabel = $this->resolveGroupLabel($suggestedGroupId, $groupLabels);
            $blockers = $this->detectBlockers($customerId, $result);

            $row = [
                'customer_id' => $customerId,
                'segment' => $this->realRegistrationChecker->isRealB2BRegistration($customerId)
                    ? RealB2BRegistrationChecker::SEGMENT_REAL_REGISTER
                    : RealB2BRegistrationChecker::SEGMENT_ERP_LEGACY,
                'name' => trim(($customerRow['firstname'] ?? '') . ' ' . ($customerRow['lastname'] ?? '')),
                'razao_social' => (string) ($customerRow['b2b_razao_social'] ?? ''),
                'cnpj' => (string) ($customerRow['b2b_cnpj'] ?? ''),
                'cnae_code' => (string) ($customerRow['b2b_cnae_code'] ?? ''),
                'cnae_profile' => (string) ($customerRow['b2b_cnae_profile'] ?? ''),
                'score' => ApprovalScore::getLabel($score),
                'score_code' => $score,
                'reason' => $result->getReason(),
                'suggested_group_id' => $suggestedGroupId,
                'suggested_group' => $suggestedGroupLabel,
                'would_auto_approve' => $result->shouldAutoApprove(),
                'blockers' => $blockers,
                'current_status' => $beforeStatus,
            ];

            $rows[] = $row;

            if ($result->shouldAutoApprove()) {
                $autoApproveList[] = $row;
            }

            if ($apply) {
                $this->approvalScoreService->persistScore($customerId, $result);
            }
        }

        if ($validations['duplicate_false_positive_count'] > 0) {
            $risks[] = sprintf(
                '%d cliente(s) pendente(s) marcado(s) como duplicidade — revisar manualmente.',
                $validations['duplicate_false_positive_count']
            );
        }

        if (!$validations['observer_unique']) {
            $risks[] = 'Observer ApprovalScoringObserver registrado mais de uma vez em events.xml.';
        }

        if ($validations['core_vendor_modified']) {
            $risks[] = 'Alterações detectadas em app/code core ou vendor (ver git status).';
        }

        $report = [
            'mode' => $apply ? 'apply' : 'dry-run',
            'generated_at' => date('c'),
            'validations' => $validations,
            'summary' => [
                'total_analyzed' => count($rows),
                'green' => $counts[ApprovalScoreResultInterface::SCORE_GREEN] ?? 0,
                'yellow' => $counts[ApprovalScoreResultInterface::SCORE_YELLOW] ?? 0,
                'red' => $counts[ApprovalScoreResultInterface::SCORE_RED] ?? 0,
                'would_auto_approve' => count($autoApproveList),
            ],
            'auto_approve_candidates' => $autoApproveList,
            'customers' => $rows,
            'risks' => $risks,
            'apply_command' => 'sudo -u www-data php bin/magento b2b:score-pending-customers --apply',
        ];

        file_put_contents($outputPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $output->writeln('');
        $output->writeln('<info>=== Relatório de impacto ===</info>');
        $output->writeln(sprintf('Total analisados: %d', $report['summary']['total_analyzed']));
        $output->writeln(sprintf('Verde:   %d', $report['summary']['green']));
        $output->writeln(sprintf('Amarelo: %d', $report['summary']['yellow']));
        $output->writeln(sprintf('Vermelho:%d', $report['summary']['red']));
        $output->writeln(sprintf('Auto-aprovação: %d', $report['summary']['would_auto_approve']));
        $output->writeln(sprintf('Relatório JSON: %s', $outputPath));

        if ($autoApproveList !== []) {
            $output->writeln('');
            $output->writeln('<info>Candidatos a auto-aprovação:</info>');
            foreach ($autoApproveList as $item) {
                $output->writeln(sprintf(
                    '  #%d | %s | CNPJ %s | CNAE %s | grupo %s',
                    $item['customer_id'],
                    $item['razao_social'] !== '' ? $item['razao_social'] : $item['name'],
                    $item['cnpj'],
                    $item['cnae_code'] !== '' ? $item['cnae_code'] : '—',
                    $item['suggested_group']
                ));
            }
        }

        if ($risks !== []) {
            $output->writeln('');
            $output->writeln('<comment>Riscos:</comment>');
            foreach ($risks as $risk) {
                $output->writeln('  - ' . $risk);
            }
        }

        $output->writeln('');
        $output->writeln('<comment>Para aplicar score nos atributos EAV (sem aprovar):</comment>');
        $output->writeln('  sudo -u www-data php bin/magento b2b:score-pending-customers --apply');

        return Command::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function runValidations(): array
    {
        $messages = [];
        $eavOk = true;

        foreach (self::EAV_ATTRIBUTES as $code) {
            $attribute = $this->eavConfig->getAttribute('customer', $code);
            if (!$attribute || !$attribute->getId()) {
                $eavOk = false;
                $messages[] = '<error>Atributo EAV ausente: ' . $code . '</error>';
            } else {
                $messages[] = '<info>Atributo EAV OK: ' . $code . '</info>';
            }
        }

        $observerCount = $this->countObserverRegistrations();
        $observerUnique = $observerCount === 1;
        $messages[] = $observerUnique
            ? '<info>Observer ApprovalScoringObserver: 1 registro (OK)</info>'
            : '<error>Observer ApprovalScoringObserver: ' . $observerCount . ' registros (esperado 1)</error>';

        $approvedInPending = 0;
        foreach ($this->loadPendingCustomers(0) as $row) {
            if (($row['b2b_approval_status'] ?? '') === ApprovalStatus::STATUS_APPROVED) {
                $approvedInPending++;
            }
        }
        $messages[] = $approvedInPending === 0
            ? '<info>Nenhum cliente approved na fila pendente (OK)</info>'
            : '<error>' . $approvedInPending . ' cliente(s) approved encontrado(s) na query pendente</error>';

        $duplicateFalsePositives = $this->countDuplicateFalsePositives();
        $messages[] = sprintf(
            '<info>CnpjDuplicateChecker: %d alerta(s) de duplicidade na fila pendente</info>',
            $duplicateFalsePositives
        );

        $coreVendorModified = $this->hasCoreVendorChanges();
        $messages[] = $coreVendorModified
            ? '<comment>Git: alterações em vendor/ ou core Magento detectadas</comment>'
            : '<info>Git: sem alterações em vendor/ ou core Magento</info>';

        return [
            'eav_ok' => $eavOk,
            'observer_unique' => $observerUnique,
            'observer_registrations' => $observerCount,
            'approved_in_pending_query' => $approvedInPending,
            'duplicate_false_positive_count' => $duplicateFalsePositives,
            'core_vendor_modified' => $coreVendorModified,
            'messages' => $messages,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadPendingCustomers(int $limit, bool $realOnly = false): array
    {
        if ($realOnly) {
            $segments = $this->realRegistrationChecker->segmentPendingCustomerIds();
            $ids = $segments[RealB2BRegistrationChecker::SEGMENT_REAL_REGISTER];
            if ($limit > 0) {
                $ids = array_slice($ids, 0, $limit);
            }

            $rows = [];
            foreach ($ids as $customerId) {
                try {
                    $customer = $this->customerRepository->getById($customerId);
                } catch (\Exception) {
                    continue;
                }
                $rows[] = [
                    'entity_id' => $customerId,
                    'firstname' => $customer->getFirstname(),
                    'lastname' => $customer->getLastname(),
                    'email' => $customer->getEmail(),
                    'b2b_approval_status' => $this->getCustomAttr($customer, 'b2b_approval_status'),
                    'b2b_cnpj' => $this->getCustomAttr($customer, 'b2b_cnpj'),
                    'b2b_razao_social' => $this->getCustomAttr($customer, 'b2b_razao_social'),
                    'b2b_cnae_code' => $this->getCustomAttr($customer, 'b2b_cnae_code'),
                    'b2b_cnae_profile' => $this->getCustomAttr($customer, 'b2b_cnae_profile'),
                ];
            }

            return $rows;
        }

        $collection = $this->customerCollectionFactory->create();
        $collection->addAttributeToSelect([
            'firstname',
            'lastname',
            'email',
            'b2b_approval_status',
            'b2b_cnpj',
            'b2b_razao_social',
            'b2b_cnae_code',
            'b2b_cnae_profile',
        ]);
        $collection->addAttributeToFilter(
            'b2b_approval_status',
            ['in' => [ApprovalStatus::STATUS_PENDING, ApprovalStatus::STATUS_DATA_REVIEW]]
        );
        $collection->setOrder('entity_id', 'ASC');

        if ($limit > 0) {
            $collection->setPageSize($limit);
        }

        $collection->load();
        $rows = [];
        foreach ($collection as $customer) {
            $rows[] = $customer->getData();
        }

        return $rows;
    }

    private function hasCoreVendorChanges(): bool
    {
        $root = dirname(__DIR__, 6);
        if (!is_dir($root . '/.git')) {
            return false;
        }

        $process = new Process(
            ['git', 'status', '--porcelain', 'vendor/', 'lib/', 'setup/', 'pub/index.php'],
            $root
        );
        $process->run();

        return $process->isSuccessful() && $process->getOutput() !== '';
    }

    private function countObserverRegistrations(): int
    {
        $moduleEtcDir = $this->moduleDirReader->getModuleDir('etc', 'GrupoAwamotos_B2B');
        $files = [
            $moduleEtcDir . '/frontend/events.xml',
            $moduleEtcDir . '/events.xml',
        ];

        $total = 0;
        foreach ($files as $file) {
            if (!is_readable($file)) {
                continue;
            }
            $content = (string) file_get_contents($file);
            $total += substr_count($content, 'ApprovalScoringObserver');
        }

        return $total;
    }

    private function countDuplicateFalsePositives(): int
    {
        $count = 0;
        foreach ($this->loadPendingCustomers(0) as $row) {
            $customerId = (int) $row['entity_id'];
            if ($this->duplicateChecker->findConflict($customerId) !== null) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param array<int, string> $cache
     */
    private function resolveGroupLabel(int $groupId, array &$cache): string
    {
        if ($groupId <= 0) {
            return '—';
        }

        if (isset($cache[$groupId])) {
            return $cache[$groupId];
        }

        try {
            $cache[$groupId] = $this->groupRepository->getById($groupId)->getCode();
        } catch (\Exception) {
            $cache[$groupId] = 'Grupo #' . $groupId;
        }

        return $cache[$groupId];
    }

    /**
     * @return list<string>
     */
    private function detectBlockers(int $customerId, ApprovalScoreResultInterface $result): array
    {
        $blockers = [];
        $reason = $result->getReason();

        if ($this->duplicateChecker->findConflict($customerId) !== null) {
            $blockers[] = 'duplicidade_cnpj';
        }

        if (str_contains($reason, 'Dados incompletos') || str_contains($reason, 'incompletos:')) {
            $blockers[] = 'dados_incompletos';
        }

        if (str_contains($reason, 'CNPJ inválido') || str_contains($reason, 'não validado') || str_contains($reason, 'irregular')) {
            $blockers[] = 'cnpj_invalido_ou_irregular';
        }

        if (str_contains($reason, 'fora do perfil') || str_contains($reason, 'Fora do perfil')) {
            $blockers[] = 'cnae_fora_perfil';
        }

        if (str_contains($reason, 'adjacente')) {
            $blockers[] = 'cnae_adjacente_analise_manual';
        }

        if (str_contains($reason, 'não classificado') || str_contains($reason, 'nao classificado')) {
            $blockers[] = 'cnae_nao_classificado';
        }

        if (str_contains($reason, 'auto-aprovação CNAE está desligada')) {
            $blockers[] = 'auto_aprovacao_cnae_desligada';
        }

        return array_values(array_unique($blockers));
    }

    private function executeSegmentReport(string $outputPath, OutputInterface $output): int
    {
        $output->writeln('<info>Relatorio de segmentacao B2B (real vs ERP legado)</info>');

        $segments = $this->realRegistrationChecker->segmentPendingCustomerIds();
        $realRows = [];
        $legacyRows = [];

        foreach ($segments[RealB2BRegistrationChecker::SEGMENT_REAL_REGISTER] as $customerId) {
            $realRows[] = $this->buildSegmentRow($customerId, RealB2BRegistrationChecker::SEGMENT_REAL_REGISTER);
        }

        foreach ($segments[RealB2BRegistrationChecker::SEGMENT_ERP_LEGACY] as $customerId) {
            $legacyRows[] = $this->buildSegmentRow($customerId, RealB2BRegistrationChecker::SEGMENT_ERP_LEGACY);
        }

        $noCnae = array_values(array_filter(
            $realRows,
            static fn (array $row): bool => ($row['cnae_code'] ?? '') === ''
        ));

        $report = [
            'generated_at' => date('c'),
            'summary' => [
                'total_pending' => count($realRows) + count($legacyRows),
                'real_register' => count($realRows),
                'erp_legacy' => count($legacyRows),
                'real_without_cnae' => count($noCnae),
            ],
            'real_register' => $realRows,
            'erp_legacy_sample' => array_slice($legacyRows, 0, 10),
            'erp_legacy_total' => count($legacyRows),
            'real_without_cnae' => $noCnae,
            'recommendations' => $this->buildRecommendations($realRows),
            'next_commands' => [
                'classify_real' => 'sudo -u www-data php bin/magento b2b:cnae:classify-all --real-register-only --dry-run',
                'score_real_dry_run' => 'sudo -u www-data php bin/magento b2b:score-pending-customers --real-register-only',
                'score_real_apply' => 'sudo -u www-data php bin/magento b2b:score-pending-customers --real-register-only --apply',
            ],
        ];

        file_put_contents($outputPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $output->writeln(sprintf('Total pendentes: %d', $report['summary']['total_pending']));
        $output->writeln(sprintf('Cadastro real: %d', $report['summary']['real_register']));
        $output->writeln(sprintf('Legado ERP: %d', $report['summary']['erp_legacy']));
        $output->writeln(sprintf('Reais sem CNAE: %d', $report['summary']['real_without_cnae']));
        $output->writeln('Relatorio: ' . $outputPath);

        return Command::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSegmentRow(int $customerId, string $segment): array
    {
        try {
            $customer = $this->customerRepository->getById($customerId);
        } catch (\Exception) {
            return ['customer_id' => $customerId, 'segment' => $segment, 'error' => 'not_found'];
        }

        $missing = $this->realRegistrationChecker->getMissingRegistrationFields($customer);
        $result = $this->approvalScoreService->evaluate($customerId);

        return [
            'customer_id' => $customerId,
            'segment' => $segment,
            'erp_mapped' => $this->realRegistrationChecker->isErpMapped($customerId),
            'name' => trim($customer->getFirstname() . ' ' . $customer->getLastname()),
            'razao_social' => $this->getCustomAttr($customer, 'b2b_razao_social'),
            'cnpj' => $this->getCustomAttr($customer, 'b2b_cnpj'),
            'cnae_code' => $this->getCustomAttr($customer, 'b2b_cnae_code'),
            'cnae_profile' => $this->getCustomAttr($customer, 'b2b_cnae_profile'),
            'missing_fields' => $missing,
            'score' => ApprovalScore::getLabel($result->getScore()),
            'score_code' => $result->getScore(),
            'reason' => $result->getReason(),
            'would_auto_approve' => $result->shouldAutoApprove(),
            'blockers' => $this->detectBlockers($customerId, $result),
        ];
    }

    /**
     * @param list<array<string, mixed>> $realRows
     * @return array<string, mixed>
     */
    private function buildRecommendations(array $realRows): array
    {
        $recs = [];
        foreach ($realRows as $row) {
            $id = (int) $row['customer_id'];
            $recs[(string) $id] = match ($id) {
                8882 => [
                    'decision' => 'analise_manual',
                    'score' => 'amarelo',
                    'note' => 'CNAE adjacent (45.30-7-03). Manter fila manual; nunca auto-aprovar.',
                ],
                8905, 8926 => [
                    'decision' => 'revisao_manual_cnae',
                    'score' => 'amarelo',
                    'note' => 'API sem CNAE. Validar CNPJ MEI na Receita e classificar manualmente se necessario.',
                ],
                8582, 8880 => [
                    'decision' => 'vermelho_comercial',
                    'score' => 'vermelho',
                    'note' => 'CNAE fora do perfil AWA. Rejeitar ou avaliar excecao comercial.',
                ],
                default => [
                    'decision' => $row['score_code'] ?? 'unknown',
                    'score' => $row['score_code'] ?? 'unknown',
                    'note' => $row['reason'] ?? '',
                ],
            };
        }

        return $recs;
    }

    private function getCustomAttr(\Magento\Customer\Api\Data\CustomerInterface $customer, string $code): string
    {
        $attr = $customer->getCustomAttribute($code);

        return $attr ? (string) $attr->getValue() : '';
    }

    private function initializeAreaCode(): void
    {
        try {
            $this->appState->getAreaCode();
        } catch (\Magento\Framework\Exception\LocalizedException) {
            $this->appState->setAreaCode(Area::AREA_ADMINHTML);
        }
    }
}
