<?php

declare(strict_types=1);

namespace GrupoAwamotos\AiAssistant\Controller\Adminhtml\Copilot;

use GrupoAwamotos\AiAssistant\Helper\Config;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_AiAssistant::admin_copilot';

    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
        private readonly Config      $config
    ) {
        parent::__construct($context);
    }

    public function execute(): \Magento\Framework\View\Result\Page
    {
        $page = $this->pageFactory->create();
        $page->setActiveMenu('GrupoAwamotos_AiAssistant::copilot');
        $page->getConfig()->getTitle()->prepend(__('Copiloto IA — AWA Motos'));
        return $page;
    }
}
