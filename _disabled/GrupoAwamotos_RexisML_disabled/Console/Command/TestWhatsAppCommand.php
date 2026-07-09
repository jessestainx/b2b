<?php

declare(strict_types=1);

/**
 * Comando CLI para testar envio de WhatsApp
 */

namespace GrupoAwamotos\RexisML\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use GrupoAwamotos\RexisML\Helper\WhatsAppNotifier;
use Magento\Framework\App\ResourceConnection;

class TestWhatsAppCommand extends Command
{
    private WhatsAppNotifier $whatsappNotifier;
    private ResourceConnection $resource;

    public function __construct(
        WhatsAppNotifier $whatsappNotifier,
        ResourceConnection $resource,
        ?string $name = null
    ) {
        parent::__construct($name);
        $this->whatsappNotifier = $whatsappNotifier;
        $this->resource = $resource;
    }

    protected function configure()
    {
        $this->setName('rexis:test-whatsapp')
            ->setDescription('Enviar mensagem de teste via WhatsApp')
            ->addArgument(
                'phone',
                InputArgument::REQUIRED,
                'Numero de telefone (formato: 5511999998888)'
            );
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('');
        $output->writeln('<fg=cyan;options=bold>REXIS ML - Teste de WhatsApp</>');
        $output->writeln('');

        $phone = $input->getArgument('phone');

        if (!preg_match('/^55\d{10,11}$/', $phone)) {
            $output->writeln('<error>Formato de telefone invalido!</error>');
            $output->writeln('<comment>Use o formato: 5511999998888 (DDI + DDD + numero)</comment>');
            return Command::FAILURE;
        }

        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('rexis_dataset_recomendacao');

        $rows = $connection->fetchAll(
            $connection->select()
                ->from($table)
                ->where('tipo_recomendacao = ?', 'crosssell')
                ->where('pred >= ?', 0.03)
                ->order('pred DESC')
                ->limit(3)
        );

        if (empty($rows)) {
            $output->writeln('<comment>Nenhuma oportunidade de Cross-sell encontrada.</comment>');
            $output->writeln('<comment>Enviando mensagem de teste generica...</comment>');
        } else {
            $output->writeln(sprintf('<comment>Encontradas %d oportunidades de Cross-sell</comment>', count($rows)));
            foreach ($rows as $r) {
                $output->writeln(sprintf(
                    '  Cliente: %s | Produto: %s | Score: %.1f%% | Valor: R$ %s',
                    $r['identificador_cliente'],
                    $r['identificador_produto'],
                    (float)$r['pred'] * 100,
                    number_format((float)$r['previsao_gasto_round_up'], 2, ',', '.')
                ));
            }
        }

        $output->writeln('');
        $output->writeln("<comment>Enviando para: $phone</comment>");

        try {
            if (!empty($rows)) {
                $this->whatsappNotifier->sendCrosssellAlert($rows);
                $output->writeln('<info>Mensagem de cross-sell enviada!</info>');
            } else {
                // No data — use Z-API testConnection which sends a test message
                $result = $this->whatsappNotifier->sendTestMessage($phone);
                if ($result) {
                    $output->writeln('<info>Mensagem de teste enviada!</info>');
                } else {
                    $output->writeln('<error>Falha ao enviar mensagem de teste</error>');
                    return Command::FAILURE;
                }
            }
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>Erro ao enviar: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
