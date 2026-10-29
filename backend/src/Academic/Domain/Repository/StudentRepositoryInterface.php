<?php

declare(strict_types=1);

namespace Kampusly\Academic\Domain\Repository;

use Kampusly\Academic\Domain\Entity\StudentProfile;
use Kampusly\Shared\Application\DTO\PaginatedResult;
use Kampusly\Shared\Domain\ValueObject\PaginationQuery;

interface StudentRepositoryInterface
{
    public function findById(int $id): ?StudentProfile;

    public function findByUserId(int $userId): ?StudentProfile;

    public function findByStudentNumber(string $studentNumber): ?StudentProfile;

    public function paginate(
        PaginationQuery $pagination,
        ?int $sectionId = null,
        ?string $status = null,
        ?string $search = null
    ): PaginatedResult;

    public function create(StudentProfile $student): StudentProfile;

    public function update(StudentProfile $student): StudentProfile;

    public function hasSchedules(int $studentId): bool;
}
