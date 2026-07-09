<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Console\Command;

use GrupoAwamotos\B2B\Model\B2bCustomer;
use GrupoAwamotos\B2B\Model\ResourceModel\B2bCustomer\CollectionFactory;
use GrupoAwamotos\B2B\Service\CustomerGroupManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Sincroniza grupos Magento de todos os clientes B2B com seu status aprovado/pendente.
 *
 * Uso:
 *   php bin/magento b2b:sync-groups            — executa a sincronização
 *   php bin/magento b2b:sync-groups --dry-run  — apenas mostra o que seria alterado
 */
class SyncGroupsCommand extends Command
{
    private const BATCH_SIZE = 100;
    private const OPT_DRY   = 'dry-run';

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly CustomerGroupManager $groupManager,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('b2b:sync-groups')
             ->setDescription('Sincroniza grupo Magento de todos os clientes B2B com seu status (aprovado/pendente).')
             ->addOption(self::OPT_DRY, null, InputOption::VALUE_NONE, 'Exibe o que seria alterado sem aplicar mudanças.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = (bool) $input->getOption(self::OPT_DRY);

        $approvedGroupId = $this->groupManager->getGroupIdByName(CustomerGroupManager::GROUP_NAME_APPROVED);
        $pendingGroupId  = $this->groupManager->getGroupIdByName(CustomerGroupManager::GROUP_NAME_PENDING);

        if ($approvedGroupId === null || $pendingGroupId === null) {
            $output->writeln('<error>Grupos "B2B Aprovado" e/ou "B2B Pendente" não existem.</error>');
            $output->writeln('<comment>Execute: php bin/magento setup:upgrade</comment>');
            return Command::FAILURE;
        }

        $output->writeln(sprintf(
            '<info>Grupos encontrados:</info> B2B Pendente (id=%d) | B2B Aprovado (id=%d)',
            $pendingGroupId,
            $approvedGroupId
        ));

        if ($dryRun) {
            $output->writeln('<comment>[DRY-RUN] Nenhuma alteração será aplicada.</comment>');
        }

        $totals = ['aprovado' => 0, 'pendente' => 0, 'erros' => 0, 'skip' => 0];

        foreach ([B2bCustomer::STATUS_APPROVED, B2bCustomer::STATUS_PENDING] as $status) {
            $label         = $status === B2bCustomer::STATUS_APPROVED ? 'Aprovados' : 'Pendentes';
            $targetGroupId = $status === B2bCustomer::STATUS_APPROVED ? $approvedGroupId : $pendingGroupId;
            $statKey       = $status === B2bCustomer::STATUS_APPROVED ? 'aprovado' : 'pendente';

            $output->writeln("\n<info>Processando clientes {$label}...</info>");

            $total      = $this->countByStatus($status);
            $progressBar = new ProgressBar($output, $total);
            $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %message%');
            $progressBar->setMessage('iniciando...');
            $progressBar->start();

            $page = 1;
            do {
                $collection = $this->collectionFactory->create();
                $collection->addFieldToFilter('status', $status)
                           ->addFieldToFilter('customer_id', ['notnull' => true])
                           ->setPageSize(self::BATCH_SIZE)
                           ->setCurPage($page);

                $items = $collection->getItems();

                foreach ($items as $b2bCustomer) {
                    $customerId = (int) $b2bCustomer->getCustomerId();
                    $progressBar->setMessage("customer_id={$customerId}");
                    $progressBar->advance();

                    try {
                        $isAlreadyApproved = $this->groupManager->isInApprovedGroup($customerId);

                        if ($status === B2bCustomer::STATUS_APPROVED && $isAlreadyApproved) {
                            $totals['skip']++;
                            continue;
                        }

                        if (!$dryRun) {
                            if ($status === B2bCustomer::STATUS_APPROVED) {
                                $this->groupManager->assignToApprovedGroup($customerId);
                            } else {
                                $this->groupManager->assignToPendingGroup($customerId);
                            }
                        }

                        $totals[$statKey]++;
                    } catch (\Exception $e) {
                        $totals['erros']++;
                        if ($output->isVerbose()) {
                            $output->writeln("\n<error>customer_id={$customerId}: {$e->getMessage()}</error>");
                        }
                    }
                }

                $page++;
            } while (count($items) === self::BATCH_SIZE);

            $progressBar->finish();
            $output->writeln('');
        }

        $output->writeln("\n<info>Resultado:</info>");
        $output->writeln("  Aprovados sincronizados : {$totals['aprovado']}");
        $output->writeln("  Pendentes sincronizados : {$totals['pendente']}");
        $output->writeln("  Já estavam corretos     : {$totals['skip']}");
        $output->writeln("  Erros                   : {$totals['erros']}");

        if (!$dryRun && ($totals['aprovado'] + $totals['pendente']) > 0) {
            $output->writeln("\n<comment>Execute cache:flush para que os clientes vejam o efeito imediatamente:</comment>");
            $output->writeln('  php bin/magento cache:flush');
        }

        return Command::SUCCESS;
    }

    private function countByStatus(int $status): int
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('status', $status)
                   ->addFieldToFilter('customer_id', ['notnull' => true]);
        return $collection->getSize();
    }
}
