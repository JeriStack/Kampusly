<?php

declare(strict_types=1);

namespace Kampusly\Scheduling\Application\Service;

use Kampusly\Academic\Domain\ValueObject\Semester;
use Kampusly\Scheduling\Domain\Entity\Schedule;
use Kampusly\Scheduling\Domain\Repository\ScheduleRepositoryInterface;
use Kampusly\Scheduling\Domain\Service\ScheduleConflictChecker;
use Kampusly\Scheduling\Domain\ValueObject\DayOfWeek;
use Kampusly\Scheduling\Domain\ValueObject\ScheduleConflict;
use Kampusly\Scheduling\Domain\ValueObject\ScheduleStatus;
use Kampusly\Scheduling\Domain\ValueObject\TimeSlot;
use Kampusly\Shared\Application\Contract\AuditLoggerInterface;
use Kampusly\Shared\Application\Contract\TransactionManagerInterface;
use Kampusly\Shared\Application\DTO\PaginatedResult;
use Kampusly\Shared\Domain\Exception\ConflictException;
use Kampusly\Shared\Domain\ValueObject\PaginationQuery;
use Kampusly\Shared\Infrastructure\Http\RequestValidator;

final class ScheduleFoundationService
{
    private ScheduleRepositoryInterface $schedules;
    private ScheduleConflictChecker $conflictChecker;
    private TransactionManagerInterface $transactions;
    private AuditLoggerInterface $auditLogger;

    public function __construct(
        ScheduleRepositoryInterface $schedules,
        ScheduleConflictChecker $conflictChecker,
        TransactionManagerInterface $transactions,
        AuditLoggerInterface $auditLogger
    ) {
        $this->schedules = $schedules;
        $this->conflictChecker = $conflictChecker;
        $this->transactions = $transactions;
        $this->auditLogger = $auditLogger;
    }

    /**
     * @param array<string, mixed> $query
     */
    public function listSchedules(array $query): PaginatedResult
    {
        $validator = new RequestValidator($query);
        $validator
            ->optionalString('school_year', 20)
            ->optionalIn('semester', Semester::values())
            ->optionalIn('day_of_week', DayOfWeek::values())
            ->optionalPositiveInt('section_id')
            ->optionalPositiveInt('instructor_id')
            ->optionalPositiveInt('room_id')
            ->optionalIn('status', ScheduleStatus::values())
            ->validateOrThrow();

        $pagination = PaginationQuery::fromQuery($query);
        $filters = [
            'school_year' => isset($query['school_year']) ? (string) $query['school_year'] : null,
            'semester' => isset($query['semester']) ? (string) $query['semester'] : null,
            'day_of_week' => isset($query['day_of_week']) ? (string) $query['day_of_week'] : null,
            'section_id' => isset($query['section_id']) && $query['section_id'] !== ''
                ? (int) $query['section_id']
                : null,
            'instructor_id' => isset($query['instructor_id']) && $query['instructor_id'] !== ''
                ? (int) $query['instructor_id']
                : null,
            'room_id' => isset($query['room_id']) && $query['room_id'] !== ''
                ? (int) $query['room_id']
                : null,
            'status' => isset($query['status']) ? (string) $query['status'] : null,
        ];

        return $this->schedules->paginate($pagination, $filters);
    }

    /**
     * Validate a schedule slot proposal and return any detected room, instructor, or section conflicts.
     *
     * @param array<string, mixed> $payload
     * @return array{has_conflicts: bool, conflicts: list<array<string, mixed>>}
     */
    public function validateProposal(array $payload): array
    {
        $this->validateSchedulePayload($payload, false);

        $roomId = (int) $payload['room_id'];
        $instructorId = (int) $payload['instructor_id'];
        $sectionId = (int) $payload['section_id'];
        $schoolYear = trim((string) $payload['school_year']);
        $semester = Semester::from((string) $payload['semester']);
        $dayOfWeek = DayOfWeek::from((string) $payload['day_of_week']);
        $timeSlot = new TimeSlot((string) $payload['start_time'], (string) $payload['end_time']);
        $excludeId = isset($payload['exclude_schedule_id']) && $payload['exclude_schedule_id'] !== ''
            ? (int) $payload['exclude_schedule_id']
            : null;

        $existing = $this->schedules->findActiveByTermAndDay(
            $schoolYear,
            $semester,
            $dayOfWeek,
            $excludeId
        );

        $conflicts = $this->conflictChecker->detectConflicts(
            $roomId,
            $instructorId,
            $sectionId,
            $schoolYear,
            $semester,
            $dayOfWeek,
            $timeSlot,
            $existing,
            $excludeId
        );

        $serializedConflicts = array_map(
            static fn (ScheduleConflict $conflict): array => $conflict->toArray(),
            $conflicts
        );

        return [
            'has_conflicts' => $serializedConflicts !== [],
            'conflicts' => $serializedConflicts,
        ];
    }

    /**
     * Create a new schedule entry inside an atomic transaction after verifying zero conflicts.
     *
     * @param array<string, mixed> $payload
     */
    public function createSchedule(
        array $payload,
        ?int $actorUserId = null,
        ?string $requestId = null
    ): Schedule {
        $this->validateSchedulePayload($payload, true);

        /** @var Schedule $created */
        $created = $this->transactions->transactional(
            function () use ($payload, $actorUserId, $requestId): Schedule {
                $validation = $this->validateProposal($payload);
                if ($validation['has_conflicts']) {
                    throw ConflictException::scheduleConflict($validation['conflicts']);
                }

                $schedule = new Schedule(
                    null,
                    (int) $payload['subject_id'],
                    (int) $payload['instructor_id'],
                    (int) $payload['room_id'],
                    (int) $payload['section_id'],
                    trim((string) $payload['school_year']),
                    Semester::from((string) $payload['semester']),
                    DayOfWeek::from((string) $payload['day_of_week']),
                    new TimeSlot((string) $payload['start_time'], (string) $payload['end_time']),
                    ScheduleStatus::from((string) ($payload['status'] ?? 'published'))
                );

                $persisted = $this->schedules->create($schedule);
                $this->auditLogger->record(
                    $actorUserId,
                    'schedule.created',
                    'schedule',
                    $persisted->getId(),
                    [
                        'school_year' => $persisted->getSchoolYear(),
                        'semester' => $persisted->getSemester()->value,
                        'day_of_week' => $persisted->getDayOfWeek()->value,
                        'start_time' => $persisted->getTimeSlot()->getStartTime(),
                        'end_time' => $persisted->getTimeSlot()->getEndTime(),
                    ],
                    $requestId
                );

                return $persisted;
            }
        );

        return $created;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function validateSchedulePayload(array $payload, bool $requireSubjectId): void
    {
        $validator = new RequestValidator($payload);
        if ($requireSubjectId) {
            $validator->requirePositiveInt('subject_id');
        } else {
            $validator->optionalPositiveInt('subject_id');
        }

        $validator
            ->requirePositiveInt('instructor_id')
            ->requirePositiveInt('room_id')
            ->requirePositiveInt('section_id')
            ->requireSchoolYear('school_year')
            ->requireIn('semester', Semester::values())
            ->requireIn('day_of_week', DayOfWeek::values())
            ->requireTime('start_time')
            ->requireTime('end_time')
            ->requireTimeOrder('start_time', 'end_time')
            ->optionalIn('status', ScheduleStatus::values())
            ->optionalPositiveInt('exclude_schedule_id')
            ->validateOrThrow();
    }
}

