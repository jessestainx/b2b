<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Subscription;

use Magento\Customer\Controller\AbstractAccount;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;

class Edit extends AbstractAccount implements HttpGetActionInterface
{
    public function execute(): Redirect
    {
        $this->messageManager->addNoticeMessage(
            __('A edição direta de assinatura será disponibilizada em breve. Você pode remover e criar uma nova assinatura por enquanto.')
        );

        return $this->resultRedirectFactory->create()->setPath('b2b/subscription/index');
    }
}
