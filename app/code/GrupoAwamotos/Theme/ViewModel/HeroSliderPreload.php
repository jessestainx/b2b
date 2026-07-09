<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\ViewModel;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * ViewModel para o bloco de head que injeta preload da imagem hero.
 *
 * @see Magento_Cms/layout/cms_index_index.xml -> awa.hero.preload
 * @see Magento_Theme/templates/html/awa-hero-preload.phtml
 */
class HeroSliderPreload implements ArgumentInterface
{
    private ResourceConnection $resource;
    private StoreManagerInterface $storeManager;

    public function __construct(
        ResourceConnection $resource,
        StoreManagerInterface $storeManager
    ) {
        $this->resource = $resource;
        $this->storeManager = $storeManager;
    }

    /**
     * Retorna URL completa da imagem hero (primeiro slide ativo) ou string vazia.
     */
    public function getHeroImageUrl(): string
    {
        try {
            $conn = $this->resource->getConnection();
            /*
             * JOIN com rokanthemes_slider para garantir que o slide pertence
             * a um slider ativo. Sem o JOIN, sliders inativos com slides na
             * posição 0 seriam retornados antes dos sliders ativos.
             */
            $select = $conn->select()
                ->from(
                    ['s' => $this->resource->getTableName('rokanthemes_slide')],
                    ['slide_image']
                )
                ->join(
                    ['sl' => $this->resource->getTableName('rokanthemes_slider')],
                    'sl.slider_id = s.slider_id AND sl.slider_status = 1',
                    []
                )
                ->where('s.slide_status = ?', 1)
                ->where('s.slide_image IS NOT NULL')
                ->where('s.slide_image != ?', '')
                ->order(['sl.slider_id ASC', 's.slide_position ASC'])
                ->limit(1);

            $slideImage = (string) $conn->fetchOne($select);
            if ($slideImage === '') {
                return '';
            }

            $mediaUrl = $this->storeManager->getStore()->getBaseUrl(
                \Magento\Framework\UrlInterface::URL_TYPE_MEDIA
            );

            return rtrim($mediaUrl, '/') . '/' . ltrim($slideImage, '/');
        } catch (\Throwable $e) {
            return '';
        }
    }
}
