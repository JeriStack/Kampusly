<?php

declare(strict_types=1);

namespace Kampusly\Academic\Application\Service;

use Kampusly\Academic\Domain\Entity\Room;
use Kampusly\Academic\Domain\Entity\Subject;
use Kampusly\Academic\Domain\Repository\RoomRepositoryInterface;
use Kampusly\Academic\Domain\Repository\SectionRepositoryInterface;
use Kampusly\Academic\Domain\Repository\SubjectRepositoryInterface;
use Kampusly\Academic\Domain\ValueObject\ReferenceStatus;
use Kampusly\Academic\Domain\ValueObject\Semester;
use Kampusly\Shared\Application\Contract\AuditLoggerInterface;
use Kampusly\Shared\Application\Contract\TransactionManagerInterface;
use Kampusly\Shared\Application\DTO\PaginatedResult;
use Kampusly\Shared\Domain\Exception\ConflictException;
use Kampusly\Shared\Domain\ValueObject\PaginationQuery;
use Kampusly\Shared\Infrastructure\Http\RequestValidator;

final class AcademicCatalogService
{
    private SubjectRepositoryInterface $subjects;
    private RoomRepositoryInterface $rooms;
    private SectionRepositoryInterface $sections;
    private TransactionManagerInterface $transactions;
    private AuditLoggerInterface $auditLogger;

    public function __construct(
        SubjectRepositoryInterface $subjects,
        RoomRepositoryInterface $rooms,
        SectionRepositoryInterface $sections,
        TransactionManagerInterface $transactions,
        AuditLoggerInterface $auditLogger
    ) {
        $this->subjects = $subjects;
        $this->rooms = $rooms;
        $this->sections = $sections;
        $this->transactions = $transactions;
        $this->auditLogger = $auditLogger;
    }

    /**
     * @param array<string, mixed> $query
     */
    public function listSubjects(array $query): PaginatedResult
    {
        $validator = new RequestValidator($query);
        $validator
            ->optionalIn('status', ReferenceStatus::values())
            ->optionalString('q', 120)
            ->validateOrThrow();

        $pagination = PaginationQuery::fromQuery($query);
        $status = isset($query['status']) && is_string($query['status']) ? $query['status'] : null;
        $search = isset($query['q']) && is_string($query['q']) ? $query['q'] : null;

        return $this->subjects->paginate($pagination, $status, $search);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function createSubject(array $payload, ?int $actorUserId = null, ?string $requestId = null): Subject
    {
        $validator = new RequestValidator($payload);
        $validator
            ->requireString('subject_code', 2, 30)
            ->requireString('subject_name', 3, 150)
            ->optionalString('description', 2000)
            ->requireInt('units', 1, 12)
            ->optionalIn('status', ReferenceStatus::values())
            ->validateOrThrow();

        $code = strtoupper(trim((string) $payload['subject_code']));
        if ($this->subjects->findByCode($code) !== null) {
            throw new ConflictException(
                sprintf('A subject with code "%s" already exists.', $code),
                'DUPLICATE_SUBJECT_CODE'
            );
        }

        $subject = new Subject(
            null,
            $code,
            (string) $payload['subject_name'],
            isset($payload['description']) ? (string) $payload['description'] : null,
            (int) $payload['units'],
            ReferenceStatus::from((string) ($payload['status'] ?? 'active'))
        );

        /** @var Subject $created */
        $created = $this->transactions->transactional(function () use ($subject, $actorUserId, $requestId): Subject {
            $persisted = $this->subjects->create($subject);
            $this->auditLogger->record(
                $actorUserId,
                'subject.created',
                'subject',
                $persisted->getId(),
                ['subject_code' => $persisted->getSubjectCode()],
                $requestId
            );

            return $persisted;
        });

        return $created;
    }

    /**
     * @param array<string, mixed> $query
     */
    public function listRooms(array $query): PaginatedResult
    {
        $validator = new RequestValidator($query);
        $validator
            ->optionalIn('status', ReferenceStatus::values())
            ->optionalString('building', 100)
            ->optionalString('q', 120)
            ->validateOrThrow();

        $pagination = PaginationQuery::fromQuery($query);
        $status = isset($query['status']) && is_string($query['status']) ? $query['status'] : null;
        $building = isset($query['building']) && is_string($query['building']) ? $query['building'] : null;
        $search = isset($query['q']) && is_string($query['q']) ? $query['q'] : null;

        return $this->rooms->paginate($pagination, $status, $building, $search);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function createRoom(array $payload, ?int $actorUserId = null, ?string $requestId = null): Room
    {
        $validator = new RequestValidator($payload);
        $validator
            ->requireString('room_code', 2, 30)
            ->requireString('room_name', 2, 100)
            ->requireString('building', 2, 100)
            ->requireInt('capacity', 1, 1000)
            ->optionalIn('status', ReferenceStatus::values())
            ->validateOrThrow();

        $code = strtoupper(trim((string) $payload['room_code']));
        if ($this->rooms->findByCode($code) !== null) {
            throw new ConflictException(
                sprintf('A room with code "%s" already exists.', $code),
                'DUPLICATE_ROOM_CODE'
            );
        }

        $room = new Room(
            null,
            $code,
            (string) $payload['room_name'],
            (string) $payload['building'],
            (int) $payload['capacity'],
            ReferenceStatus::from((string) ($payload['status'] ?? 'active'))
        );

        /** @var Room $created */
        $created = $this->transactions->transactional(function () use ($room, $actorUserId, $requestId): Room {
            $persisted = $this->rooms->create($room);
            $this->auditLogger->record(
                $actorUserId,
                'room.created',
                'room',
                $persisted->getId(),
                ['room_code' => $persisted->getRoomCode()],
                $requestId
            );

            return $persisted;
        });

        return $created;
    }

    /**
     * @param array<string, mixed> $query
     */
    public function listSections(array $query): PaginatedResult
    {
        $validator = new RequestValidator($query);
        $validator
            ->optionalString('school_year', 20)
            ->optionalIn('semester', Semester::values())
            ->optionalIn('status', ReferenceStatus::values())
            ->optionalString('q', 120)
            ->validateOrThrow();

        $pagination = PaginationQuery::fromQuery($query);
        $schoolYear = isset($query['school_year']) && is_string($query['school_year']) ? $query['school_year'] : null;
        $semester = isset($query['semester']) && is_string($query['semester']) ? $query['semester'] : null;
        $status = isset($query['status']) && is_string($query['status']) ? $query['status'] : null;
        $search = isset($query['q']) && is_string($query['q']) ? $query['q'] : null;

        return $this->sections->paginate($pagination, $schoolYear, $semester, $status, $search);
    }
}

