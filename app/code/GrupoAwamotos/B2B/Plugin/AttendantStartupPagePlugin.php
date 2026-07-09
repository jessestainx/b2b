<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin;

use GrupoAwamotos\B2B\Helper\CurrentAttendant;
use Magento\Backend\Model\Url as BackendUrl;

/**
 * Redireciona atendentes para o dashboard AWA Comercial após login no admin.
 */
class AttendantStartupPagePlugin
{
    public function __construct(
        private readonly CurrentAttendant $currentAttendant
    ) {
    }

    public function afterGetStartupPageUrl(BackendUrl $subject, string $result): string
    {
        if (!$this->currentAttendant->isAttendant()) {
            return $result;
        }

        return $subject->getUrl('awa_commercial/commercialdashboard/index');
    }
}
