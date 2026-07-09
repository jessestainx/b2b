<?php

declare(strict_types=1);

namespace GrupoAwamotos\RexisML\Block\Email;

use Magento\Framework\View\Element\Template;

class CrossSellOpportunities extends Template
{
    /**
     * Get opportunities data
     *
     * @return array
     */
    public function getOpportunities()
    {
        return $this->getData('opportunities') ?: [];
    }
}
