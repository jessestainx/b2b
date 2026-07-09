<?php

/**
 * Breadcrumbs Schema.org Block
 */

declare(strict_types=1);

namespace GrupoAwamotos\SchemaOrg\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Catalog\Helper\Data as CatalogHelper;

class Breadcrumbs extends Template
{
    protected $catalogHelper;

    public function __construct(
        Context $context,
        CatalogHelper $catalogHelper,
        array $data = []
    ) {
        $this->catalogHelper = $catalogHelper;
        parent::__construct($context, $data);
    }

    /**
     * Gera BreadcrumbList Schema.org
     */
    public function getBreadcrumbsSchema()
    {
        $breadcrumbs = $this->catalogHelper->getBreadcrumbPath();

        if (empty($breadcrumbs)) {
            return '';
        }

        $itemListElement = [];
        $position = 1;

        foreach ($breadcrumbs as $crumb) {
            $item = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $crumb['label']
            ];

            if (!empty($crumb['link'])) {
                $item['item'] = $crumb['link'];
            }

            $itemListElement[] = $item;
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $itemListElement
        ];

        return json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
