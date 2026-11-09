<?php

declare(strict_types=1);

namespace Kampusly\Scheduling\Application\Service;

use PDOException;
use Kampusly\Academic\Domain\Repository\InstructorRepositoryInterface;
use Kampusly\Academic\Domain\Repository\RoomRepositoryInterface;
use Kampusly\Academic\Domain\Repository\SectionRepositoryInterface;
use Kampusly\Academic\Domain\Repository\StudentRepositoryInterface;
use Kampusly\IdentityAccess\Domain\Entity\User;
use Kampusly\Notifications\Domain\Entity\Notification;
use Kampusly\Notifications\Domain\Repository\NotificationRepositoryInterface;
use Kampusly\Notifications\Domain\ValueObject\NotificationType;
use Kampusly\Scheduling\Domain\Repository\ScheduleChangeRequestRepositoryInterface;
use Kampusly\Scheduling\Domain\Repository\ScheduleRepositoryInterface;
use Kampusly\Scheduling\Domain\ValueObject\DayOfWeek;
use Kampusly\Scheduling\Domain\ValueObject\ScheduleChangeWindow;
use Kampusly\Scheduling\Domain\ValueObject\ScheduleStatus;
use Kampusly\Shared\Application\Contract\AuditLoggerInterface;
use Kampusly\Shared\Application\Contract\TransactionManagerInterface;
use Kampusly\Shared\Application\DTO\PaginatedResult;
use Kampusly\Shared\Domain\Exception\ConflictException;
use Kampusly\Shared\Domain\Exception\ForbiddenException;
use Kampusly\Shared\Domain\Exception\ResourceNotFoundException;
use Kampusly\Shared\Domain\Exception\ValidationException;
use Kampusly\Shared\Domain\ValueObject\PaginationQuery;
use Kampusly\Shared\Infrastructure\Http\RequestValidator;

final class ScheduleChangeRequestService
{
    private ScheduleChangeRequestRepositoryInterface $requests;
    private StudentRepositoryInterface $students;
    private SectionRepositoryInterface $sections;
    private ScheduleRepositoryInterface $schedules;
    private InstructorRepositoryInterface $instructors;
    private RoomRepositoryInterface $rooms;
    private NotificationRepositoryInterface $notifications;
    private TransactionManagerInterface $transactions;
    private AuditLoggerInterface $auditLogger;

    public function __construct(
        ScheduleChangeRequestRepositoryInterface $requests,
        StudentRepositoryInterface $students,
        SectionRepositoryInterface $sections,
        ScheduleRepositoryInterface $schedules,
        InstructorRepositoryInterface $instructors,
        RoomRepositoryInterface $rooms,
        NotificationRepositoryInterface $notifications,
        TransactionManagerInterface $transactions,
        AuditLoggerInterface $auditLogger
    ) {
        $this->requests = $requests;
        $this->students = $students;
        $this->sections = $sections;
        $this->schedules = $schedules;
        $this->instructors = $instructors;
        $this->rooms = $rooms;
        $this->notifications = $notifications;
        $this->transactions = $transactions;
        $this->auditLogger = $auditLogger;
    }

    public function listForStudent(User $user, array $query): PaginatedResult
    {
        $student = $this->students->findByUserId((int) $user->getId());
        if ($student === null) {
            throw new ResourceNotFoundException('Student profile was not found.');
        }

        $result = $this->requests->paginateForStudent(
            (int) $student->getId(),
            PaginationQuery::fromQuery($query)
        );

        return $this->presentPage($result);
    }

    public function listForAdministrators(array $query): PaginatedResult
    {
        $validator = new RequestValidator($query);
        $validator->optionalIn('status', ['pending', 'approved', 'rejected'])->validateOrThrow();

        $result = $this->requests->paginateAll(
            isset($query['status']) && $query['status'] !== '' ? (string) $query['status'] : null,
            PaginationQuery::fromQuery($query)
        );

        return $this->presentPage($result);
    }

    /** @param array<string, mixed> $payload */
    public function create(User $user, array $payload, ?string $requestId): array
    {
        $validator = new RequestValidator($payload);
        $validator
            ->requirePositiveInt('schedule_id')
            ->optionalIn('preferred_day_of_week', DayOfWeek::values())
            ->optionalTime('preferred_start_time')
            ->optionalTime('preferred_end_time')
            ->optionalPositiveInt('preferred_room_id')
            ->requireString('reason', 10, 1000)
            ->validateOrThrow();

        $start = $payload['preferred_start_time'] ?? null;
        $end = $payload['preferred_end_time'] ?? null;
        if (($start === null || $start === '') !== ($end === null || $end === '')) {
            throw new ValidationException([
                'preferred_start_time' => ['Preferred start and end times must be provided together.'],
            ]);
        }
        if (is_string($start) && is_string($end)) {
            $validator->requireTimeOrder('preferred_start_time', 'preferred_end_time')->validateOrThrow();
        }
        if (($payload['preferred_day_of_week'] ?? '') === ''
            && ($start === null || $start === '')
            && ($payload['preferred_room_id'] ?? '') === ''
        ) {
            throw new ValidationException([
                'preferred_day_of_week' => ['Provide at least one preferred day, time, or classroom.'],
            ]);
        }

        $student = $this->students->findByUserId((int) $user->getId());
        if ($student === null) {
            throw new ResourceNotFoundException('Student profile was not found.');
        }
        $scheduleId = (int) $payload['schedule_id'];
        $schedule = $this->schedules->findById($scheduleId);
        if ($schedule === null || $schedule->getSectionId() !== $student->getSectionId()) {
            throw new ResourceNotFoundException('The selected schedule was not found in your section.');
        }
        if ($schedule->getStatus() !== ScheduleStatus::Published) {
            throw new ConflictException('Only published class schedules can be changed.', 'SCHEDULE_NOT_PUBLISHED');
        }
        if (isset($payload['preferred_room_id']) && $payload['preferred_room_id'] !== ''
            && $this->rooms->findById((int) $payload['preferred_room_id']) === null
        ) {
            throw new ResourceNotFoundException('The preferred classroom was not found.');
        }

        $section = $this->sections->findById($student->getSectionId());
        if ($section === null || $section->getTermStartDate() === null) {
            throw new ConflictException(
                'Schedule change requests are unavailable until the administrator sets the semester start date.',
                'TERM_START_DATE_NOT_SET'
            );
        }

        $window = new ScheduleChangeWindow($section->getTermStartDate());
        if (!$window->isOpen()) {
            throw new ConflictException('The schedule change request period has ended or has not started.', 'REQUEST_WINDOW_CLOSED');
        }

        $scheduleData = $schedule->toArray();
        $request = [
            'student_id' => $student->getId(),
            'schedule_id' => $scheduleId,
            'current_day_of_week' => $scheduleData['day_of_week'],
            'current_start_time' => $scheduleData['start_time'],
            'current_end_time' => $scheduleData['end_time'],
            'current_room_code' => $scheduleData['room_code'] ?? '',
            'preferred_day_of_week' => isset($payload['preferred_day_of_week'])
                && $payload['preferred_day_of_week'] !== ''
                ? $payload['preferred_day_of_week']
                : null,
            'preferred_start_time' => $start !== null && $start !== '' ? $start : null,
            'preferred_end_time' => $end !== null && $end !== '' ? $end : null,
            'preferred_room_id' => isset($payload['preferred_room_id']) && $payload['preferred_room_id'] !== ''
                ? (int) $payload['preferred_room_id']
                : null,
            'reason' => trim((string) $payload['reason']),
        ];

        try {
            return $this->transactions->transactional(function () use ($request, $schedule, $user, $requestId): array {
                if ($this->requests->hasPendingRequest((int) $request['student_id'], (int) $request['schedule_id'])) {
                    throw new ConflictException(
                        'You already have a pending request for this class.',
                        'REQUEST_ALREADY_PENDING'
                    );
                }

                $id = $this->requests->create($request);
                $created = $this->requests->findById($id);
                if ($created === null) {
                    throw new ResourceNotFoundException(
                        'The schedule change request could not be loaded after creation.'
                    );
                }

                $instructor = $this->instructors->findById($schedule->getInstructorId());
                if ($instructor !== null && $instructor->getStatus() === 'active') {
                    $this->notifications->create(new Notification(
                        null,
                        $instructor->getUserId(),
                        'Student schedule change request',
                        sprintf(
                            '%s requested a change to %s (%s).',
                            $created['student_name'],
                            $created['subject_code'],
                            $created['section_name']
                        ),
                        NotificationType::ScheduleUpdate,
                        'schedule_change_request',
                        $id,
                        'schedule-change-request:' . $id . ':submitted:' . $instructor->getUserId()
                    ));
                }
                $this->auditLogger->record(
                    $user->getId(),
                    'schedule_change_request.created',
                    'schedule_change_request',
                    $id,
                    ['schedule_id' => $schedule->getId()],
                    $requestId
                );

                return $this->presentRequest($created);
            });
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000' && (int) ($exception->errorInfo[1] ?? 0) === 1062) {
                throw new ConflictException(
                    'You already have a pending request for this class.',
                    'REQUEST_ALREADY_PENDING'
                );
            }

            throw $exception;
        }
    }

    /** @param array<string, mixed> $payload */
    public function review(int $id, User $reviewer, array $payload, ?string $requestId): array
    {
        $validator = new RequestValidator($payload);
        $validator
            ->requireIn('status', ['approved', 'rejected'])
            ->optionalString('review_note', 1000)
            ->validateOrThrow();

        $existing = $this->requests->findById($id);
        if ($existing === null) {
            throw new ResourceNotFoundException('Schedule change request not found.');
        }
        if ((string) $existing['status'] !== 'pending') {
            throw new ConflictException('This request has already been reviewed.', 'REQUEST_ALREADY_REVIEWED');
        }

        $status = (string) $payload['status'];
        $note = isset($payload['review_note']) && trim((string) $payload['review_note']) !== ''
            ? trim((string) $payload['review_note'])
            : null;

        return $this->transactions->transactional(function () use (
            $id,
            $reviewer,
            $status,
            $note,
            $existing,
            $requestId
        ): array {
            if (!$this->requests->review($id, (int) $reviewer->getId(), $status, $note)) {
                throw new ConflictException('This request has already been reviewed.', 'REQUEST_ALREADY_REVIEWED');
            }

            $this->notifications->create(new Notification(
                null,
                (int) $existing['student_user_id'],
                'Schedule change request ' . ucfirst($status),
                $note ?? sprintf('Your request for %s was %s.', $existing['subject_code'], $status),
                NotificationType::ScheduleUpdate,
                'schedule_change_request',
                $id,
                'schedule-change-request:' . $id . ':' . $status
            ));
            $instructor = $this->instructors->findById((int) $existing['instructor_id']);
            if ($instructor !== null && $instructor->getStatus() === 'active') {
                $this->notifications->create(new Notification(
                    null,
                    $instructor->getUserId(),
                    'Student schedule change request ' . $status,
                    sprintf(
                        'The request from %s for %s (%s) was %s.',
                        $existing['student_name'],
                        $existing['subject_code'],
                        $existing['section_name'],
                        $status
                    ),
                    NotificationType::ScheduleUpdate,
                    'schedule_change_request',
                    $id,
                    'schedule-change-request:' . $id . ':' . $status . ':instructor'
                ));
            }
            $this->auditLogger->record(
                $reviewer->getId(),
                'schedule_change_request.' . $status,
                'schedule_change_request',
                $id,
                ['review_note' => $note],
                $requestId
            );

            $reviewed = $this->requests->findById($id);
            if ($reviewed === null) {
                throw new ResourceNotFoundException('Reviewed schedule change request was not found.');
            }

            return $this->presentRequest($reviewed);
        });
    }

    private function presentPage(PaginatedResult $result): PaginatedResult
    {
        return new PaginatedResult(
            array_map(fn (array $request): array => $this->presentRequest($request), $result->getItems()),
            $result->getPage(),
            $result->getPerPage(),
            $result->getTotal()
        );
    }

    /**
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    private function presentRequest(array $request): array
    {
        unset(
            $request['student_user_id'],
            $request['instructor_id'],
            $request['subject_id'],
            $request['term_start_date'],
            $request['pending_schedule_id'],
            $request['reviewed_by_user_id']
        );

        return $request;
    }

}
