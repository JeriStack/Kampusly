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
     * Archive an announcement for everyone: it is hidden from students and kept only until the
     * retention window ends. The original published_at is preserved.
     */
    public function archive(int $id): void;

    /**
     * Restore an archived announcement to published. Expired archives cannot be restored.
     */
    public function restore(int $id, int $retentionDays): bool;

    /**
     * Permanently delete archived announcements older than the retention window.
     *
     * @return int Number of deleted announcements
     */
    public function purgeExpiredArchived(int $retentionDays): int;

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
