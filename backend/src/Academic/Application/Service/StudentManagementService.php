<?php

declare(strict_types=1);

namespace Kampusly\Academic\Application\Service;

use Kampusly\Academic\Domain\Entity\StudentProfile;
use Kampusly\Academic\Domain\Repository\SectionRepositoryInterface;
use Kampusly\Academic\Domain\Repository\StudentRepositoryInterface;
use Kampusly\IdentityAccess\Domain\Entity\User;
use Kampusly\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use Kampusly\IdentityAccess\Domain\Service\PasswordHasher;
use Kampusly\IdentityAccess\Domain\ValueObject\AccountStatus;
use Kampusly\IdentityAccess\Domain\ValueObject\UserRole;
use Kampusly\Shared\Application\Contract\AuditLoggerInterface;
use Kampusly\Shared\Application\Contract\TransactionManagerInterface;
use Kampusly\Shared\Application\DTO\PaginatedResult;
use Kampusly\Shared\Domain\Exception\ConflictException;
use Kampusly\Shared\Domain\Exception\ResourceNotFoundException;
use Kampusly\Shared\Domain\ValueObject\PaginationQuery;
use Kampusly\Shared\Infrastructure\Http\RequestValidator;

final class StudentManagementService
{
    private StudentRepositoryInterface $studentRepository;
    private SectionRepositoryInterface $sectionRepository;
    private UserRepositoryInterface $userRepository;
    private PasswordHasher $passwordHasher;
    private TransactionManagerInterface $transactions;
    private AuditLoggerInterface $auditLogger;

    public function __construct(
        StudentRepositoryInterface $studentRepository,
        SectionRepositoryInterface $sectionRepository,
        UserRepositoryInterface $userRepository,
        PasswordHasher $passwordHasher,
        TransactionManagerInterface $transactions,
        AuditLoggerInterface $auditLogger
    ) {
        $this->studentRepository = $studentRepository;
        $this->sectionRepository = $sectionRepository;
        $this->userRepository = $userRepository;
        $this->passwordHasher = $passwordHasher;
        $this->transactions = $transactions;
        $this->auditLogger = $auditLogger;
    }

    /**
     * @param array<string, mixed> $query
     */
    public function listStudents(array $query): PaginatedResult
    {
        $validator = new RequestValidator($query);
        $validator
            ->optionalIn('status', AccountStatus::values())
            ->optionalInt('section_id', 1)
            ->optionalString('q', 120)
            ->validateOrThrow();

        $pagination = PaginationQuery::fromQuery($query);
        $sectionId = isset($query['section_id']) ? (int) $query['section_id'] : null;
        $status = isset($query['status']) && is_string($query['status']) ? $query['status'] : null;
        $search = isset($query['q']) && is_string($query['q']) ? $query['q'] : null;

        return $this->studentRepository->paginate($pagination, $sectionId, $status, $search);
    }

    /**
     * @return array<string, mixed>
     */
    public function getStudent(int $id): array
    {
        $student = $this->studentRepository->findById($id);
        if ($student === null) {
            throw new ResourceNotFoundException('Student record not found.');
        }

        return $student->toArray();
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createStudent(array $payload, ?int $actorUserId = null, ?string $requestId = null): array
    {
        $validator = new RequestValidator($payload);
        $validator
            ->requireString('student_number', 3, 32)
            ->requireString('first_name', 2, 80)
            ->requireString('last_name', 2, 80)
            ->requireEmail('email')
            ->requireString('program', 2, 120)
            ->requireInt('section_id', 1)
            ->optionalString('password', 8, 128)
            ->optionalIn('status', AccountStatus::values())
            ->validateOrThrow();

        $studentNumber = strtoupper(trim((string) $payload['student_number']));
        if ($this->studentRepository->findByStudentNumber($studentNumber) !== null) {
            throw new ConflictException(
                sprintf('A student with number "%s" already exists.', $studentNumber),
                'DUPLICATE_STUDENT_NUMBER'
            );
        }

        $email = strtolower(trim((string) $payload['email']));
        if ($this->userRepository->findByEmail($email) !== null) {
            throw new ConflictException(
                sprintf('A user with email "%s" already exists.', $email),
                'DUPLICATE_EMAIL'
            );
        }

        $sectionId = (int) $payload['section_id'];
        $section = $this->sectionRepository->findById($sectionId);
        if ($section === null) {
            throw new ResourceNotFoundException('Assigned section does not exist.');
        }

        $rawPassword = isset($payload['password']) && is_string($payload['password']) && $payload['password'] !== ''
            ? (string) $payload['password']
            : 'ChangeMe123!';

        $passwordErrors = $this->passwordHasher->validatePolicy($rawPassword);
        if ($passwordErrors !== []) {
            throw new \Kampusly\Shared\Domain\Exception\ValidationException(['password' => $passwordErrors]);
        }

        $passwordHash = $this->passwordHasher->hash($rawPassword);
        $fullName = trim((string) $payload['first_name'] . ' ' . (string) $payload['last_name']);
        $accountStatus = AccountStatus::from((string) ($payload['status'] ?? 'active'));

        /** @var StudentProfile $createdStudent */
        $createdStudent = $this->transactions->transactional(function () use (
            $fullName,
            $email,
            $passwordHash,
            $accountStatus,
            $sectionId,
            $studentNumber,
            $payload,
            $actorUserId,
            $requestId
        ): StudentProfile {
            $user = new User(
                null,
                $fullName,
                $email,
                $passwordHash,
                UserRole::Student,
                $accountStatus
            );
            $persistedUser = $this->userRepository->create($user);

            $student = new StudentProfile(
                null,
                (int) $persistedUser->getId(),
                $sectionId,
                $studentNumber,
                (string) $payload['first_name'],
                (string) $payload['last_name'],
                (string) $payload['program']
            );
            $persistedStudent = $this->studentRepository->create($student);

            $this->auditLogger->record(
                $actorUserId,
                'student.created',
                'student',
                $persistedStudent->getId(),
                [
                    'student_number' => $persistedStudent->getStudentNumber(),
                    'user_id' => $persistedUser->getId(),
                    'email' => $email,
                ],
                $requestId
            );

            return $persistedStudent;
        });

        return $this->getStudent((int) $createdStudent->getId());
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function updateStudent(int $id, array $payload, ?int $actorUserId = null, ?string $requestId = null): array
    {
        $student = $this->studentRepository->findById($id);
        if ($student === null) {
            throw new ResourceNotFoundException('Student record not found.');
        }

        $validator = new RequestValidator($payload);
        $validator
            ->requireString('student_number', 3, 32)
            ->requireString('first_name', 2, 80)
            ->requireString('last_name', 2, 80)
            ->requireEmail('email')
            ->requireString('program', 2, 120)
            ->requireInt('section_id', 1)
            ->validateOrThrow();

        $newStudentNumber = strtoupper(trim((string) $payload['student_number']));
        $existingNumberStudent = $this->studentRepository->findByStudentNumber($newStudentNumber);
        if ($existingNumberStudent !== null && $existingNumberStudent->getId() !== $id) {
            throw new ConflictException(
                sprintf('A student with number "%s" already exists.', $newStudentNumber),
                'DUPLICATE_STUDENT_NUMBER'
            );
        }

        $newEmail = strtolower(trim((string) $payload['email']));
        $existingEmailUser = $this->userRepository->findByEmail($newEmail);
        if ($existingEmailUser !== null && $existingEmailUser->getId() !== $student->getUserId()) {
            throw new ConflictException(
                sprintf('A user with email "%s" already exists.', $newEmail),
                'DUPLICATE_EMAIL'
            );
        }

        $sectionId = (int) $payload['section_id'];
        $section = $this->sectionRepository->findById($sectionId);
        if ($section === null) {
            throw new ResourceNotFoundException('Assigned section does not exist.');
        }

        $fullName = trim((string) $payload['first_name'] . ' ' . (string) $payload['last_name']);

        $this->transactions->transactional(function () use (
            $student,
            $newStudentNumber,
            $newEmail,
            $fullName,
            $sectionId,
            $payload,
            $actorUserId,
            $requestId
        ): void {
            $updatedStudent = new StudentProfile(
                $student->getId(),
                $student->getUserId(),
                $sectionId,
                $newStudentNumber,
                (string) $payload['first_name'],
                (string) $payload['last_name'],
                (string) $payload['program']
            );
            $this->studentRepository->update($updatedStudent);

            $this->userRepository->updateProfile($student->getUserId(), $fullName, $newEmail);

            $this->auditLogger->record(
                $actorUserId,
                'student.updated',
                'student',
                $student->getId(),
                [
                    'student_number' => $newStudentNumber,
                    'email' => $newEmail,
                ],
                $requestId
            );
        });

        return $this->getStudent($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function updateStatus(int $id, string $status, ?int $actorUserId = null, ?string $requestId = null): array
    {
        $student = $this->studentRepository->findById($id);
        if ($student === null) {
            throw new ResourceNotFoundException('Student record not found.');
        }

        if (!in_array($status, AccountStatus::values(), true)) {
            throw new \Kampusly\Shared\Domain\Exception\ValidationException([
                'status' => ['Invalid account status.'],
            ]);
        }

        $targetStatus = AccountStatus::from($status);
        $this->userRepository->updateStatus($student->getUserId(), $targetStatus);

        $this->auditLogger->record(
            $actorUserId,
            'student.status_updated',
            'student',
            $id,
            ['status' => $status],
            $requestId
        );

        return $this->getStudent($id);
    }
}
