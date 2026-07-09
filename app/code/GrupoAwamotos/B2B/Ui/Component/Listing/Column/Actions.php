<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Ui\Component\Listing\Column;

use GrupoAwamotos\B2B\Model\B2bCustomer;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class Actions extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        foreach ($dataSource['data']['items'] as &$item) {
            $id     = $item['b2b_customer_id'];
            $status = (int) ($item['status'] ?? B2bCustomer::STATUS_PENDING);
            $name   = $this->getData('name');

            $item[$name] = [
                'view' => [
                    'href'  => $this->urlBuilder->getUrl('b2b/customer/view', ['id' => $id]),
                    'label' => __('Ver Detalhes'),
                ],
            ];

            if ($status === B2bCustomer::STATUS_PENDING) {
                $item[$name]['approve'] = [
                    'href'    => $this->urlBuilder->getUrl('b2b/customer/approve', ['id' => $id]),
                    'label'   => __('Aprovar'),
                    'confirm' => ['title' => __('Aprovar'), 'message' => __('Confirma aprovação?')],
                ];
                $item[$name]['reject'] = [
                    'href'  => $this->urlBuilder->getUrl('b2b/customer/reject', ['id' => $id]),
                    'label' => __('Rejeitar'),
                    'confirm' => ['title' => __('Rejeitar'), 'message' => __('Confirma rejeição?')],
                ];
            }
        }

        return $dataSource;
    }
}
