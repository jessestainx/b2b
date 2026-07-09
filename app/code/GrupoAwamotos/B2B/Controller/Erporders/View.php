<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Erporders;

use Magento\Customer\Controller\AbstractAccount;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\View\Result\PageFactory;

class View extends AbstractAccount implements HttpGetActionInterface
{
    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\View\Result\Page|Redirect
     */
    public function execute()
    {
        $orderId = (int) $this->getRequest()->getParam('id');
        if ($orderId <= 0) {
            return $this->resultRedirectFactory->create()->setPath('b2b/erporders/index');
        }

        return $this->resultPageFactory->create();
    }
}
