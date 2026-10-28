<?php

declare(strict_types=1);

namespace Kampusly\Scheduling\Domain\Repository;

use Kampusly\Academic\Domain\ValueObject\Semester;
use Kampusly\Scheduling\Domain\Entity\Schedule;
use Kampusly\Scheduling\Domain\ValueObject\DayOfWeek;
use Kampusly\Shared\Application\DTO\PaginatedResult;
use Kampusly\Shared\Domain\ValueObject\PaginationQuery;

interface ScheduleRepositoryInterface
{
    public function findById(int $id): ?Schedule;

    /**
     * Fetch non-cancelled schedules for the given academic term and day of week.
     *
     * @return list<Schedule>
     */
    public function findActiveByTermAndDay(
        string $schoolYear,
        Semester $semester,
        DayOfWeek $dayOfWeek,
        ?int $excludeScheduleId = null
    ): array;

    /**
     * @param array<string, mixed> $filters
     */
    public function paginate(PaginationQuery $pagination, array $filters = []): PaginatedResult;

    public function create(Schedule $schedule): Schedule;
}

