<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Adminhtml\View;

use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\View\Result\PageFactory;

/**
 * PageFactory que sempre instancia Backend Page (setActiveMenu), independente do merge de área DI.
 */
class BackendPageFactory extends PageFactory
{
    public function __construct(ObjectManagerInterface $objectManager)
    {
        parent::__construct($objectManager, Page::class);
    }
}
