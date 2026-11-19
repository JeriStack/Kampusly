<?php

declare(strict_types=1);

namespace Kampusly\Scheduling\Domain\Repository;

use Kampusly\Shared\Application\DTO\PaginatedResult;
use Kampusly\Shared\Domain\ValueObject\PaginationQuery;

interface ScheduleChangeRequestRepositoryInterface
{
    /** @param array<string, mixed> $request */
    public function create(array $request): int;

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array;

    public function hasPendingRequest(int $studentId, int $scheduleId): bool;

    public function paginateForStudent(int $studentId, PaginationQuery $pagination): PaginatedResult;

    public function paginateAll(?string $status, PaginationQuery $pagination): PaginatedResult;

    public function review(int $id, int $reviewerUserId, string $status, ?string $note): bool;
}
