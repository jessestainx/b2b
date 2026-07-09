<?php

/**
 * Widget de Recomendações para CMS
 */

declare(strict_types=1);

namespace GrupoAwamotos\ProductIntelligence\Block\Widget;

use Magento\Framework\View\Element\Template;
use Magento\Widget\Block\BlockInterface;

class Recommendations extends \GrupoAwamotos\ProductIntelligence\Block\Recommendations implements BlockInterface
{
    protected $_template = 'GrupoAwamotos_ProductIntelligence::widget/recommendations.phtml';

    /**
     * Get widget title
     *
     * @return string
     */
    public function getWidgetTitle()
    {
        return $this->getData('widget_title') ?: $this->getTitle();
    }

    /**
     * Get limit from widget configuration
     *
     * @return int
     */
    public function getLimit()
    {
        return $this->getData('limit') ?: 4;
    }

    /**
     * Get classificacao from widget configuration
     *
     * @return string|null
     */
    public function getClassificacao()
    {
        return $this->getData('classificacao');
    }

    /**
     * Check if widget should display even if no customer logged in
     *
     * @return bool
     */
    public function getShowForGuest()
    {
        return (bool)$this->getData('show_for_guest');
    }

    /**
     * Get custom CSS class
     *
     * @return string
     */
    public function getCustomClass()
    {
        return $this->getData('custom_class') ?: '';
    }
}
