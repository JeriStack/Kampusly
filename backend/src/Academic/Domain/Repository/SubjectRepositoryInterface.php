<?php

declare(strict_types=1);

namespace Kampusly\Academic\Domain\Repository;

use Kampusly\Academic\Domain\Entity\Subject;
use Kampusly\Shared\Application\DTO\PaginatedResult;
use Kampusly\Shared\Domain\ValueObject\PaginationQuery;

interface SubjectRepositoryInterface
{
    public function findById(int $id): ?Subject;

    public function findByCode(string $subjectCode): ?Subject;

    public function paginate(
        PaginationQuery $pagination,
        ?string $status = null,
        ?string $search = null
    ): PaginatedResult;

    public function create(Subject $subject): Subject;
}

