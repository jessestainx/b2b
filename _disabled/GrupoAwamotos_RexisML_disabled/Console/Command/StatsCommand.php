<?php

declare(strict_types=1);

/**
 * Comando CLI para exibir estatisticas do REXIS ML
 */

namespace GrupoAwamotos\RexisML\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Magento\Framework\App\ResourceConnection;

class StatsCommand extends Command
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
        $this->setName('rexis:stats')
            ->setDescription('Exibir estatisticas completas do sistema REXIS ML');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $conn = $this->resource->getConnection();

        $output->writeln('');
        $output->writeln('<fg=cyan;options=bold>+=============================================+</>');
        $output->writeln('<fg=cyan;options=bold>|     REXIS ML - Estatisticas do Sistema      |</>');
        $output->writeln('<fg=cyan;options=bold>+=============================================+</>');
        $output->writeln('');

        $this->showGeneralStats($output, $conn);
        $this->showTipoDistribution($output, $conn);
        $this->showTopChurn($output, $conn);
        $this->showTopCrosssell($output, $conn);
        $this->showRfmSegments($output, $conn);
        $this->showConversionMetrics($output, $conn);
        $this->showScoreDistribution($output, $conn);

        $output->writeln('<info>Gerado em: ' . date('d/m/Y H:i:s') . '</info>');
        $output->writeln('');

        return Command::SUCCESS;
    }

    private function showGeneralStats(OutputInterface $output, $conn): void
    {
        $t = $this->resource->getTableName('rexis_dataset_recomendacao');
        $row = $conn->fetchRow("
            SELECT
                COUNT(*) AS total,
                COUNT(DISTINCT identificador_cliente) AS clientes,
                COUNT(DISTINCT identificador_produto) AS produtos,
                AVG(pred) AS score_medio,
                SUM(previsao_gasto_round_up) AS valor_potencial,
                SUM(COALESCE(valor_convertida, 0)) AS valor_convertido,
                MAX(updated_at) AS last_sync
            FROM {$t}
        ");

        $output->writeln('<fg=yellow;options=bold>ESTATISTICAS GERAIS</>');
        $output->writeln('');

        $table = new Table($output);
        $table->setHeaders(['Metrica', 'Valor']);
        $table->setRows([
            ['Total Recomendacoes', number_format((int)$row['total'], 0, ',', '.')],
            ['Clientes Analisados', number_format((int)$row['clientes'], 0, ',', '.')],
            ['Produtos Recomendados', number_format((int)$row['produtos'], 0, ',', '.')],
            ['Score Medio ML', number_format((float)$row['score_medio'] * 100, 1) . '%'],
            ['Valor Potencial', 'R$ ' . number_format((float)$row['valor_potencial'], 2, ',', '.')],
            ['Valor Convertido', 'R$ ' . number_format((float)$row['valor_convertido'], 2, ',', '.')],
            ['Ultimo Sync', $row['last_sync'] ? date('d/m/Y H:i', strtotime($row['last_sync'])) : 'Nunca'],
        ]);
        $table->render();
        $output->writeln('');
    }

    private function showTipoDistribution(OutputInterface $output, $conn): void
    {
        $t = $this->resource->getTableName('rexis_dataset_recomendacao');
        $rows = $conn->fetchAll("
            SELECT
                tipo_recomendacao AS tipo,
                COUNT(*) AS total,
                AVG(pred) AS score_medio,
                SUM(previsao_gasto_round_up) AS valor
            FROM {$t}
            GROUP BY tipo_recomendacao
            ORDER BY total DESC
        ");

        $output->writeln('<fg=yellow;options=bold>DISTRIBUICAO POR TIPO</>');
        $output->writeln('');

        $table = new Table($output);
        $table->setHeaders(['Tipo', 'Qtd', 'Score Medio', 'Valor Potencial']);
        foreach ($rows as $r) {
            $table->addRow([
                ucfirst($r['tipo'] ?: 'Sem tipo'),
                number_format((int)$r['total'], 0, ',', '.'),
                number_format((float)$r['score_medio'] * 100, 1) . '%',
                'R$ ' . number_format((float)$r['valor'], 2, ',', '.'),
            ]);
        }
        $table->render();
        $output->writeln('');
    }

    private function showTopChurn(OutputInterface $output, $conn): void
    {
        $t = $this->resource->getTableName('rexis_dataset_recomendacao');
        $rows = $conn->fetchAll("
            SELECT identificador_cliente, identificador_produto, pred, previsao_gasto_round_up, recencia
            FROM {$t}
            WHERE tipo_recomendacao = 'churn' AND pred >= 0.3
            ORDER BY pred DESC
            LIMIT 10
        ");

        $output->writeln('<fg=red;options=bold>TOP 10 OPORTUNIDADES DE CHURN</>');
        $output->writeln('');

        if (empty($rows)) {
            $output->writeln('<comment>  Nenhuma oportunidade encontrada.</comment>');
            $output->writeln('');
            return;
        }

        $table = new Table($output);
        $table->setHeaders(['Cliente', 'Produto', 'Score', 'Valor Previsto', 'Recencia']);
        foreach ($rows as $r) {
            $table->addRow([
                '#' . $r['identificador_cliente'],
                $r['identificador_produto'],
                number_format((float)$r['pred'] * 100, 1) . '%',
                'R$ ' . number_format((float)$r['previsao_gasto_round_up'], 2, ',', '.'),
                $r['recencia'] . ' dias',
            ]);
        }
        $table->render();
        $output->writeln('');
    }

    private function showTopCrosssell(OutputInterface $output, $conn): void
    {
        $t = $this->resource->getTableName('rexis_network_rules');
        $rows = $conn->fetchAll("
            SELECT antecedent, consequent, lift, confidence, support
            FROM {$t}
            WHERE is_active = 1
            ORDER BY lift DESC
            LIMIT 10
        ");

        $output->writeln('<fg=green;options=bold>TOP 10 REGRAS DE CROSS-SELL (MBA)</>');
        $output->writeln('');

        if (empty($rows)) {
            $output->writeln('<comment>  Nenhuma regra encontrada.</comment>');
            $output->writeln('');
            return;
        }

        $table = new Table($output);
        $table->setHeaders(['Comprou (A)', 'Sugestao (B)', 'Lift', 'Confidence', 'Support']);
        foreach ($rows as $r) {
            $table->addRow([
                strlen($r['antecedent']) > 30 ? substr($r['antecedent'], 0, 27) . '...' : $r['antecedent'],
                strlen($r['consequent']) > 30 ? substr($r['consequent'], 0, 27) . '...' : $r['consequent'],
                number_format((float)$r['lift'], 2),
                number_format((float)$r['confidence'] * 100, 1) . '%',
                number_format((float)$r['support'] * 100, 3) . '%',
            ]);
        }
        $table->render();
        $output->writeln('');
    }

    private function showRfmSegments(OutputInterface $output, $conn): void
    {
        $t = $this->resource->getTableName('rexis_customer_classification');
        $rows = $conn->fetchAll("
            SELECT
                classificacao_cliente,
                COUNT(*) AS total,
                AVG(monetary) AS valor_medio,
                AVG(frequency) AS freq_media,
                AVG(recency) AS recencia_media
            FROM {$t}
            GROUP BY classificacao_cliente
            ORDER BY total DESC
        ");

        $output->writeln('<fg=magenta;options=bold>SEGMENTOS RFM</>');
        $output->writeln('');

        if (empty($rows)) {
            $output->writeln('<comment>  Nenhum segmento encontrado.</comment>');
            $output->writeln('');
            return;
        }

        $table = new Table($output);
        $table->setHeaders(['Segmento', 'Clientes', 'Valor Medio', 'Freq Media', 'Recencia Media']);
        foreach ($rows as $r) {
            $table->addRow([
                $r['classificacao_cliente'] ?: 'Nao classificado',
                number_format((int)$r['total'], 0, ',', '.'),
                'R$ ' . number_format((float)$r['valor_medio'], 2, ',', '.'),
                number_format((float)$r['freq_media'], 1),
                number_format((float)$r['recencia_media'], 0) . ' dias',
            ]);
        }
        $table->render();
        $output->writeln('');
    }

    private function showConversionMetrics(OutputInterface $output, $conn): void
    {
        $t = $this->resource->getTableName('rexis_metricas_conversao');
        $rows = $conn->fetchAll("SELECT * FROM {$t} ORDER BY mes_rexis_code DESC LIMIT 3");

        $output->writeln('<fg=blue;options=bold>METRICAS DE CONVERSAO (ultimos 3 meses)</>');
        $output->writeln('');

        if (empty($rows)) {
            $output->writeln('<comment>  Nenhuma metrica encontrada.</comment>');
            $output->writeln('');
            return;
        }

        $table = new Table($output);
        $table->setHeaders(['Mes', 'Recomendados', 'Compraram', 'Conversao', 'Esperado', 'Convertido']);
        foreach ($rows as $r) {
            $table->addRow([
                $r['mes_rexis_code'],
                number_format((int)$r['n_clientes_rec_mes_atual'], 0, ',', '.'),
                number_format((int)$r['n_cliente_comprou_mes_atual'], 0, ',', '.'),
                number_format((float)$r['perc_conversao_cliente'], 2) . '%',
                'R$ ' . number_format((float)$r['valor_esperado_atual'], 2, ',', '.'),
                'R$ ' . number_format((float)$r['valor_convertido_atual'], 2, ',', '.'),
            ]);
        }
        $table->render();
        $output->writeln('');
    }

    private function showScoreDistribution(OutputInterface $output, $conn): void
    {
        $t = $this->resource->getTableName('rexis_dataset_recomendacao');
        $rows = $conn->fetchAll("
            SELECT
                CASE
                    WHEN pred < 0.2 THEN '0-20%'
                    WHEN pred < 0.4 THEN '20-40%'
                    WHEN pred < 0.6 THEN '40-60%'
                    WHEN pred < 0.8 THEN '60-80%'
                    ELSE '80-100%'
                END AS faixa,
                COUNT(*) AS qtd
            FROM {$t}
            GROUP BY faixa
            ORDER BY MIN(pred) ASC
        ");

        $output->writeln('<fg=yellow;options=bold>DISTRIBUICAO DE SCORES</>');
        $output->writeln('');

        $maxQtd = 0;
        foreach ($rows as $r) {
            $maxQtd = max($maxQtd, (int)$r['qtd']);
        }

        foreach ($rows as $r) {
            $qtd = (int)$r['qtd'];
            $barLen = $maxQtd > 0 ? (int)round(($qtd / $maxQtd) * 40) : 0;
            $bar = str_repeat('#', $barLen);
            $output->writeln(sprintf(
                '  <fg=cyan>%7s</> %s %s',
                $r['faixa'],
                $bar,
                number_format($qtd, 0, ',', '.')
            ));
        }
        $output->writeln('');
    }
}
