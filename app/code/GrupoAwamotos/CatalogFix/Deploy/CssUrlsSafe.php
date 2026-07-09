<?php

declare(strict_types=1);

namespace GrupoAwamotos\CatalogFix\Deploy;

use Magento\Deploy\Package\Package;
use Magento\Deploy\Package\Processor\PostProcessor\CssUrls;
use Magento\Framework\Filesystem;
use Magento\Framework\View\Asset\Minification;
use Psr\Log\LoggerInterface;

/**
 * Safe wrapper for CssUrls post-processor.
 *
 * Handles null PackageFile from getFileFromParent() in getValidExternalUrl()
 * when a CSS @import references a file not found in ancestors (e.g. email-fonts.css).
 */
class CssUrlsSafe extends CssUrls
{
    private LoggerInterface $logger;

    public function __construct(
        Filesystem $filesystem,
        Minification $minification,
        LoggerInterface $logger
    ) {
        parent::__construct($filesystem, $minification);
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function process(Package $package, array $options): bool
    {
        try {
            return parent::process($package, $options);
        } catch (\Error | \TypeError $e) {
            $this->logger->warning(
                'CssUrls post-processor skipped for ' . $package->getPath() . ': ' . $e->getMessage()
            );
            return true;
        }
    }
}
