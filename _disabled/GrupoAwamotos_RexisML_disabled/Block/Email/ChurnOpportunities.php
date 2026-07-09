<?php

declare(strict_types=1);

/**
 * Block para renderizar oportunidades de churn em emails
 */

namespace GrupoAwamotos\RexisML\Block\Email;

use Magento\Framework\View\Element\Template;

class ChurnOpportunities extends Template
{
    /**
     * Get opportunities data
     * Supports both setData('opportunities') from email template directive
     * and setOpportunities() for programmatic usage
     *
     * @return array
     */
    public function getOpportunities()
    {
        return $this->getData('opportunities') ?: [];
    }
}
