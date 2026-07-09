<?php

declare(strict_types=1);

/**
 * Comando CLI para limpeza de dados antigos do REXIS ML
 */

namespace GrupoAwamotos\RexisML\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\ResourceConnection;

class CleanupCommand extends Command
{
    private ResourceConnection $resource;

    public function __construct(
        ResourceConnection $resource,
        ?string $name = null
    ) {
        parent::__construct($name);
        $this->resource = $resource;
    }

    protected function configure()
    {
        $this->setName('rexis:cleanup')
            ->setDescription('Limpar dados antigos do REXIS ML')
            ->addOption('months', 'm', InputOption::VALUE_OPTIONAL, 'Manter apenas os ultimos N meses', '6')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Apenas simular (nao excluir)');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $months = (int)$input->getOption('months');
        $dryRun = $input->getOption('dry-run');
        $conn = $this->resource->getConnection();

        $output->writeln('');
        $output->writeln('<fg=cyan;options=bold>REXIS ML - Limpeza de Dados</>');
        $output->writeln('');

        if ($dryRun) {
            $output->writeln('<comment>[DRY-RUN] Nenhum dado sera excluido.</comment>');
            $output->writeln('');
        }

        $cutoffDate = date('Y-m-d', strtotime("-{$months} months"));
        $output->writeln(sprintf('<comment>Removendo dados anteriores a: %s (%d meses)</comment>', $cutoffDate, $months));
        $output->writeln('');

        // 1. Recommendations
        $t1 = $this->resource->getTableName('rexis_dataset_recomendacao');
        $countBefore = (int)$conn->fetchOne("SELECT COUNT(*) FROM {$t1}");
        $countOld = (int)$conn->fetchOne("SELECT COUNT(*) FROM {$t1} WHERE created_at < ?", [$cutoffDate]);

        $output->writeln(sprintf(
            '  rexis_dataset_recomendacao: %s total, %s antigos',
            number_format($countBefore, 0, ',', '.'),
            number_format($countOld, 0, ',', '.')
        ));

        if (!$dryRun && $countOld > 0) {
            $deleted = $conn->delete($t1, ['created_at < ?' => $cutoffDate]);
            $output->writeln(sprintf('    <info>Excluidos: %s registros</info>', number_format($deleted, 0, ',', '.')));
        }

        // 2. Metrics
        $t2 = $this->resource->getTableName('rexis_metricas_conversao');
        $cutoffMonth = date('m-Y', strtotime("-{$months} months"));
        $countMetrics = (int)$conn->fetchOne("SELECT COUNT(*) FROM {$t2} WHERE mes_rexis_code < ?", [$cutoffMonth]);

        $output->writeln(sprintf('  rexis_metricas_conversao: %s antigos', number_format($countMetrics, 0, ',', '.')));

        if (!$dryRun && $countMetrics > 0) {
            $deleted = $conn->delete($t2, ['mes_rexis_code < ?' => $cutoffMonth]);
            $output->writeln(sprintf('    <info>Excluidos: %s registros</info>', number_format($deleted, 0, ',', '.')));
        }

        // 3. Inactive rules
        $t3 = $this->resource->getTableName('rexis_network_rules');
        $countInactive = (int)$conn->fetchOne("SELECT COUNT(*) FROM {$t3} WHERE is_active = 0");

        $output->writeln(sprintf('  rexis_network_rules (inativas): %s', number_format($countInactive, 0, ',', '.')));

        if (!$dryRun && $countInactive > 0) {
            $deleted = $conn->delete($t3, ['is_active = ?' => 0]);
            $output->writeln(sprintf('    <info>Excluidas: %s regras</info>', number_format($deleted, 0, ',', '.')));
        }

        // 4. Summary
        $output->writeln('');
        if ($dryRun) {
            $total = $countOld + $countMetrics + $countInactive;
            $output->writeln(sprintf(
                '<comment>[DRY-RUN] %s registros seriam excluidos. Use sem --dry-run para executar.</comment>',
                number_format($total, 0, ',', '.')
            ));
        } else {
            $countAfter = (int)$conn->fetchOne("SELECT COUNT(*) FROM {$t1}");
            $output->writeln(sprintf(
                '<info>Limpeza concluida. Recomendacoes: %s -> %s</info>',
                number_format($countBefore, 0, ',', '.'),
                number_format($countAfter, 0, ',', '.')
            ));
        }
        $output->writeln('');

        return Command::SUCCESS;
    }
}
