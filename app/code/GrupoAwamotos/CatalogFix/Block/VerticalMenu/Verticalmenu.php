<?php

declare(strict_types=1);

namespace GrupoAwamotos\CatalogFix\Block\VerticalMenu;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;

/**
 * VerticalMenu block — override do vendor Rokanthemes\VerticalMenu\Block\Verticalmenu.
 *
 * Correções aplicadas:
 *  [C1/C2] XSS: nomes de categoria, font icon class e static width eram concatenados
 *           em HTML/CSS sem escaping. Todos os valores controlados pelo usuário agora
 *           passam por htmlspecialchars() via self::e().
 *  [C4]    N+1 queries: getCategoryModel() disparava um SELECT por categoria.
 *           Um único preloadCategoryCache() carrega todos os atributos vc_menu_*
 *           em uma só query de coleção antes do loop.
 *
 * @see \Rokanthemes\VerticalMenu\Block\Verticalmenu
 */
class Verticalmenu extends \Rokanthemes\VerticalMenu\Block\Verticalmenu
{
    /** @var array<int, Category> */
    private array $categoryCache = [];
    private bool $categoryCacheLoaded = false;
    private CategoryCollectionFactory $categoryCollectionFactory;

    private const VC_MENU_ATTRIBUTES = [
        'vc_menu_hide_item',
        'vc_menu_type',
        'vc_menu_static_width',
        'vc_menu_cat_columns',
        'vc_menu_float_type',
        'vc_menu_cat_label',
        'vc_menu_icon_img',
        'vc_menu_font_icon',
        'vc_menu_block_top_content',
        'vc_menu_block_left_width',
        'vc_menu_block_left_content',
        'vc_menu_block_right_width',
        'vc_menu_block_right_content',
        'vc_menu_block_bottom_content',
        'image',
        'name',
        'is_active',
        'url_key',
        'url_path',
    ];

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Catalog\Helper\Category $categoryHelper,
        \Rokanthemes\VerticalMenu\Helper\Data $helper,
        \Magento\Catalog\Model\Indexer\Category\Flat\State $categoryFlatState,
        \Magento\Catalog\Model\CategoryFactory $categoryFactory,
        \Magento\Theme\Block\Html\Topmenu $topMenu,
        \Magento\Cms\Model\Template\FilterProvider $filterProvider,
        \Magento\Cms\Model\BlockFactory $blockFactory,
        CategoryCollectionFactory $categoryCollectionFactory
    ) {
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        parent::__construct(
            $context,
            $categoryHelper,
            $helper,
            $categoryFlatState,
            $categoryFactory,
            $topMenu,
            $filterProvider,
            $blockFactory
        );
    }

    private function preloadCategoryCache(): void
    {
        if ($this->categoryCacheLoaded) {
            return;
        }
        $storeId = (int)$this->_storeManager->getStore()->getId();
        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToSelect(self::VC_MENU_ATTRIBUTES);
        $collection->setStoreId($storeId);
        foreach ($collection as $category) {
            $this->categoryCache[(int)$category->getId()] = $category;
        }
        $this->categoryCacheLoaded = true;
    }

    public function getCategoryModel($id): Category
    {
        $this->preloadCategoryCache();
        $intId = (int)$id;
        if (!isset($this->categoryCache[$intId])) {
            $cat = $this->_categoryFactory->create();
            $cat->load($intId);
            $this->categoryCache[$intId] = $cat;
        }
        return $this->categoryCache[$intId];
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function sanitizeCssWidth(string $value): string
    {
        return preg_match('/^\d+(\.\d+)?(px|%|em|rem|vw|vh)$/', trim($value))
            ? trim($value)
            : '';
    }

    public function getSubmenuItemsHtml(
        $children,
        $level = 1,
        $max_level = 0,
        $column_width = 12,
        $menu_type = 'fullwidth',
        $columns = null
    ): string {
        $html     = '';
        $levelInt = (int)$level;
        $maxLvl   = (int)$max_level;

        if (!$maxLvl || $maxLvl - 1 >= $levelInt) {
            $column_class = '';
            if ($levelInt === 1 && $columns && ($menu_type === 'fullwidth' || $menu_type === 'staticwidth')) {
                $column_class  = 'col-sm-' . (int)$column_width . ' ';
                $column_class .= 'mega-columns columns' . (int)$columns;
            }

            $html = '<ul class="subchildmenu ' . self::e($column_class) . '">';

            foreach ($children as $child) {
                $cat_model = $this->getCategoryModel($child->getId());
                if ($cat_model->getData('vc_menu_hide_item')) {
                    continue;
                }

                $sub_children  = $this->getActiveChildCategories($child);
                $cat_label_key = (string)($cat_model->getData('vc_menu_cat_label') ?? '');
                $font_icon     = (string)($cat_model->getData('vc_menu_font_icon') ?? '');

                $item_class = 'level' . $levelInt . ' ';
                if (count($sub_children) > 0) {
                    $item_class .= 'parent ';
                }
                $a_class = '';
                if ($levelInt === 1 && ($menu_type === 'fullwidth' || $menu_type === 'staticwidth')) {
                    $a_class     = ' class="title-cat-mega-menu"';
                    $item_class .= 'parent-ul-cat-mega-menu';
                }

                $html .= '<li class="ui-menu-item ' . self::e($item_class) . '">';
                if (count($sub_children) > 0) {
                    $html .= '<div class="open-children-toggle"></div>';
                }

                $html .= '<a' . $a_class . ' href="' . self::e($this->_categoryHelper->getCategoryUrl($child)) . '">';
                if ($font_icon !== '') {
                    $html .= '<em class="menu-thumb-icon ' . self::e($font_icon) . '"></em>';
                }
                $html .= '<span>' . self::e((string)$child->getName());
                if ($cat_label_key !== '' && isset($this->_verticalmenuConfig['cat_labels'][$cat_label_key])) {
                    $label_text = (string)$this->_verticalmenuConfig['cat_labels'][$cat_label_key];
                    $html .= '<span class="cat-label cat-label-' . self::e($cat_label_key) . '">'
                        . self::e($label_text) . '</span>';
                }
                $html .= '</span></a>';

                if (count($sub_children) > 0) {
                    $html .= $this->getSubmenuItemsHtml(
                        $sub_children,
                        $levelInt + 1,
                        $max_level,
                        $column_width,
                        $menu_type
                    );
                }
                $html .= '</li>';
            }

            $html .= '</ul>';
        }

        return $html;
    }

    public function getVerticalMenuHtml(): string
    {
        $html       = '';
        $categories = $this->getStoreCategories(true, false, true);

        $this->_verticalmenuConfig = $this->_helper->getConfig('verticalmenu');
        $max_level = (int)($this->_verticalmenuConfig['general']['max_level'] ?? 0);

        $html .= $this->getCustomBlockHtml('before');

        foreach ($categories as $category) {
            if (!$category->getIsActive()) {
                continue;
            }

            $cat_model = $this->getCategoryModel($category->getId());
            if ($cat_model->getData('vc_menu_hide_item')) {
                continue;
            }

            $children        = $this->getActiveChildCategories($category);
            $cat_label_key   = (string)($cat_model->getData('vc_menu_cat_label') ?? '');
            $icon_img_url    = $this->_helper->getVerticalIconimageUrl($cat_model);
            $font_icon       = (string)($cat_model->getData('vc_menu_font_icon') ?? '');
            $cat_columns     = (int)($cat_model->getData('vc_menu_cat_columns') ?: 4);
            $float_type      = (string)($cat_model->getData('vc_menu_float_type') ?? '');
            $menu_type       = (string)($cat_model->getData('vc_menu_type') ?: $this->_verticalmenuConfig['general']['menu_type']);

            $custom_style = '';
            if ($menu_type === 'staticwidth') {
                $raw_width  = (string)($cat_model->getData('vc_menu_static_width') ?: '500px');
                $safe_width = self::sanitizeCssWidth($raw_width) ?: '500px';
                $custom_style = ' style="width: ' . $safe_width . ';"';
            }

            $float_class = $float_type !== '' ? 'fl-' . self::e($float_type) . ' ' : '';
            $item_class  = 'level0 ' . self::e($menu_type) . ' ';

            $menu_top_content    = (string)($cat_model->getData('vc_menu_block_top_content') ?? '');
            $menu_left_content   = (string)($cat_model->getData('vc_menu_block_left_content') ?? '');
            $menu_left_width     = ($menu_left_content !== '') ? (int)$cat_model->getData('vc_menu_block_left_width') : 0;
            $menu_right_content  = (string)($cat_model->getData('vc_menu_block_right_content') ?? '');
            $menu_right_width    = ($menu_right_content !== '') ? (int)$cat_model->getData('vc_menu_block_right_width') : 0;
            $menu_bottom_content = (string)($cat_model->getData('vc_menu_block_bottom_content') ?? '');

            $isMega = ($menu_type === 'fullwidth' || $menu_type === 'staticwidth');
            $has_submenu = count($children) > 0
                || ($isMega && ($menu_top_content || $menu_left_content || $menu_right_content || $menu_bottom_content));

            if ($has_submenu) {
                $item_class .= 'parent ';
            }

            $html .= '<li class="ui-menu-item ' . $item_class . $float_class . '">';
            if (count($children) > 0) {
                $html .= '<div class="open-children-toggle"></div>';
            }

            $html .= '<a href="' . self::e($this->_categoryHelper->getCategoryUrl($category)) . '" class="level-top">';
            if ($icon_img_url) {
                $html .= '<img class="menu-thumb-icon" src="' . self::e((string)$icon_img_url)
                    . '" alt="' . self::e((string)$category->getName()) . '"/>';
            } elseif ($font_icon !== '') {
                $html .= '<em class="menu-thumb-icon ' . self::e($font_icon) . '"></em>';
            }
            $html .= '<span>' . self::e((string)$category->getName()) . '</span>';

            if ($cat_label_key !== '' && isset($this->_verticalmenuConfig['cat_labels'][$cat_label_key])) {
                $label_text = (string)$this->_verticalmenuConfig['cat_labels'][$cat_label_key];
                $html .= '<span class="cat-label cat-label-' . self::e($cat_label_key) . '">'
                    . self::e($label_text) . '</span>';
            }
            $html .= '</a>';

            if ($has_submenu) {
                $html .= '<div class="level0 submenu"' . $custom_style . '>';

                if ($isMega && $menu_top_content !== '') {
                    $html .= '<div class="menu-top-block">' . $this->getBlockContent($menu_top_content) . '</div>';
                }

                if (count($children) > 0 || ($isMega && ($menu_left_content || $menu_right_content))) {
                    $html .= '<div class="row">';
                    if ($isMega && $menu_left_content !== '' && $menu_left_width > 0) {
                        $html .= '<div class="menu-left-block col-sm-' . $menu_left_width . '">'
                            . $this->getBlockContent($menu_left_content) . '</div>';
                    }
                    $html .= $this->getSubmenuItemsHtml(
                        $children,
                        1,
                        $max_level,
                        12 - $menu_left_width - $menu_right_width,
                        $menu_type,
                        $cat_columns
                    );
                    if ($isMega && $menu_right_content !== '' && $menu_right_width > 0) {
                        $html .= '<div class="menu-right-block col-sm-' . $menu_right_width . '">'
                            . $this->getBlockContent($menu_right_content) . '</div>';
                    }
                    $html .= '</div>';
                }

                if ($isMega && $menu_bottom_content !== '') {
                    $html .= '<div class="menu-bottom-block">' . $this->getBlockContent($menu_bottom_content) . '</div>';
                } elseif ($isMega && count($children) > 0) {
                    $catImage = $cat_model->getData('image');
                    if ($catImage) {
                        try {
                            $catImageUrl = (string)$cat_model->getImageUrl('image');
                        } catch (\Exception $e) {
                            $mediaUrl = $this->_storeManager->getStore()->getBaseUrl(
                                \Magento\Framework\UrlInterface::URL_TYPE_MEDIA
                            );
                            $catImageUrl = $mediaUrl . 'catalog/category/' . $catImage;
                        }
                        $html .= '<div class="menu-bottom-block menu-bottom-block-auto">';
                        $html .= '<a href="' . self::e($this->_categoryHelper->getCategoryUrl($category))
                            . '" class="menu-category-banner">';
                        $html .= '<img loading="lazy" src="' . self::e($catImageUrl)
                            . '" alt="' . self::e((string)$category->getName()) . '" />';
                        $html .= '</a></div>';
                    }
                }

                $html .= '</div>';
            }

            $html .= '</li>';
        }

        $html .= $this->getCustomBlockHtml('after');

        return $html;
    }
}
