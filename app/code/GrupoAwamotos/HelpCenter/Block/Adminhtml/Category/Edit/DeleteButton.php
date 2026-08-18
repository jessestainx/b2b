<?php

declare(strict_types=1);

namespace GrupoAwamotos\HelpCenter\Block\Adminhtml\Category\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Magento\Backend\Block\Widget\Context;

class DeleteButton implements ButtonProviderInterface
{
    public function __construct(private readonly Context $context)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        $id = (int) $this->context->getRequest()->getParam('category_id');
        if (!$id) {
            return [];
        }
        return [
            'label'    => __('Excluir'),
            'class'    => 'delete',
            'on_click' => sprintf(
                "deleteConfirm('%s', '%s')",
                __('Confirma exclusão desta categoria?'),
                $this->context->getUrlBuilder()->getUrl('*/*/delete', ['category_id' => $id])
            ),
            'sort_order' => 20,
        ];
    }
}
