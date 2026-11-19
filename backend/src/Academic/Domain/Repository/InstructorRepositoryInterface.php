<?php

declare(strict_types=1);

namespace Kampusly\Academic\Domain\Repository;

use Kampusly\Academic\Domain\Entity\InstructorProfile;
use Kampusly\Shared\Application\DTO\PaginatedResult;
use Kampusly\Shared\Domain\ValueObject\PaginationQuery;

interface InstructorRepositoryInterface
{
    public function findById(int $id): ?InstructorProfile;

    public function findByUserId(int $userId): ?InstructorProfile;

    public function findByEmployeeNumber(string $employeeNumber): ?InstructorProfile;

    public function paginate(
        PaginationQuery $pagination,
        ?string $department = null,
        ?string $status = null,
        ?string $search = null
    ): PaginatedResult;

    public function create(InstructorProfile $instructor): InstructorProfile;

    public function update(InstructorProfile $instructor): InstructorProfile;

    public function hasSchedules(int $instructorId): bool;
}
