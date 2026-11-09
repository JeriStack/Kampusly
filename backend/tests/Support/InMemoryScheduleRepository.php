<?php

declare(strict_types=1);

namespace Kampusly\Tests\Support;

use Kampusly\Academic\Domain\ValueObject\Semester;
use Kampusly\Scheduling\Domain\Entity\Schedule;
use Kampusly\Scheduling\Domain\Repository\ScheduleRepositoryInterface;
use Kampusly\Scheduling\Domain\ValueObject\DayOfWeek;
use Kampusly\Shared\Application\DTO\PaginatedResult;
use Kampusly\Shared\Domain\ValueObject\PaginationQuery;

final class InMemoryScheduleRepository implements ScheduleRepositoryInterface
{
    /** @var list<Schedule> */
    private array $schedules = [];
    private int $autoIncrement = 1;

    /**
     * @param list<Schedule> $initialSchedules
     */
    public function __construct(array $initialSchedules = [])
    {
        foreach ($initialSchedules as $schedule) {
            $this->create($schedule);
        }
    }

    public function findById(int $id): ?Schedule
    {
        foreach ($this->schedules as $schedule) {
            if ($schedule->getId() === $id) {
                return $schedule;
            }
        }

        return null;
    }

    public function findActiveByTermAndDay(
        string $schoolYear,
        Semester $semester,
        DayOfWeek $dayOfWeek,
        ?int $excludeScheduleId = null
    ): array {
        $matches = [];
        foreach ($this->schedules as $schedule) {
            if ($excludeScheduleId !== null && $schedule->getId() === $excludeScheduleId) {
                continue;
            }
            if ($schedule->isCancelled()) {
                continue;
            }
            if ($schedule->getSchoolYear() === trim($schoolYear)
                && $schedule->getSemester() === $semester
                && $schedule->getDayOfWeek() === $dayOfWeek
            ) {
                $matches[] = $schedule;
            }
        }

        return $matches;
    }

    public function paginate(PaginationQuery $pagination, array $filters = []): PaginatedResult
    {
        $filtered = array_filter($this->schedules, static function (Schedule $s) use ($filters): bool {
            if (isset($filters['school_year']) && $s->getSchoolYear() !== $filters['school_year']) {
                return false;
            }
            if (isset($filters['semester']) && $s->getSemester()->value !== $filters['semester']) {
                return false;
            }
            if (isset($filters['day_of_week']) && $s->getDayOfWeek()->value !== $filters['day_of_week']) {
                return false;
            }
            if (isset($filters['section_id']) && $s->getSectionId() !== $filters['section_id']) {
                return false;
            }
            if (isset($filters['instructor_id']) && $s->getInstructorId() !== $filters['instructor_id']) {
                return false;
            }
            if (isset($filters['room_id']) && $s->getRoomId() !== $filters['room_id']) {
                return false;
            }
            if (isset($filters['status']) && $s->getStatus()->value !== $filters['status']) {
                return false;
            }
            return true;
        });

        $items = array_map(static fn (Schedule $s): array => $s->toArray(), array_values($filtered));
        $total = count($items);
        $slice = array_slice($items, $pagination->getOffset(), $pagination->getPerPage());

        return new PaginatedResult($slice, $pagination->getPage(), $pagination->getPerPage(), $total);
    }

    public function create(Schedule $schedule): Schedule
    {
        $id = $schedule->getId() ?? $this->autoIncrement++;
        if ($id >= $this->autoIncrement) {
            $this->autoIncrement = $id + 1;
        }
        $persisted = new Schedule(
            $id,
            $schedule->getSubjectId(),
            $schedule->getInstructorId(),
            $schedule->getRoomId(),
            $schedule->getSectionId(),
            $schedule->getSchoolYear(),
            $schedule->getSemester(),
            $schedule->getDayOfWeek(),
            $schedule->getTimeSlot(),
            $schedule->getStatus()
        );

        $this->schedules[$id] = $persisted;

        return $persisted;
    }

    public function update(Schedule $schedule): Schedule
    {
        $id = $schedule->getId();
        if ($id === null || !isset($this->schedules[$id])) {
            return $schedule;
        }

        $this->schedules[$id] = $schedule;
        return $schedule;
    }

    public function hasInstructorAssignment(int $instructorId, int $sectionId, int $subjectId): bool
    {
        foreach ($this->schedules as $schedule) {
            if (!$schedule->isCancelled()
                && $schedule->getInstructorId() === $instructorId
                && $schedule->getSectionId() === $sectionId
                && $schedule->getSubjectId() === $subjectId
            ) {
                return true;
            }
        }

        return false;
    }
}
