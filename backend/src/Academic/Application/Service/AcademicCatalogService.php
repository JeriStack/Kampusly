<?php

declare(strict_types=1);

namespace Kampusly\Academic\Application\Service;

use Kampusly\Academic\Domain\Entity\Room;
use Kampusly\Academic\Domain\Entity\Section;
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
use Kampusly\Shared\Domain\Exception\ResourceNotFoundException;
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

    // -------------------------------------------------------------------------
    // Subjects
    // -------------------------------------------------------------------------

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
     * @return array<string, mixed>
     */
    public function getSubject(int $id): array
    {
        $subject = $this->subjects->findById($id);
        if ($subject === null) {
            throw new ResourceNotFoundException('Subject not found.');
        }

        return $subject->toArray();
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
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function updateSubject(int $id, array $payload, ?int $actorUserId = null, ?string $requestId = null): array
    {
        $existing = $this->subjects->findById($id);
        if ($existing === null) {
            throw new ResourceNotFoundException('Subject not found.');
        }

        $validator = new RequestValidator($payload);
        $validator
            ->requireString('subject_code', 2, 30)
            ->requireString('subject_name', 3, 150)
            ->optionalString('description', 2000)
            ->requireInt('units', 1, 12)
            ->optionalIn('status', ReferenceStatus::values())
            ->validateOrThrow();

        $newCode = strtoupper(trim((string) $payload['subject_code']));
        $subjectWithCode = $this->subjects->findByCode($newCode);
        if ($subjectWithCode !== null && $subjectWithCode->getId() !== $id) {
            throw new ConflictException(
                sprintf('A subject with code "%s" already exists.', $newCode),
                'DUPLICATE_SUBJECT_CODE'
            );
        }

        $updated = new Subject(
            $id,
            $newCode,
            (string) $payload['subject_name'],
            isset($payload['description']) ? (string) $payload['description'] : null,
            (int) $payload['units'],
            ReferenceStatus::from((string) ($payload['status'] ?? $existing->getStatus()->value))
        );

        $this->transactions->transactional(function () use ($updated, $actorUserId, $requestId): void {
            $this->subjects->update($updated);
            $this->auditLogger->record(
                $actorUserId,
                'subject.updated',
                'subject',
                $updated->getId(),
                ['subject_code' => $updated->getSubjectCode()],
                $requestId
            );
        });

        return $this->getSubject($id);
    }

    public function deleteSubject(int $id, ?int $actorUserId = null, ?string $requestId = null): void
    {
        $subject = $this->subjects->findById($id);
        if ($subject === null) {
            throw new ResourceNotFoundException('Subject not found.');
        }

        if ($this->subjects->hasSchedules($id)) {
            throw new ConflictException(
                'Cannot delete subject because it is referenced by existing class schedules. Deactivate it instead.',
                'SUBJECT_HAS_SCHEDULES'
            );
        }

        $this->transactions->transactional(function () use ($id, $subject, $actorUserId, $requestId): void {
            $this->subjects->delete($id);
            $this->auditLogger->record(
                $actorUserId,
                'subject.deleted',
                'subject',
                $id,
                ['subject_code' => $subject->getSubjectCode()],
                $requestId
            );
        });
    }

    // -------------------------------------------------------------------------
    // Rooms
    // -------------------------------------------------------------------------

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
     * @return array<string, mixed>
     */
    public function getRoom(int $id): array
    {
        $room = $this->rooms->findById($id);
        if ($room === null) {
            throw new ResourceNotFoundException('Room not found.');
        }

        return $room->toArray();
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
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function updateRoom(int $id, array $payload, ?int $actorUserId = null, ?string $requestId = null): array
    {
        $existing = $this->rooms->findById($id);
        if ($existing === null) {
            throw new ResourceNotFoundException('Room not found.');
        }

        $validator = new RequestValidator($payload);
        $validator
            ->requireString('room_code', 2, 30)
            ->requireString('room_name', 2, 100)
            ->requireString('building', 2, 100)
            ->requireInt('capacity', 1, 1000)
            ->optionalIn('status', ReferenceStatus::values())
            ->validateOrThrow();

        $newCode = strtoupper(trim((string) $payload['room_code']));
        $roomWithCode = $this->rooms->findByCode($newCode);
        if ($roomWithCode !== null && $roomWithCode->getId() !== $id) {
            throw new ConflictException(
                sprintf('A room with code "%s" already exists.', $newCode),
                'DUPLICATE_ROOM_CODE'
            );
        }

        $updated = new Room(
            $id,
            $newCode,
            (string) $payload['room_name'],
            (string) $payload['building'],
            (int) $payload['capacity'],
            ReferenceStatus::from((string) ($payload['status'] ?? $existing->getStatus()->value))
        );

        $this->transactions->transactional(function () use ($updated, $actorUserId, $requestId): void {
            $this->rooms->update($updated);
            $this->auditLogger->record(
                $actorUserId,
                'room.updated',
                'room',
                $updated->getId(),
                ['room_code' => $updated->getRoomCode()],
                $requestId
            );
        });

        return $this->getRoom($id);
    }

    public function deleteRoom(int $id, ?int $actorUserId = null, ?string $requestId = null): void
    {
        $room = $this->rooms->findById($id);
        if ($room === null) {
            throw new ResourceNotFoundException('Room not found.');
        }

        if ($this->rooms->hasSchedules($id)) {
            throw new ConflictException(
                'Cannot delete room because it is referenced by existing class schedules. Deactivate it instead.',
                'ROOM_HAS_SCHEDULES'
            );
        }

        $this->transactions->transactional(function () use ($id, $room, $actorUserId, $requestId): void {
            $this->rooms->delete($id);
            $this->auditLogger->record(
                $actorUserId,
                'room.deleted',
                'room',
                $id,
                ['room_code' => $room->getRoomCode()],
                $requestId
            );
        });
    }

    // -------------------------------------------------------------------------
    // Sections
    // -------------------------------------------------------------------------

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

    /**
     * @return array<string, mixed>
     */
    public function getSection(int $id): array
    {
        $section = $this->sections->findById($id);
        if ($section === null) {
            throw new ResourceNotFoundException('Section not found.');
        }

        $data = $section->toArray();
        $data['students'] = $this->sections->findStudents($id);
        $data['enrolled_students_count'] = count($data['students']);

        return $data;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createSection(array $payload, ?int $actorUserId = null, ?string $requestId = null): array
    {
        $validator = new RequestValidator($payload);
        $validator
            ->requireString('section_name', 2, 60)
            ->requireString('program', 2, 120)
            ->requireInt('year_level', 1, 6)
            ->requireString('school_year', 4, 20)
            ->requireIn('semester', Semester::values())
            ->optionalIn('status', ReferenceStatus::values())
            ->validateOrThrow();

        $name = trim((string) $payload['section_name']);
        $sy = trim((string) $payload['school_year']);
        $sem = (string) $payload['semester'];

        if ($this->sections->findByNameAndTerm($name, $sy, $sem) !== null) {
            throw new ConflictException(
                sprintf('A section named "%s" already exists for %s %s semester.', $name, $sy, $sem),
                'DUPLICATE_SECTION'
            );
        }

        $section = new Section(
            null,
            $name,
            (string) $payload['program'],
            (int) $payload['year_level'],
            $sy,
            Semester::from($sem),
            ReferenceStatus::from((string) ($payload['status'] ?? 'active'))
        );

        /** @var Section $created */
        $created = $this->transactions->transactional(function () use ($section, $actorUserId, $requestId): Section {
            $persisted = $this->sections->create($section);
            $this->auditLogger->record(
                $actorUserId,
                'section.created',
                'section',
                $persisted->getId(),
                ['section_name' => $persisted->getSectionName()],
                $requestId
            );

            return $persisted;
        });

        return $this->getSection((int) $created->getId());
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function updateSection(int $id, array $payload, ?int $actorUserId = null, ?string $requestId = null): array
    {
        $existing = $this->sections->findById($id);
        if ($existing === null) {
            throw new ResourceNotFoundException('Section not found.');
        }

        $validator = new RequestValidator($payload);
        $validator
            ->requireString('section_name', 2, 60)
            ->requireString('program', 2, 120)
            ->requireInt('year_level', 1, 6)
            ->requireString('school_year', 4, 20)
            ->requireIn('semester', Semester::values())
            ->optionalIn('status', ReferenceStatus::values())
            ->validateOrThrow();

        $name = trim((string) $payload['section_name']);
        $sy = trim((string) $payload['school_year']);
        $sem = (string) $payload['semester'];

        $existingWithSameTerm = $this->sections->findByNameAndTerm($name, $sy, $sem);
        if ($existingWithSameTerm !== null && $existingWithSameTerm->getId() !== $id) {
            throw new ConflictException(
                sprintf('A section named "%s" already exists for %s %s semester.', $name, $sy, $sem),
                'DUPLICATE_SECTION'
            );
        }

        $updated = new Section(
            $id,
            $name,
            (string) $payload['program'],
            (int) $payload['year_level'],
            $sy,
            Semester::from($sem),
            ReferenceStatus::from((string) ($payload['status'] ?? $existing->getStatus()->value))
        );

        $this->transactions->transactional(function () use ($updated, $actorUserId, $requestId): void {
            $this->sections->update($updated);
            $this->auditLogger->record(
                $actorUserId,
                'section.updated',
                'section',
                $updated->getId(),
                ['section_name' => $updated->getSectionName()],
                $requestId
            );
        });

        return $this->getSection($id);
    }

    public function deleteSection(int $id, ?int $actorUserId = null, ?string $requestId = null): void
    {
        $section = $this->sections->findById($id);
        if ($section === null) {
            throw new ResourceNotFoundException('Section not found.');
        }

        if ($this->sections->hasSchedules($id)) {
            throw new ConflictException(
                'Cannot delete section because it is referenced by existing class schedules. Deactivate it instead.',
                'SECTION_HAS_SCHEDULES'
            );
        }

        if ($this->sections->hasStudents($id)) {
            throw new ConflictException(
                'Cannot delete section because students are currently assigned to it. Reassign students first.',
                'SECTION_HAS_STUDENTS'
            );
        }

        $this->transactions->transactional(function () use ($id, $section, $actorUserId, $requestId): void {
            $this->sections->delete($id);
            $this->auditLogger->record(
                $actorUserId,
                'section.deleted',
                'section',
                $id,
                ['section_name' => $section->getSectionName()],
                $requestId
            );
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listSectionStudents(int $id): array
    {
        $section = $this->sections->findById($id);
        if ($section === null) {
            throw new ResourceNotFoundException('Section not found.');
        }

        return $this->sections->findStudents($id);
    }
}
