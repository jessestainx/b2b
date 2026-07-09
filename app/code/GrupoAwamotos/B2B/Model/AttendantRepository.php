<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model;

use GrupoAwamotos\B2B\Api\AttendantRepositoryInterface;
use GrupoAwamotos\B2B\Api\Data\AttendantInterface;
use GrupoAwamotos\B2B\Model\ResourceModel\Attendant as AttendantResource;
use Magento\Framework\Exception\NoSuchEntityException;

class AttendantRepository implements AttendantRepositoryInterface
{
    public function __construct(
        private readonly AttendantFactory $attendantFactory,
        private readonly AttendantResource $attendantResource
    ) {
    }

    public function getById(int $id): AttendantInterface
    {
        $attendant = $this->attendantFactory->create();
        $this->attendantResource->load($attendant, $id);
        if (!$attendant->getId()) {
            throw new NoSuchEntityException(__("Atendente #%1 não encontrado.", $id));
        }
        return $attendant;
    }

    public function save(AttendantInterface $attendant): AttendantInterface
    {
        $this->attendantResource->save($attendant);
        return $attendant;
    }

    public function delete(AttendantInterface $attendant): bool
    {
        $this->attendantResource->delete($attendant);
        return true;
    }

    public function deleteById(int $id): bool
    {
        return $this->delete($this->getById($id));
    }
}
