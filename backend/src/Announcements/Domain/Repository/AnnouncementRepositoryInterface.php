<?php

declare(strict_types=1);

namespace Kampusly\Announcements\Domain\Repository;

use Kampusly\Announcements\Domain\Entity\Announcement;
use Kampusly\Shared\Application\DTO\PaginatedResult;
use Kampusly\Shared\Domain\ValueObject\PaginationQuery;

interface AnnouncementRepositoryInterface
{
    public function findById(int $id): ?Announcement;

    /**
     * @param array<string, mixed> $filters
     */
    public function paginate(PaginationQuery $pagination, array $filters = []): PaginatedResult;

    public function create(Announcement $announcement): Announcement;
}

