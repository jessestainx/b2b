<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Api;

use GrupoAwamotos\B2B\Api\Data\AttendantInterface;

interface AttendantRepositoryInterface
{
    public function getById(int $id): AttendantInterface;
    public function save(AttendantInterface $attendant): AttendantInterface;
    public function delete(AttendantInterface $attendant): bool;
    public function deleteById(int $id): bool;
}
