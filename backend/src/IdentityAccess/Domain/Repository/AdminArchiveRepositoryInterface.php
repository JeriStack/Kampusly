<?php

declare(strict_types=1);

namespace Kampusly\IdentityAccess\Domain\Repository;

use Kampusly\Shared\Application\DTO\PaginatedResult;
use Kampusly\Shared\Domain\ValueObject\PaginationQuery;

interface AdminArchiveRepositoryInterface
{
    /**
     * @return array{all: int, users: int, subjects: int, rooms: int, sections: int, schedules: int}
     */
    public function countArchivedByType(): array;

    public function paginateArchived(
        PaginationQuery $pagination,
        ?string $type = null,
        ?string $search = null
    ): PaginatedResult;

    /**
     * @return array<string, mixed>|null
     */
    public function findRecordSummary(string $type, int $id): ?array;

    public function markArchived(string $type, int $id): void;

    public function markRestored(string $type, int $id): void;

    /**
     * Return a human-readable block reason if the archived record cannot be restored
     * (e.g. parent entity is still archived or restoring a schedule causes a conflict),
     * or null when restoration is allowed.
     */
    public function getRestoreBlockReason(string $type, int $id): ?string;

    /**
     * Return a human-readable block reason if the archived record is still referenced
     * by dependent records and cannot be permanently deleted, or null when safe.
     */
    public function getPermanentDeleteBlockReason(string $type, int $id): ?string;

    public function permanentlyDelete(string $type, int $id): void;
}
