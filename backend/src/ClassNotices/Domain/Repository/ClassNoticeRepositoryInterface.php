<?php

declare(strict_types=1);

namespace Kampusly\ClassNotices\Domain\Repository;

use Kampusly\ClassNotices\Domain\Entity\ClassNotice;
use Kampusly\Shared\Application\DTO\PaginatedResult;
use Kampusly\Shared\Domain\ValueObject\PaginationQuery;

interface ClassNoticeRepositoryInterface
{
    public function findById(int $id): ?ClassNotice;

    /**
     * @param array<string, mixed> $filters
     */
    public function paginate(PaginationQuery $pagination, array $filters = []): PaginatedResult;

    public function create(ClassNotice $notice): ClassNotice;
}

