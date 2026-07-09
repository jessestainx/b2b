<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Console\Command;

use GrupoAwamotos\B2B\Helper\CnpjValidator;
use GrupoAwamotos\B2B\Model\CnaeClassifier;
use GrupoAwamotos\B2B\Model\RealB2BRegistrationChecker;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Classifica retroativamente clientes B2B por CNAE via ReceitaWS/BrasilAPI.
 *
 * Uso:
 *   php bin/magento b2b:cnae:classify-all
 *   php bin/magento b2b:cnae:classify-all --dry-run
 *   php bin/magento b2b:cnae:classify-all --reclassify
 *   php bin/magento b2b:cnae:classify-all --limit=100
 *   php bin/magento b2b:cnae:classify-all --real-register-only
 *   php bin/magento b2b:cnae:classify-all --pending-only
 */
class ClassifyCnaeCommand extends Command
{
    private const OPTION_DRY_RUN    = 'dry-run';
    private const OPTION_RECLASSIFY = 'reclassify';
    private const OPTION_LIMIT      = 'limit';
    private const OPTION_SLEEP            = 'sleep';
    private const OPTION_REAL_REGISTER    = 'real-register-only';
    private const OPTION_PENDING_ONLY     = 'pending-only';

    public function __construct(
        private readonly CustomerCollectionFactory $customerCollectionFactory,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly CnpjValidator $cnpjValidator,
        private readonly CnaeClassifier $cnaeClassifier,
        private readonly RealB2BRegistrationChecker $realRegistrationChecker,
        private readonly EavConfig $eavConfig,
        private readonly State $appState,
        private readonly LoggerInterface $logger,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('b2b:cnae:classify-all')
            ->setDescription('Classifica clientes B2B por CNAE via ReceitaWS/BrasilAPI')
            ->addOption(self::OPTION_DRY_RUN, null, InputOption::VALUE_NONE, 'Apenas lista o que seria feito, sem salvar')
            ->addOption(self::OPTION_RECLASSIFY, null, InputOption::VALUE_NONE, 'Inclui clientes que ja tem CNAE (re-classifica tudo)')
            ->addOption(self::OPTION_LIMIT, null, InputOption::VALUE_REQUIRED, 'Limite de clientes a processar', '0')
            ->addOption(self::OPTION_SLEEP, null, InputOption::VALUE_REQUIRED, 'Segundos de sleep entre requisicoes API (default: 0)', '0')
            ->addOption(
                self::OPTION_REAL_REGISTER,
                null,
                InputOption::VALUE_NONE,
                'Somente cadastros reais pendentes (/b2b/register/) com dados B2B completos; exclui legado ERP'
            )
            ->addOption(
                self::OPTION_PENDING_ONLY,
                null,
                InputOption::VALUE_NONE,
                'Alias de --real-register-only: pendentes com fluxo real de cadastro B2B'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode(Area::AREA_ADMINHTML);
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->logger->warning($e->getMessage());
        }

        $dryRun     = (bool) $input->getOption(self::OPTION_DRY_RUN);
        $reclassify = (bool) $input->getOption(self::OPTION_RECLASSIFY);
        $realOnly   = (bool) $input->getOption(self::OPTION_REAL_REGISTER)
            || (bool) $input->getOption(self::OPTION_PENDING_ONLY);
        $limit      = max(0, (int) $input->getOption(self::OPTION_LIMIT));
        $sleepUs    = max(0, (int) ((float) $input->getOption(self::OPTION_SLEEP) * 1_000_000));

        $output->writeln('<info>Classificacao CNAE -- AWA Motos B2B</info>');
        if ($dryRun) {
            $output->writeln('<comment>Modo DRY-RUN: nenhum dado sera salvo.</comment>');
        }
        if ($realOnly) {
            $output->writeln('<comment>Filtro: cadastros reais pendentes (exclui legado ERP).</comment>');
        }

        $customerIds = $this->fetchCustomerIds($reclassify, $limit, $realOnly);
        $total       = count($customerIds);

        if ($total === 0) {
            $output->writeln('<comment>Nenhum cliente elegivel encontrado.</comment>');
            return Command::SUCCESS;
        }

        $output->writeln(sprintf('Clientes a processar: <info>%d</info>', $total));

        $progress = new ProgressBar($output, $total);
        $progress->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s% | %message%');
        $progress->start();

        $stats = [
            'classified'  => 0,
            'direct'      => 0,
            'adjacent'    => 0,
            'off_profile' => 0,
            'no_api_data' => 0,
            'errors'      => 0,
        ];

        foreach ($customerIds as $i => $customerId) {
            try {
                $result = $this->processCustomer((int) $customerId, $dryRun);

                if ($result === null) {
                    $stats['no_api_data']++;
                    $progress->setMessage("sem dados API: cliente #{$customerId}");
                } else {
                    $stats['classified']++;
                    $stats[$result['profile']] = ($stats[$result['profile']] ?? 0) + 1;
                    $progress->setMessage("{$result['profile']}: {$result['cnae_code']} (#{$customerId})");
                }
            } catch (\Exception $e) {
                $stats['errors']++;
                $progress->setMessage("erro #{$customerId}: " . substr($e->getMessage(), 0, 60));
                $this->logger->error(
                    '[B2B CNAE] classify-all error for customer #' . $customerId . ': ' . $e->getMessage()
                );
            }

            $progress->advance();

            if ($sleepUs > 0 && $i < $total - 1) {
                usleep($sleepUs);
            }
        }

        $progress->finish();
        $output->writeln('');
        $output->writeln('');
        $output->writeln('<info>Resultado:</info>');
        $output->writeln(sprintf('  Classificados  : %d', $stats['classified']));
        $output->writeln(sprintf('  Direct (motos) : %d', $stats['direct']));
        $output->writeln(sprintf('  Adjacent (auto): %d', $stats['adjacent']));
        $output->writeln(sprintf('  Off-profile    : %d', $stats['off_profile']));
        $output->writeln(sprintf('  Sem dado API   : %d', $stats['no_api_data']));
        $output->writeln(sprintf('  Erros          : %d', $stats['errors']));

        $this->logger->info('[B2B CNAE] classify-all completed', $stats);

        return Command::SUCCESS;
    }

    /**
     * Classifica um unico cliente: busca API, salva atributos CNAE.
     *
     * @return array{profile: string, cnae_code: string, cnae_description: string}|null
     */
    private function processCustomer(int $customerId, bool $dryRun): ?array
    {
        $customer = $this->customerRepository->getById($customerId);

        $cnpjAttr = $customer->getCustomAttribute('b2b_cnpj');
        if ($cnpjAttr === null) {
            return null;
        }

        $cnpj = preg_replace('/\D/', '', (string) $cnpjAttr->getValue());
        if (strlen($cnpj) !== 14) {
            return null;
        }

        $apiData = $this->cnpjValidator->validateApi($cnpj);
        if ($apiData === null || empty($apiData['data'])) {
            return null;
        }

        $rawData  = $apiData['data'];
        $cnaeCode = $this->cnaeClassifier->extractCnaeCode($rawData);
        $cnaeDesc = $this->cnaeClassifier->extractCnaeDescription($rawData);

        if ($cnaeCode === '') {
            return null;
        }

        $profile = $this->cnaeClassifier->classify($cnaeCode);

        if (!$dryRun) {
            $customer->setCustomAttribute('b2b_cnae_code', $cnaeCode);
            $customer->setCustomAttribute('b2b_cnae_description', $cnaeDesc);
            $customer->setCustomAttribute('b2b_cnae_profile', $profile);
            $this->customerRepository->save($customer);
        }

        return ['profile' => $profile, 'cnae_code' => $cnaeCode, 'cnae_description' => $cnaeDesc];
    }

    /**
     * Retorna IDs dos clientes PJ com b2b_cnpj preenchido.
     *
     * @return string[]
     */
    private function fetchCustomerIds(bool $reclassify, int $limit, bool $realOnly = false): array
    {
        $cnpjAttrId = $this->getAttributeId('b2b_cnpj');
        $cnaeAttrId = $this->getAttributeId('b2b_cnae_code');

        if ($cnpjAttrId === 0) {
            return [];
        }

        if ($realOnly) {
            $segments = $this->realRegistrationChecker->segmentPendingCustomerIds();
            $customerIds = array_map('strval', $segments[RealB2BRegistrationChecker::SEGMENT_REAL_REGISTER]);

            if (!$reclassify && $cnaeAttrId > 0) {
                $filtered = [];
                foreach ($customerIds as $customerId) {
                    try {
                        $customer = $this->customerRepository->getById((int) $customerId);
                    } catch (\Exception) {
                        continue;
                    }
                    $cnae = $customer->getCustomAttribute('b2b_cnae_code');
                    $cnaeVal = $cnae ? trim((string) $cnae->getValue()) : '';
                    if ($cnaeVal === '') {
                        $filtered[] = $customerId;
                    }
                }
                $customerIds = $filtered;
            }

            if ($limit > 0) {
                $customerIds = array_slice($customerIds, 0, $limit);
            }

            return $customerIds;
        }

        $collection = $this->customerCollectionFactory->create();
        $collection->addAttributeToFilter('b2b_cnpj', ['notnull' => true]);
        $collection->addAttributeToFilter('b2b_cnpj', ['neq' => '']);

        if (!$reclassify && $cnaeAttrId > 0) {
            $collection->addAttributeToFilter([
                ['attribute' => 'b2b_cnae_code', 'null' => true],
                ['attribute' => 'b2b_cnae_code', 'eq' => ''],
            ], null, 'left');
        }

        $collection->setOrder('entity_id', 'ASC');

        if ($limit > 0) {
            $collection->setPageSize($limit);
            $collection->setCurPage(1);
        }

        return $collection->getColumnValues('entity_id');
    }

    private function getAttributeId(string $attributeCode): int
    {
        $attribute = $this->eavConfig->getAttribute('customer', $attributeCode);
        return (int) $attribute->getId();
    }
}
