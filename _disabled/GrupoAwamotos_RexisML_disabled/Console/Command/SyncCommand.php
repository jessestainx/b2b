<?php

declare(strict_types=1);

namespace GrupoAwamotos\RexisML\Console\Command;

use GrupoAwamotos\RexisML\Model\DataPipeline\ErpDataCollector;
use GrupoAwamotos\RexisML\Model\DataPipeline\RfmCalculator;
use GrupoAwamotos\RexisML\Model\DataPipeline\ChurnDetector;
use GrupoAwamotos\RexisML\Model\DataPipeline\CrossSellGenerator;
use GrupoAwamotos\RexisML\Model\DataPipeline\CrossSellRecommender;
use GrupoAwamotos\RexisML\Helper\EmailNotifier;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SyncCommand extends Command
{
    private ErpDataCollector $collector;
    private RfmCalculator $rfm;
    private ChurnDetector $churn;
    private CrossSellGenerator $crossSell;
    private CrossSellRecommender $crossSellRecommender;
    private EmailNotifier $emailNotifier;
    private ScopeConfigInterface $scopeConfig;
    private ResourceConnection $resource;

    public function __construct(
        ErpDataCollector $collector,
        RfmCalculator $rfm,
        ChurnDetector $churn,
        CrossSellGenerator $crossSell,
        CrossSellRecommender $crossSellRecommender,
        EmailNotifier $emailNotifier,
        ScopeConfigInterface $scopeConfig,
        ResourceConnection $resource,
        ?string $name = null
    ) {
        $this->collector = $collector;
        $this->rfm = $rfm;
        $this->churn = $churn;
        $this->crossSell = $crossSell;
        $this->crossSellRecommender = $crossSellRecommender;
        $this->emailNotifier = $emailNotifier;
        $this->scopeConfig = $scopeConfig;
        $this->resource = $resource;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('rexis:sync')
            ->setDescription('Sincroniza dados do ERP e gera recomendacoes ML (PHP nativo)')
            ->addOption('months', 'm', InputOption::VALUE_OPTIONAL, 'Meses de historico a analisar', '24');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $startTime = microtime(true);
        $months = (int)$input->getOption('months');

        $output->writeln('<info>REXIS ML - Sincronizacao via PHP (pipeline nativo)</info>');
        $output->writeln('');

        try {
            // Step 1: Collect data from ERP
            $output->writeln('<comment>[1/5] Coletando dados do ERP...</comment>');
            $orders = $this->collector->fetchOrders($months);
            $output->writeln(sprintf('  Pedidos/itens carregados: <info>%d</info>', count($orders)));

            if (empty($orders)) {
                $output->writeln('<error>Nenhum pedido encontrado no ERP. Verifique a conexao.</error>');
                return Command::FAILURE;
            }

            $customers = $this->collector->fetchCustomers();
            $output->writeln(sprintf('  Clientes carregados: <info>%d</info>', count($customers)));

            $products = $this->collector->fetchProducts();
            $output->writeln(sprintf('  Produtos carregados: <info>%d</info>', count($products)));
            $output->writeln('');

            // Step 2: RFM Classification
            $output->writeln('<comment>[2/5] Calculando segmentacao RFM...</comment>');
            $rfmCount = $this->rfm->calculate($orders, $customers);
            $output->writeln(sprintf('  Clientes classificados: <info>%d</info>', $rfmCount));
            $output->writeln('');

            // Step 3: Churn Detection
            $output->writeln('<comment>[3/5] Detectando oportunidades de churn...</comment>');
            $churnCount = $this->churn->detect($orders, $products);
            $output->writeln(sprintf('  Recomendacoes de churn: <info>%d</info>', $churnCount));
            $output->writeln('');

            // Step 4: Cross-sell Rules (Market Basket Analysis)
            $output->writeln('<comment>[4/5] Gerando regras de cross-sell (MBA)...</comment>');
            $crossCount = $this->crossSell->generate($orders);
            $output->writeln(sprintf('  Regras de associacao: <info>%d</info>', $crossCount));
            $output->writeln('');

            // Step 5: Cross-sell Per-Customer Recommendations
            $output->writeln('<comment>[5/5] Gerando recomendacoes cross-sell por cliente...</comment>');
            $xsRecCount = $this->crossSellRecommender->recommend($orders, $products);
            $output->writeln(sprintf('  Recomendacoes cross-sell: <info>%d</info>', $xsRecCount));
            $output->writeln('');

            // Email Alerts (optional, config-driven)
            $this->sendAlerts($output);

            $duration = round(microtime(true) - $startTime, 2);
            $output->writeln(sprintf(
                '<info>Sincronizacao concluida em %ss! RFM=%d | Churn=%d | Regras=%d | Cross-sell=%d</info>',
                $duration,
                $rfmCount,
                $churnCount,
                $crossCount,
                $xsRecCount
            ));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>Erro: ' . $e->getMessage() . '</error>');
            $output->writeln('<comment>Verifique as configuracoes de conexao ERP em:</comment>');
            $output->writeln('  Admin > Stores > Configuration > ERP Integration > Connection');
            return Command::FAILURE;
        }
    }

    private function sendAlerts(OutputInterface $output): void
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('rexis_dataset_recomendacao');

        // Churn alerts
        $churnEnabled = $this->scopeConfig->getValue(
            'rexisml/alerts/churn_enabled',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        if ($churnEnabled) {
            $output->writeln('<comment>Enviando alerta de churn...</comment>');
            $topChurn = $connection->fetchAll(
                $connection->select()->from($table)
                    ->where('tipo_recomendacao = ?', 'churn')
                    ->where('pred >= 0.3')
                    ->order('pred DESC')
                    ->limit(10)
            );
            if ($this->emailNotifier->sendChurnAlert($topChurn)) {
                $output->writeln('  <info>Alerta de churn enviado!</info>');
            } else {
                $output->writeln('  <comment>Nenhuma oportunidade de churn para alertar.</comment>');
            }
        }

        // Cross-sell alerts
        $crossEnabled = $this->scopeConfig->getValue(
            'rexisml/alerts/crosssell_enabled',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        if ($crossEnabled) {
            $output->writeln('<comment>Enviando alerta de cross-sell...</comment>');
            $topCross = $connection->fetchAll(
                $connection->select()->from($table)
                    ->where('tipo_recomendacao = ?', 'crosssell')
                    ->where('pred >= 0.3')
                    ->order('pred DESC')
                    ->limit(10)
            );
            if ($this->emailNotifier->sendCrosssellAlert($topCross)) {
                $output->writeln('  <info>Alerta de cross-sell enviado!</info>');
            } else {
                $output->writeln('  <comment>Nenhuma oportunidade de cross-sell para alertar.</comment>');
            }
        }
    }
}
