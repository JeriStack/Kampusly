<?php

declare(strict_types=1);

namespace Kampusly\Academic\Domain\Repository;

use Kampusly\Academic\Domain\Entity\Section;
use Kampusly\Shared\Application\DTO\PaginatedResult;
use Kampusly\Shared\Domain\ValueObject\PaginationQuery;

interface SectionRepositoryInterface
{
    public function findById(int $id): ?Section;

    public function paginate(
        PaginationQuery $pagination,
        ?string $schoolYear = null,
        ?string $semester = null,
        ?string $status = null,
        ?string $search = null
    ): PaginatedResult;

    public function create(Section $section): Section;
}

