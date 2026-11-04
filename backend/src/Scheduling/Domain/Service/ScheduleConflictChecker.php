<?php

declare(strict_types=1);

namespace Kampusly\Scheduling\Domain\Service;

use Kampusly\Academic\Domain\ValueObject\Semester;
use Kampusly\Scheduling\Domain\Entity\Schedule;
use Kampusly\Scheduling\Domain\ValueObject\DayOfWeek;
use Kampusly\Scheduling\Domain\ValueObject\ScheduleConflict;
use Kampusly\Scheduling\Domain\ValueObject\TimeSlot;

final class ScheduleConflictChecker
{
    /**
     * Evaluate a candidate class schedule slot against existing schedules for
     * room, instructor, and section double-booking conflicts.
     *
     * @param list<Schedule> $existingSchedules
     * @return list<ScheduleConflict>
     */
    public function detectConflicts(
        int $roomId,
        int $instructorId,
        int $sectionId,
        string $schoolYear,
        Semester $semester,
        DayOfWeek $dayOfWeek,
        TimeSlot $candidateSlot,
        array $existingSchedules,
        ?int $excludeScheduleId = null
    ): array {
        $conflicts = [];

        foreach ($existingSchedules as $existing) {
            $existingId = $existing->getId() ?? 0;
            if ($excludeScheduleId !== null && $existingId === $excludeScheduleId) {
                continue;
            }

            if ($existing->isCancelled()) {
                continue;
            }

            if ($existing->getSchoolYear() !== trim($schoolYear)
                || $existing->getSemester() !== $semester
                || $existing->getDayOfWeek() !== $dayOfWeek
            ) {
                continue;
            }

            if (!$candidateSlot->overlapsWith($existing->getTimeSlot())) {
                continue;
            }

            $existingStart = $existing->getTimeSlot()->getStartTime();
            $existingEnd = $existing->getTimeSlot()->getEndTime();
            $label = $existing->describeDisplayLabel();

            if ($existing->getRoomId() === $roomId) {
                $conflicts[] = new ScheduleConflict(
                    ScheduleConflict::DIMENSION_ROOM,
                    $existingId,
                    sprintf(
                        'Room is already booked by %s on %s (%s - %s).',
                        $label,
                        $dayOfWeek->value,
                        $existingStart,
                        $existingEnd
                    ),
                    $dayOfWeek,
                    $existingStart,
                    $existingEnd
                );
            }

            if ($existing->getInstructorId() === $instructorId) {
                $conflicts[] = new ScheduleConflict(
                    ScheduleConflict::DIMENSION_INSTRUCTOR,
                    $existingId,
                    sprintf(
                        'Instructor is already assigned to %s on %s (%s - %s).',
                        $label,
                        $dayOfWeek->value,
                        $existingStart,
                        $existingEnd
                    ),
                    $dayOfWeek,
                    $existingStart,
                    $existingEnd
                );
            }

            if ($existing->getSectionId() === $sectionId) {
                $conflicts[] = new ScheduleConflict(
                    ScheduleConflict::DIMENSION_SECTION,
                    $existingId,
                    sprintf(
                        'Student section already has %s scheduled on %s (%s - %s).',
                        $label,
                        $dayOfWeek->value,
                        $existingStart,
                        $existingEnd
                    ),
                    $dayOfWeek,
                    $existingStart,
                    $existingEnd
                );
            }
        }

        return $conflicts;
    }
}

