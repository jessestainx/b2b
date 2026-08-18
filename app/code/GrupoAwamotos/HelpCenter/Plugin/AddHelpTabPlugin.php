<?php

declare(strict_types=1);

namespace GrupoAwamotos\HelpCenter\Plugin;

use GrupoAwamotos\B2B\CommercialPanel\Block\Adminhtml\CommercialNavigation;
use Magento\Framework\UrlInterface;

/**
 * Adiciona a aba "Ajuda" ao cockpit comercial sem alterar o módulo B2B.
 */
class AddHelpTabPlugin
{
    public function __construct(private readonly UrlInterface $urlBuilder)
    {
    }

    /**
     * @param CommercialNavigation $subject
     * @param array<int, array{label: \Magento\Framework\Phrase, url: string, action: string}> $result
     * @return array<int, array{label: \Magento\Framework\Phrase, url: string, action: string}>
     */
    public function afterGetNavigationTabs(
        CommercialNavigation $subject,
        array $result
    ): array {
        $result[] = [
            'label'  => __('Ajuda'),
            'url'    => $this->urlBuilder->getUrl('awa_commercial/commercialhelp/index'),
            'action' => 'awa_commercial_commercialhelp_index',
        ];
        return $result;
    }
}
