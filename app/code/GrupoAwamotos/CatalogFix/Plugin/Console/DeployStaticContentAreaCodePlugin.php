<?php

declare(strict_types=1);

namespace GrupoAwamotos\CatalogFix\Plugin\Console;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Setup\Console\Command\DeployStaticContentCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Garante area code antes do deploy estático (CLI não define área por padrão).
 */
class DeployStaticContentAreaCodePlugin
{
    public function __construct(
        private readonly State $appState
    ) {
    }

    /**
     * @return array{0: InputInterface, 1: OutputInterface}
     */
    public function beforeExecute(
        DeployStaticContentCommand $subject,
        InputInterface $input,
        OutputInterface $output
    ): array {
        try {
            $this->appState->getAreaCode();
        } catch (LocalizedException) {
            $this->appState->setAreaCode(Area::AREA_GLOBAL);
        }

        return [$input, $output];
    }
}
