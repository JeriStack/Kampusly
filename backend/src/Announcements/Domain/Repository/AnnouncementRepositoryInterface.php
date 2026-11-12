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

    public function update(Announcement $announcement): Announcement;

    /**
     * Archive an announcement for one student. Archiving twice keeps the original archive time.
     */
    public function archiveForStudent(int $studentId, int $announcementId): void;

    /**
     * Restore an archived announcement for one student.
     * Archives older than the retention window are expired and cannot be restored.
     */
    public function restoreForStudent(int $studentId, int $announcementId, int $retentionDays): bool;
}
