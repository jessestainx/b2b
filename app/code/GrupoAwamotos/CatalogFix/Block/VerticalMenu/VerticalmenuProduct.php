<?php

declare(strict_types=1);

namespace GrupoAwamotos\CatalogFix\Block\VerticalMenu;

use GrupoAwamotos\Theme\Block\VerticalMenu\SafeVerticalmenu;

/**
 * VerticalmenuProduct block — override do vendor Rokanthemes\VerticalMenu\Block\VerticalmenuProduct.
 *
 * Herda cache de categorias (N+1 fix) e escaping XSS de SafeVerticalmenu (GrupoAwamotos_Theme).
 * Diferenças em relação ao menu principal:
 *  - max_level fixo em 2 (sidebar de produto não exibe profundidade desnecessária)
 *  - sem inline style de static width no submenu
 *  - sem banner promocional de fallback
 *  - getSubmenuItemsHtml sem prefixo col-sm- (layout sidebar de produto)
 *
 * @see \Rokanthemes\VerticalMenu\Block\VerticalmenuProduct
 */
class VerticalmenuProduct extends SafeVerticalmenu
{
    public function getSubmenuItemsHtml(
        $children,
        $level = 1,
        $max_level = 0,
        $column_width = 12,
        $menu_type = 'fullwidth',
        $columns = null,
        $parentMenuId = 'root'
    ): string {
        $html     = '';
        $levelInt = (int)$level;
        $maxLvl   = (int)$max_level;

        if (!$maxLvl || $maxLvl - 1 >= $levelInt) {
            $column_class = '';
            if ($levelInt === 1 && $columns && ($menu_type === 'fullwidth' || $menu_type === 'staticwidth')) {
                $column_class = 'mega-columns columns' . (int)$columns;
            }

            $html = '<ul class="subchildmenu ' . htmlspecialchars($column_class, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';

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

                $html .= '<li class="ui-menu-item ' . htmlspecialchars($item_class, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
                if (count($sub_children) > 0) {
                    $html .= '<div class="open-children-toggle"></div>';
                }

                $html .= '<a' . $a_class . ' href="' . htmlspecialchars($this->_categoryHelper->getCategoryUrl($child), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
                if ($font_icon !== '') {
                    $html .= '<em class="menu-thumb-icon ' . htmlspecialchars($font_icon, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"></em>';
                }
                $html .= '<span>' . htmlspecialchars((string)$child->getName(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                if ($cat_label_key !== '' && isset($this->_verticalmenuConfig['cat_labels'][$cat_label_key])) {
                    $label_text = (string)$this->_verticalmenuConfig['cat_labels'][$cat_label_key];
                    $html .= '<span class="cat-label cat-label-' . htmlspecialchars($cat_label_key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
                        . htmlspecialchars($label_text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>';
                }
                $html .= '</span></a>';

                if (count($sub_children) > 0) {
                    $html .= $this->getSubmenuItemsHtml($sub_children, $levelInt + 1, $max_level, $column_width, $menu_type);
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
        $max_level = 2;

        foreach ($categories as $category) {
            if (!$category->getIsActive()) {
                continue;
            }

            $cat_model = $this->getCategoryModel($category->getId());
            if ($cat_model->getData('vc_menu_hide_item')) {
                continue;
            }

            $children       = $this->getActiveChildCategories($category);
            $cat_label_key  = (string)($cat_model->getData('vc_menu_cat_label') ?? '');
            $icon_img_url   = $this->_helper->getVerticalIconimageUrl($cat_model);
            $font_icon      = (string)($cat_model->getData('vc_menu_font_icon') ?? '');
            $cat_columns    = (int)($cat_model->getData('vc_menu_cat_columns') ?: 4);
            $float_type     = (string)($cat_model->getData('vc_menu_float_type') ?? '');
            $menu_type      = (string)($cat_model->getData('vc_menu_type') ?: $this->_verticalmenuConfig['general']['menu_type']);

            $float_class = $float_type !== '' ? 'fl-' . htmlspecialchars($float_type, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ' ' : '';
            $item_class  = 'level0 ' . htmlspecialchars($menu_type, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ' ';

            $menu_left_content  = (string)($cat_model->getData('vc_menu_block_left_content') ?? '');
            $menu_left_width    = ($menu_left_content !== '') ? (int)$cat_model->getData('vc_menu_block_left_width') : 0;
            $menu_right_content = (string)($cat_model->getData('vc_menu_block_right_content') ?? '');
            $menu_right_width   = ($menu_right_content !== '') ? (int)$cat_model->getData('vc_menu_block_right_width') : 0;

            if (count($children) > 0) {
                $item_class .= 'parent ';
            }

            $html .= '<li class="ui-menu-item ' . $item_class . $float_class . '">';
            if (count($children) > 0) {
                $html .= '<div class="open-children-toggle"></div>';
            }

            $html .= '<a href="' . htmlspecialchars($this->_categoryHelper->getCategoryUrl($category), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" class="level-top">';
            if ($icon_img_url) {
                $html .= '<img class="menu-thumb-icon" src="' . htmlspecialchars((string)$icon_img_url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    . '" alt="' . htmlspecialchars((string)$category->getName(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"/>';
            } elseif ($font_icon !== '') {
                $html .= '<em class="menu-thumb-icon ' . htmlspecialchars($font_icon, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"></em>';
            }
            $html .= '<span>' . htmlspecialchars((string)$category->getName(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>';

            if ($cat_label_key !== '' && isset($this->_verticalmenuConfig['cat_labels'][$cat_label_key])) {
                $label_text = (string)$this->_verticalmenuConfig['cat_labels'][$cat_label_key];
                $html .= '<span class="cat-label cat-label-' . htmlspecialchars($cat_label_key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
                    . htmlspecialchars($label_text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>';
            }
            $html .= '</a>';

            if (count($children) > 0) {
                $html .= '<div class="level0 submenu">';
                $html .= $this->getSubmenuItemsHtml(
                    $children,
                    1,
                    $max_level,
                    12 - $menu_left_width - $menu_right_width,
                    $menu_type,
                    $cat_columns
                );
                $html .= '</div>';
            }

            $html .= '</li>';
        }

        return $html;
    }
}
