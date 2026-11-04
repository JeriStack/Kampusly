<?php

declare(strict_types=1);

namespace Kampusly\Tests\Unit\Scheduling;

use Kampusly\Academic\Domain\ValueObject\Semester;
use Kampusly\Scheduling\Domain\Entity\Schedule;
use Kampusly\Scheduling\Domain\Service\ScheduleConflictChecker;
use Kampusly\Scheduling\Domain\ValueObject\DayOfWeek;
use Kampusly\Scheduling\Domain\ValueObject\ScheduleConflict;
use Kampusly\Scheduling\Domain\ValueObject\ScheduleStatus;
use Kampusly\Scheduling\Domain\ValueObject\TimeSlot;
use Kampusly\Shared\Domain\Exception\ValidationException;
use Kampusly\Tests\Support\TestCase;

final class TimeSlotAndConflictCheckerTest extends TestCase
{
    public function testTimeSlotCanonicalizationAndDuration(): void
    {
        $slot = new TimeSlot('08:00', '10:30');
        $this->assertSame('08:00:00', $slot->getStartTime());
        $this->assertSame('10:30:00', $slot->getEndTime());
        $this->assertSame(150, $slot->getDurationMinutes());
    }

    public function testTimeSlotThrowsWhenEndTimeIsNotLaterThanStartTime(): void
    {
        $this->assertThrows(ValidationException::class, static function (): void {
            new TimeSlot('10:00:00', '10:00:00');
        });

        $this->assertThrows(ValidationException::class, static function (): void {
            new TimeSlot('11:00:00', '09:00:00');
        });
    }

    public function testTimeSlotHalfOpenIntervalOverlapLogic(): void
    {
        $slot1 = new TimeSlot('08:00:00', '10:00:00');
        $adjacent = new TimeSlot('10:00:00', '12:00:00');
        $overlapping = new TimeSlot('09:30:00', '11:00:00');
        $enclosed = new TimeSlot('08:30:00', '09:30:00');
        $disjoint = new TimeSlot('13:00:00', '15:00:00');

        // Half-open interval [S1, E1) and [S2, E2): adjacent boundary 10:00 does NOT overlap
        $this->assertFalse($slot1->overlapsWith($adjacent), 'Adjacent classes must not overlap');
        $this->assertFalse($adjacent->overlapsWith($slot1), 'Adjacency is commutative');

        // Overlapping interior minutes must overlap
        $this->assertTrue($slot1->overlapsWith($overlapping));
        $this->assertTrue($overlapping->overlapsWith($slot1));

        // Sub-interval must overlap
        $this->assertTrue($slot1->overlapsWith($enclosed));

        // Disjoint does not overlap
        $this->assertFalse($slot1->overlapsWith($disjoint));
    }

    public function testConflictCheckerDetectsRoomInstructorAndSectionConflicts(): void
    {
        $existingSchedule = new Schedule(
            101,
            1, // Subject CS 201
            10, // Instructor #10
            20, // Room #20
            30, // Section #30
            '2026-2027',
            Semester::First,
            DayOfWeek::Monday,
            new TimeSlot('08:00:00', '10:00:00'),
            ScheduleStatus::Published,
            'CS 201',
            'Data Structures',
            'Dr. Elena Santos',
            'RM-301',
            'BSCS 2A'
        );

        $checker = new ScheduleConflictChecker();

        // 1. Same room, different instructor and section -> Room Conflict
        $candidateSlot = new TimeSlot('09:00:00', '11:00:00');
        $conflicts = $checker->detectConflicts(
            20, // Same room
            99, // Diff instructor
            99, // Diff section
            '2026-2027',
            Semester::First,
            DayOfWeek::Monday,
            $candidateSlot,
            [$existingSchedule]
        );

        $this->assertCount(1, $conflicts);
        $this->assertSame(ScheduleConflict::DIMENSION_ROOM, $conflicts[0]->getDimension());
        $this->assertSame(101, $conflicts[0]->getConflictingScheduleId());

        // 2. Same instructor, different room and section -> Instructor Conflict
        $conflictsInst = $checker->detectConflicts(
            99,
            10, // Same instructor
            99,
            '2026-2027',
            Semester::First,
            DayOfWeek::Monday,
            $candidateSlot,
            [$existingSchedule]
        );
        $this->assertCount(1, $conflictsInst);
        $this->assertSame(ScheduleConflict::DIMENSION_INSTRUCTOR, $conflictsInst[0]->getDimension());

        // 3. Same section, different room and instructor -> Section Conflict
        $conflictsSec = $checker->detectConflicts(
            99,
            99,
            30, // Same section
            '2026-2027',
            Semester::First,
            DayOfWeek::Monday,
            $candidateSlot,
            [$existingSchedule]
        );
        $this->assertCount(1, $conflictsSec);
        $this->assertSame(ScheduleConflict::DIMENSION_SECTION, $conflictsSec[0]->getDimension());

        // 4. Multi-dimension collision (all match)
        $conflictsAll = $checker->detectConflicts(
            20,
            10,
            30,
            '2026-2027',
            Semester::First,
            DayOfWeek::Monday,
            $candidateSlot,
            [$existingSchedule]
        );
        $this->assertCount(3, $conflictsAll);
    }

    public function testConflictCheckerIgnoresCancelledSchedulesAndExcludedId(): void
    {
        $cancelledSchedule = new Schedule(
            102,
            1,
            10,
            20,
            30,
            '2026-2027',
            Semester::First,
            DayOfWeek::Monday,
            new TimeSlot('08:00:00', '10:00:00'),
            ScheduleStatus::Cancelled
        );

        $activeSchedule = new Schedule(
            103,
            1,
            10,
            20,
            30,
            '2026-2027',
            Semester::First,
            DayOfWeek::Monday,
            new TimeSlot('08:00:00', '10:00:00'),
            ScheduleStatus::Published
        );

        $checker = new ScheduleConflictChecker();
        $candidateSlot = new TimeSlot('09:00:00', '11:00:00');

        // Cancelled schedule produces no conflicts
        $conflictsCancelled = $checker->detectConflicts(
            20,
            10,
            30,
            '2026-2027',
            Semester::First,
            DayOfWeek::Monday,
            $candidateSlot,
            [$cancelledSchedule]
        );
        $this->assertCount(0, $conflictsCancelled);

        // Excluded ID (self on update) produces no conflicts
        $conflictsSelf = $checker->detectConflicts(
            20,
            10,
            30,
            '2026-2027',
            Semester::First,
            DayOfWeek::Monday,
            $candidateSlot,
            [$activeSchedule],
            103 // Exclude schedule 103
        );
        $this->assertCount(0, $conflictsSelf);
    }
}

