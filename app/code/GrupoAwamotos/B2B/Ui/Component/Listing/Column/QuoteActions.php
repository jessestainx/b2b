<?php

/**
 * Quote Actions Column
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class QuoteActions extends Column
{
    public const URL_PATH_VIEW = 'grupoawamotos_b2b/quote/view';
    public const URL_PATH_RESPOND = 'grupoawamotos_b2b/quote/respond';

    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        $this->urlBuilder = $urlBuilder;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                if (isset($item['request_id'])) {
                    $item[$this->getData('name')] = [
                        'view' => [
                            'href' => $this->urlBuilder->getUrl(
                                self::URL_PATH_VIEW,
                                ['id' => $item['request_id']]
                            ),
                            'label' => __('Ver'),
                        ],
                    ];

                    $status = $item['status'] ?? '';

                    if (in_array($status, ['pending', 'processing'])) {
                        $item[$this->getData('name')]['respond'] = [
                            'href' => $this->urlBuilder->getUrl(
                                self::URL_PATH_RESPOND,
                                ['id' => $item['request_id']]
                            ),
                            'label' => __('Responder'),
                        ];
                    }
                }
            }
        }

        return $dataSource;
    }
}
