<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\Block\Html;

use GrupoAwamotos\Theme\ViewModel\HeroResponsiveImage;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Bloco de head: injeta <link rel="preload" as="image" fetchpriority="high">
 * para a imagem hero do primeiro slide ativo.
 *
 * Renderiza ANTES do <body>, garantindo que o browser descubra a imagem
 * imediatamente e possa resolver o LCP.
 *
 * Links: awa-hero-preload-links.phtml (início de head.additional).
 * CSS crítico: awa-hero-preload.phtml (fim de head.additional).
 *
 * Registrado em cms_index_index.xml no container head.additional.
 */
class HeroPreload extends Template
{
    private ResourceConnection $resource;
    private StoreManagerInterface $storeManager;
    private HeroResponsiveImage $heroResponsiveImage;
    private LoggerInterface $logger;

    /** @var array<string, bool> Dedupe por request — um aviso por arquivo ausente. */
    private array $missingCssLogged = [];

    public function __construct(
        Context $context,
        ResourceConnection $resource,
        StoreManagerInterface $storeManager,
        HeroResponsiveImage $heroResponsiveImage,
        LoggerInterface $logger,
        array $data = []
    ) {
        $this->resource = $resource;
        $this->storeManager = $storeManager;
        $this->heroResponsiveImage = $heroResponsiveImage;
        $this->logger = $logger;
        parent::__construct($context, $data);
    }

    /**
     * Registra aviso quando um CSS referenciado pelo head-preload não existe
     * em disco (rename/mova silencioso). Antes deste log o link era omitido
     * sem nenhum rastro — ver scripts/check-awa-css-refs.sh.
     */
    public function logMissingDeferredCss(string $url): void
    {
        if (isset($this->missingCssLogged[$url])) {
            return;
        }
        $this->missingCssLogged[$url] = true;
        $this->logger->warning(
            'AWA CSS ausente — link omitido do head (arquivo não encontrado em web/css nem pub/static): ' . $url
        );
    }

    /**
     * Retorna a URL completa da imagem hero desktop (preload 1920w) ou string vazia.
     */
    public function getHeroImageUrl(): string
    {
        $slideImage = $this->fetchFirstSlideImagePath(false);
        if ($slideImage === '') {
            return '';
        }

        return $this->heroResponsiveImage->getPreloadUrl($slideImage, false);
    }

    /**
     * Retorna a URL da variante mobile (~768w) para preload LCP.
     */
    public function getHeroImageMobileUrl(): string
    {
        $slideImage = $this->fetchFirstSlideImagePath(true);
        if ($slideImage === '') {
            return '';
        }

        return $this->heroResponsiveImage->getPreloadUrl($slideImage, true);
    }

    public function getHeroImageSrcset(): string
    {
        $slideImage = $this->fetchFirstSlideImagePath(false);

        return $slideImage !== '' ? $this->heroResponsiveImage->buildSrcset($slideImage) : '';
    }

    public function getHeroImageMobileSrcset(): string
    {
        $slideImage = $this->fetchFirstSlideImagePath(true);

        return $slideImage !== '' ? $this->heroResponsiveImage->buildSrcset($slideImage) : '';
    }

    public function getHeroImageSizes(): string
    {
        return $this->heroResponsiveImage->getSizesAttribute(false);
    }

    public function getHeroImageMobileSizes(): string
    {
        return $this->heroResponsiveImage->getSizesAttribute(true);
    }

    private function fetchFirstSlideImagePath(bool $preferMobile): string
    {
        try {
            $conn = $this->resource->getConnection();
            /*
             * JOIN com rokanthemes_slider para garantir que o slide preloaded
             * pertence ao slider principal (status=1). Sem o JOIN, slides de
             * sliders mobile ou inativos com position=0 eram retornados primeiro,
             * gerando um <link rel="preload"> para a imagem errada.
             */
            $select = $conn->select()
                ->from(
                    ['s' => $this->resource->getTableName('rokanthemes_slide')],
                    ['slide_image_mobile', 'slide_image']
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

            $row = $conn->fetchRow($select);
            if (empty($row)) {
                return '';
            }

            if ($preferMobile && !empty($row['slide_image_mobile'])) {
                return (string) $row['slide_image_mobile'];
            }

            return (string) $row['slide_image'];
        } catch (\Throwable $e) {
            return '';
        }
    }
}
