<?php

/**
 * Redireciona atendentes para o Meu Painel após login no admin.
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin\Admin;

use GrupoAwamotos\B2B\Helper\CurrentAttendant;
use Magento\Backend\Controller\Adminhtml\Auth\Login;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;

class AttendantLoginRedirectPlugin
{
    public function __construct(
        private readonly CurrentAttendant $currentAttendant
    ) {
    }

    /**
     * Após login bem-sucedido, redireciona atendentes para o painel pessoal.
     *
     * @param Login $subject
     * @param ResultInterface $result
     * @return ResultInterface
     */
    public function afterExecute(Login $subject, ResultInterface $result): ResultInterface
    {
        if ($result instanceof Redirect && $this->currentAttendant->isAttendant()) {
            $result->setPath('awa_commercial/commercialdashboard/index');
        }

        return $result;
    }
}
