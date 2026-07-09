<?php

/**
 * Plugin: add <lastmod> to the home (store base URL) sitemap item.
 *
 * Magento's StoreUrl item provider creates the home URL without an updatedAt
 * date, so the sitemap never contains a <lastmod> for the root URL.  This
 * plugin reads the update_time from the active CMS home page and injects it.
 */

declare(strict_types=1);

namespace GrupoAwamotos\CatalogFix\Plugin;

use Magento\Framework\App\ResourceConnection;
use Magento\Sitemap\Model\ItemProvider\StoreUrl;
use Magento\Sitemap\Model\SitemapItemInterfaceFactory;

class SitemapHomeLastmodPlugin
{
    private ResourceConnection $resource;
    private SitemapItemInterfaceFactory $itemFactory;

    public function __construct(
        ResourceConnection $resource,
        SitemapItemInterfaceFactory $itemFactory
    ) {
        $this->resource = $resource;
        $this->itemFactory = $itemFactory;
    }

    /**
     * Replace the home sitemap item with one that includes the CMS page lastmod.
     *
     * @param StoreUrl $subject
     * @param array $result
     * @param int $storeId
     * @return array
     */
    public function afterGetItems(StoreUrl $subject, array $result, $storeId): array
    {
        $updatedAt = $this->getHomeCmsPageUpdatedAt();

        if ($updatedAt === null || empty($result)) {
            return $result;
        }

        foreach ($result as $index => $item) {
            if ($item->getUrl() === '') {
                $result[$index] = $this->itemFactory->create([
                    'url'             => '',
                    'priority'        => $item->getPriority(),
                    'changeFrequency' => $item->getChangeFrequency(),
                    'updatedAt'       => $updatedAt,
                ]);
            }
        }

        return $result;
    }

    /**
     * Fetch update_time from the active CMS home page.
     */
    private function getHomeCmsPageUpdatedAt(): ?string
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('cms_page');

        $updatedAt = $connection->fetchOne(
            $connection->select()
                ->from($table, ['update_time'])
                ->where('identifier = ?', 'homepage_ayo_home5')
                ->limit(1)
        );

        return $updatedAt ?: null;
    }
}
