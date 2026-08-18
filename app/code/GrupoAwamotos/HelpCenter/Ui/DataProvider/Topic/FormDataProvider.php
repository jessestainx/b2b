<?php

declare(strict_types=1);

namespace GrupoAwamotos\HelpCenter\Ui\DataProvider\Topic;

use GrupoAwamotos\HelpCenter\Model\ResourceModel\Topic\CollectionFactory;
use GrupoAwamotos\HelpCenter\Model\TopicRepository;
use Magento\Framework\App\RequestInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;

class FormDataProvider extends AbstractDataProvider
{
    /** @var array<int|string, array<string, mixed>> */
    private array $loadedData = [];

    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        private readonly CollectionFactory $collectionFactory,
        private readonly TopicRepository $repository,
        private readonly RequestInterface $request,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $this->collectionFactory->create();
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    public function getData(): array
    {
        if ($this->loadedData !== []) {
            return $this->loadedData;
        }
        $id = (int) $this->request->getParam('topic_id');
        if ($id) {
            try {
                $topic = $this->repository->getById($id);
                $this->loadedData[$id] = $topic->getData();
            } catch (\Magento\Framework\Exception\NoSuchEntityException) {
                // Return empty for new form
            }
        }
        return $this->loadedData;
    }
}
