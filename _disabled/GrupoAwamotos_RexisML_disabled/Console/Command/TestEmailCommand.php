<?php

declare(strict_types=1);

/**
 * Comando CLI para testar envio de emails
 */

namespace GrupoAwamotos\RexisML\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use GrupoAwamotos\RexisML\Helper\EmailNotifier;
use Magento\Framework\App\ResourceConnection;

class TestEmailCommand extends Command
{
    private EmailNotifier $emailNotifier;
    private ResourceConnection $resource;

    public function __construct(
        EmailNotifier $emailNotifier,
        ResourceConnection $resource,
        ?string $name = null
    ) {
        parent::__construct($name);
        $this->emailNotifier = $emailNotifier;
        $this->resource = $resource;
    }

    protected function configure()
    {
        $this->setName('rexis:test-email')
            ->setDescription('Enviar email de teste de alerta REXIS ML')
            ->addOption('type', 't', InputOption::VALUE_OPTIONAL, 'Tipo: churn ou crosssell', 'churn')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Quantidade de oportunidades', '5');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $type = $input->getOption('type');
        $limit = (int)$input->getOption('limit');

        $output->writeln('');
        $output->writeln('<fg=cyan;options=bold>REXIS ML - Teste de Email (' . ucfirst($type) . ')</>');
        $output->writeln('');

        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('rexis_dataset_recomendacao');

        $select = $connection->select()
            ->from($table)
            ->where('tipo_recomendacao = ?', $type)
            ->where('pred >= ?', 0.3)
            ->order('pred DESC')
            ->limit($limit);

        $rows = $connection->fetchAll($select);

        if (empty($rows)) {
            $output->writeln('<error>Nenhuma oportunidade de ' . $type . ' encontrada.</error>');
            $output->writeln('<comment>Execute primeiro: php bin/magento rexis:sync</comment>');
            return Command::FAILURE;
        }

        $output->writeln(sprintf('<comment>Encontradas %d oportunidades de %s</comment>', count($rows), $type));
        foreach ($rows as $row) {
            $output->writeln(sprintf(
                '  Cliente: %s | Produto: %s | Score: %.1f%% | Valor: R$ %s',
                $row['identificador_cliente'],
                $row['identificador_produto'],
                (float)$row['pred'] * 100,
                number_format((float)$row['previsao_gasto_round_up'], 2, ',', '.')
            ));
        }
        $output->writeln('');

        $output->writeln('<comment>Enviando email...</comment>');

        $result = $type === 'crosssell'
            ? $this->emailNotifier->sendCrosssellAlert($rows)
            : $this->emailNotifier->sendChurnAlert($rows);

        if ($result) {
            $output->writeln('<info>Email enviado com sucesso!</info>');
            return Command::SUCCESS;
        } else {
            $output->writeln('<error>Falha ao enviar email. Verifique os logs.</error>');
            return Command::FAILURE;
        }
    }
}
