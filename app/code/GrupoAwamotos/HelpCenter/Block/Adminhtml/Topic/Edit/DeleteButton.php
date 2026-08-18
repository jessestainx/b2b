<?php

declare(strict_types=1);

namespace GrupoAwamotos\HelpCenter\Block\Adminhtml\Topic\Edit;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

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
        $id = (int) $this->context->getRequest()->getParam('topic_id');
        if (!$id) {
            return [];
        }
        return [
            'label'    => __('Excluir'),
            'class'    => 'delete',
            'on_click' => sprintf(
                "deleteConfirm('%s', '%s')",
                __('Confirma exclusão deste tópico?'),
                $this->context->getUrlBuilder()->getUrl('*/*/delete', ['topic_id' => $id])
            ),
            'sort_order' => 20,
        ];
    }
}
