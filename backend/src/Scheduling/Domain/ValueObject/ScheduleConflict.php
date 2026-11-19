<?php

declare(strict_types=1);

namespace Kampusly\Scheduling\Domain\ValueObject;

final class ScheduleConflict
{
    public const DIMENSION_ROOM = 'room';
    public const DIMENSION_INSTRUCTOR = 'instructor';
    public const DIMENSION_SECTION = 'section';

    private string $dimension;
    private int $conflictingScheduleId;
    private string $summary;
    private DayOfWeek $dayOfWeek;
    private string $startTime;
    private string $endTime;

    public function __construct(
        string $dimension,
        int $conflictingScheduleId,
        string $summary,
        DayOfWeek $dayOfWeek,
        string $startTime,
        string $endTime
    ) {
        $this->dimension = $dimension;
        $this->conflictingScheduleId = $conflictingScheduleId;
        $this->summary = $summary;
        $this->dayOfWeek = $dayOfWeek;
        $this->startTime = $startTime;
        $this->endTime = $endTime;
    }

    public function getDimension(): string
    {
        return $this->dimension;
    }

    public function getConflictingScheduleId(): int
    {
        return $this->conflictingScheduleId;
    }

    public function getSummary(): string
    {
        return $this->summary;
    }

    /**
     * Matches OpenAPI 3.1 ScheduleConflictItem schema.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'dimension' => $this->dimension,
            'conflicting_schedule_id' => $this->conflictingScheduleId,
            'summary' => $this->summary,
            'day_of_week' => $this->dayOfWeek->value,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
        ];
    }
}

