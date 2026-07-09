<?php

declare(strict_types=1);

namespace GrupoAwamotos\CatalogFix\Plugin;

use GrupoAwamotos\CatalogFix\Model\ErpProductNameNormalizer;
use Magento\Catalog\Model\Category;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;

/**
 * Aplica acentuação PT-BR em nomes de categoria exibidos no frontend.
 */
class CategoryNameNormalizerPlugin
{
    public function __construct(
        private readonly ErpProductNameNormalizer $nameNormalizer,
        private readonly State $appState,
    ) {
    }

    public function afterGetName(Category $subject, ?string $result): ?string
    {
        if ($result === null || $result === '' || !$this->isFrontend()) {
            return $result;
        }

        return $this->nameNormalizer->normalize($result);
    }

    private function isFrontend(): bool
    {
        try {
            return $this->appState->getAreaCode() === Area::AREA_FRONTEND;
        } catch (\Exception) {
            return false;
        }
    }
}
