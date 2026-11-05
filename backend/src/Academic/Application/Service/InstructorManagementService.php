<?php

declare(strict_types=1);

namespace Kampusly\Academic\Application\Service;

use Kampusly\Academic\Domain\Entity\InstructorProfile;
use Kampusly\Academic\Domain\Repository\InstructorRepositoryInterface;
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

final class InstructorManagementService
{
    private InstructorRepositoryInterface $instructorRepository;
    private UserRepositoryInterface $userRepository;
    private PasswordHasher $passwordHasher;
    private TransactionManagerInterface $transactions;
    private AuditLoggerInterface $auditLogger;

    public function __construct(
        InstructorRepositoryInterface $instructorRepository,
        UserRepositoryInterface $userRepository,
        PasswordHasher $passwordHasher,
        TransactionManagerInterface $transactions,
        AuditLoggerInterface $auditLogger
    ) {
        $this->instructorRepository = $instructorRepository;
        $this->userRepository = $userRepository;
        $this->passwordHasher = $passwordHasher;
        $this->transactions = $transactions;
        $this->auditLogger = $auditLogger;
    }

    /**
     * @param array<string, mixed> $query
     */
    public function listInstructors(array $query): PaginatedResult
    {
        $validator = new RequestValidator($query);
        $validator
            ->optionalIn('status', AccountStatus::values())
            ->optionalString('department', 120)
            ->optionalString('q', 120)
            ->validateOrThrow();

        $pagination = PaginationQuery::fromQuery($query);
        $department = isset($query['department']) && is_string($query['department']) ? $query['department'] : null;
        $status = isset($query['status']) && is_string($query['status']) ? $query['status'] : null;
        $search = isset($query['q']) && is_string($query['q']) ? $query['q'] : null;

        return $this->instructorRepository->paginate($pagination, $department, $status, $search);
    }

    /**
     * @return array<string, mixed>
     */
    public function getInstructor(int $id): array
    {
        $instructor = $this->instructorRepository->findById($id);
        if ($instructor === null) {
            throw new ResourceNotFoundException('Instructor record not found.');
        }

        return $instructor->toArray();
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createInstructor(array $payload, ?int $actorUserId = null, ?string $requestId = null): array
    {
        $validator = new RequestValidator($payload);
        $validator
            ->requireString('employee_number', 3, 32)
            ->requireString('first_name', 2, 80)
            ->requireString('last_name', 2, 80)
            ->requireEmail('email')
            ->requireString('department', 2, 120)
            ->optionalString('password', 128)
            ->optionalIn('status', AccountStatus::values())
            ->validateOrThrow();

        $employeeNumber = strtoupper(trim((string) $payload['employee_number']));
        if ($this->instructorRepository->findByEmployeeNumber($employeeNumber) !== null) {
            throw new ConflictException(
                sprintf('An instructor with employee number "%s" already exists.', $employeeNumber),
                'DUPLICATE_EMPLOYEE_NUMBER'
            );
        }

        $email = strtolower(trim((string) $payload['email']));
        if ($this->userRepository->findByEmail($email) !== null) {
            throw new ConflictException(
                sprintf('A user with email "%s" already exists.', $email),
                'DUPLICATE_EMAIL'
            );
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

        /** @var InstructorProfile $createdInstructor */
        $createdInstructor = $this->transactions->transactional(function () use (
            $fullName,
            $email,
            $passwordHash,
            $accountStatus,
            $employeeNumber,
            $payload,
            $actorUserId,
            $requestId
        ): InstructorProfile {
            $user = new User(
                null,
                $fullName,
                $email,
                $passwordHash,
                UserRole::Instructor,
                $accountStatus
            );
            $persistedUser = $this->userRepository->create($user);

            $instructor = new InstructorProfile(
                null,
                (int) $persistedUser->getId(),
                $employeeNumber,
                (string) $payload['first_name'],
                (string) $payload['last_name'],
                (string) $payload['department']
            );
            $persistedInstructor = $this->instructorRepository->create($instructor);

            $this->auditLogger->record(
                $actorUserId,
                'instructor.created',
                'instructor',
                $persistedInstructor->getId(),
                [
                    'employee_number' => $persistedInstructor->getEmployeeNumber(),
                    'user_id' => $persistedUser->getId(),
                    'email' => $email,
                ],
                $requestId
            );

            return $persistedInstructor;
        });

        return $this->getInstructor((int) $createdInstructor->getId());
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function updateInstructor(int $id, array $payload, ?int $actorUserId = null, ?string $requestId = null): array
    {
        $instructor = $this->instructorRepository->findById($id);
        if ($instructor === null) {
            throw new ResourceNotFoundException('Instructor record not found.');
        }

        $validator = new RequestValidator($payload);
        $validator
            ->requireString('employee_number', 3, 32)
            ->requireString('first_name', 2, 80)
            ->requireString('last_name', 2, 80)
            ->requireEmail('email')
            ->requireString('department', 2, 120)
            ->validateOrThrow();

        $newEmployeeNumber = strtoupper(trim((string) $payload['employee_number']));
        $existingNumberInstructor = $this->instructorRepository->findByEmployeeNumber($newEmployeeNumber);
        if ($existingNumberInstructor !== null && $existingNumberInstructor->getId() !== $id) {
            throw new ConflictException(
                sprintf('An instructor with employee number "%s" already exists.', $newEmployeeNumber),
                'DUPLICATE_EMPLOYEE_NUMBER'
            );
        }

        $newEmail = strtolower(trim((string) $payload['email']));
        $existingEmailUser = $this->userRepository->findByEmail($newEmail);
        if ($existingEmailUser !== null && $existingEmailUser->getId() !== $instructor->getUserId()) {
            throw new ConflictException(
                sprintf('A user with email "%s" already exists.', $newEmail),
                'DUPLICATE_EMAIL'
            );
        }

        $fullName = trim((string) $payload['first_name'] . ' ' . (string) $payload['last_name']);

        $this->transactions->transactional(function () use (
            $instructor,
            $newEmployeeNumber,
            $newEmail,
            $fullName,
            $payload,
            $actorUserId,
            $requestId
        ): void {
            $updatedInstructor = new InstructorProfile(
                $instructor->getId(),
                $instructor->getUserId(),
                $newEmployeeNumber,
                (string) $payload['first_name'],
                (string) $payload['last_name'],
                (string) $payload['department']
            );
            $this->instructorRepository->update($updatedInstructor);

            $this->userRepository->updateProfile($instructor->getUserId(), $fullName, $newEmail);

            $this->auditLogger->record(
                $actorUserId,
                'instructor.updated',
                'instructor',
                $instructor->getId(),
                [
                    'employee_number' => $newEmployeeNumber,
                    'email' => $newEmail,
                ],
                $requestId
            );
        });

        return $this->getInstructor($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function updateStatus(int $id, string $status, ?int $actorUserId = null, ?string $requestId = null): array
    {
        $instructor = $this->instructorRepository->findById($id);
        if ($instructor === null) {
            throw new ResourceNotFoundException('Instructor record not found.');
        }

        if (!in_array($status, AccountStatus::values(), true)) {
            throw new \Kampusly\Shared\Domain\Exception\ValidationException([
                'status' => ['Invalid account status.'],
            ]);
        }

        $targetStatus = AccountStatus::from($status);
        $this->userRepository->updateStatus($instructor->getUserId(), $targetStatus);

        $this->auditLogger->record(
            $actorUserId,
            'instructor.status_updated',
            'instructor',
            $id,
            ['status' => $status],
            $requestId
        );

        return $this->getInstructor($id);
    }
}
