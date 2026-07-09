<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Console\Command;

use GrupoAwamotos\B2B\Service\PrivateLabelDetector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Varre todo o catálogo em busca de produtos cujo SKU corresponde a um
 * alias de cliente Private Label e os registra em
 * grupoawamotos_b2b_exclusive_product.
 *
 * Uso:
 *   php bin/magento b2b:private-label:scan
 *
 * Executar após:
 *   - Primeiro deploy do módulo (protege produtos já existentes)
 *   - Qualquer importação CSV com produtos Private Label
 *   - Adição de novo alias em grupoawamotos_b2b_erp_alias
 */
class PrivateLabelScanCommand extends Command
{
    public function __construct(
        private readonly PrivateLabelDetector $detector
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('b2b:private-label:scan')
             ->setDescription('Varre o catálogo e protege produtos Private Label pelo sufixo do SKU');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Iniciando scan de produtos Private Label...</info>');

        $count = $this->detector->scanAll();

        if ($count === 0) {
            $output->writeln('<comment>Nenhum produto novo encontrado para proteger.</comment>');
        } else {
            $output->writeln(sprintf(
                '<info>%d produto(s) registrado(s) como Private Label.</info>',
                $count
            ));
        }

        $output->writeln('<info>Scan concluído.</info>');

        return Command::SUCCESS;
    }
}
