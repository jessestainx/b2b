<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model;

use GrupoAwamotos\B2B\Api\B2bCustomerRepositoryInterface;
use GrupoAwamotos\B2B\Model\ResourceModel\B2bCustomer as B2bCustomerResource;
use Magento\Framework\Exception\NoSuchEntityException;

class B2bCustomerRepository implements B2bCustomerRepositoryInterface
{
    public function __construct(
        private readonly B2bCustomerFactory $factory,
        private readonly B2bCustomerResource $resource
    ) {
    }

    public function save(B2bCustomer $b2bCustomer): B2bCustomer
    {
        $this->resource->save($b2bCustomer);
        return $b2bCustomer;
    }

    public function getById(int $id): B2bCustomer
    {
        $model = $this->factory->create();
        $this->resource->load($model, $id);

        if (!$model->getId()) {
            throw new NoSuchEntityException(__('Cadastro B2B id=%1 não encontrado.', $id));
        }

        return $model;
    }

    public function getByCustomerId(int $customerId): ?B2bCustomer
    {
        $data = $this->resource->getByCustomerId($customerId);
        if (empty($data)) {
            return null;
        }

        $model = $this->factory->create();
        $this->resource->load($model, $data['b2b_customer_id']);
        return $model;
    }

    public function delete(B2bCustomer $b2bCustomer): void
    {
        $this->resource->delete($b2bCustomer);
    }
}
