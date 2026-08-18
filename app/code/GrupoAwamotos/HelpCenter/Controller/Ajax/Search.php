<?php

declare(strict_types=1);

namespace GrupoAwamotos\HelpCenter\Controller\Ajax;

use GrupoAwamotos\HelpCenter\Api\Data\CategoryInterface;
use GrupoAwamotos\HelpCenter\Api\TopicRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\UrlInterface;

class Search extends Action implements HttpGetActionInterface
{
    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly TopicRepositoryInterface $repository,
        private readonly CustomerSession $customerSession,
        private readonly SerializerInterface $serializer,
        private readonly UrlInterface $urlBuilder,
    ) {
        parent::__construct($context);
    }

    public function execute(): \Magento\Framework\Controller\Result\Json
    {
        $query = trim((string) $this->getRequest()->getParam('q', ''));
        $result = $this->jsonFactory->create();

        if (mb_strlen($query) < 2) {
            return $result->setData(['topics' => [], 'query' => $query]);
        }

        $audiences = $this->resolveAudiences();
        $topics    = $this->repository->search($query, $audiences, 8);

        $items = [];
        foreach ($topics as $topic) {
            $items[] = [
                'id'       => $topic->getTopicId(),
                'title'    => $topic->getTitle(),
                'summary'  => $topic->getSummary(),
                'audience' => $topic->getAudience(),
                'url'      => $this->urlBuilder->getUrl(
                    'ajuda',
                    ['_fragment' => 'topico-' . $topic->getTopicId()]
                ),
            ];
        }

        return $result->setData(['topics' => $items, 'query' => $query]);
    }

    /**
     * @return string[]
     */
    private function resolveAudiences(): array
    {
        $base = [CategoryInterface::AUDIENCE_ALL];
        if ($this->customerSession->isLoggedIn()) {
            $base[] = CategoryInterface::AUDIENCE_CUSTOMER;
            if ($this->customerSession->getCustomer()->getData('b2b_approval_status') === 'approved') {
                $base[] = CategoryInterface::AUDIENCE_B2B;
            }
        }
        return $base;
    }
}
