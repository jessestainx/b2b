<?php

declare(strict_types=1);

namespace GrupoAwamotos\PreprocessedFallback\Plugin\View\Element;

use GrupoAwamotos\PreprocessedFallback\Model\PreprocessedTemplateHealer;
use Magento\Framework\View\Element\Template;

/**
 * Em production, Template::fetchView() loga "Invalid template file" e devolve ''
 * sem chegar no Php engine — por isso o header sumia silenciosamente.
 * Cura o path preprocessed (ou faz fallback para o source) antes da validação.
 */
final class TemplateFallbackPlugin
{
    public function __construct(
        private readonly PreprocessedTemplateHealer $templateHealer
    ) {
    }

    /**
     * @param string $fileName
     */
    public function aroundFetchView(Template $subject, callable $proceed, $fileName): string
    {
        if (is_string($fileName) && $fileName !== '' && $this->templateHealer->isPreprocessedPath($fileName)) {
            $fileName = $this->templateHealer->resolveRenderablePath($fileName);
        }

        return (string) $proceed($fileName);
    }
}
