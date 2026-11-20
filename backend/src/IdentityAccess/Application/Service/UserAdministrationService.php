<?php

declare(strict_types=1);

namespace Kampusly\IdentityAccess\Application\Service;

use Kampusly\IdentityAccess\Domain\Entity\User;
use Kampusly\IdentityAccess\Domain\Policy\AdminPolicy;
use Kampusly\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use Kampusly\IdentityAccess\Domain\Service\PasswordHasher;
use Kampusly\IdentityAccess\Domain\ValueObject\AccountStatus;
use Kampusly\IdentityAccess\Domain\ValueObject\UserRole;
use Kampusly\Shared\Application\Contract\AuditLoggerInterface;
use Kampusly\Shared\Application\DTO\PaginatedResult;
use Kampusly\Shared\Domain\Exception\ConflictException;
use Kampusly\Shared\Domain\Exception\ForbiddenException;
use Kampusly\Shared\Domain\Exception\ResourceNotFoundException;
use Kampusly\Shared\Domain\ValueObject\PaginationQuery;
use Kampusly\Shared\Infrastructure\Http\RequestValidator;

final class UserAdministrationService
{
    private UserRepositoryInterface $userRepository;
    private PasswordHasher $passwordHasher;
    private AdminPolicy $adminPolicy;
    private AuditLoggerInterface $auditLogger;

    public function __construct(
        UserRepositoryInterface $userRepository,
        PasswordHasher $passwordHasher,
        AdminPolicy $adminPolicy,
        AuditLoggerInterface $auditLogger
    ) {
        $this->userRepository = $userRepository;
        $this->passwordHasher = $passwordHasher;
        $this->adminPolicy = $adminPolicy;
        $this->auditLogger = $auditLogger;
    }

    /**
     * @param array<string, mixed> $query
     */
    public function listUsers(array $query, User $caller): PaginatedResult
    {
        if (!$this->adminPolicy->canManageUsers($caller)) {
            throw new ForbiddenException();
        }

        $validator = new RequestValidator($query);
        $validator
            ->optionalIn('role', UserRole::values())
            ->optionalIn('status', AccountStatus::values())
            ->optionalString('q', 120)
            ->validateOrThrow();

        $pagination = PaginationQuery::fromQuery($query);
        $role = isset($query['role']) && is_string($query['role']) ? $query['role'] : null;
        $status = isset($query['status']) && is_string($query['status']) ? $query['status'] : null;
        $search = isset($query['q']) && is_string($query['q']) ? $query['q'] : null;

        return $this->userRepository->paginate($pagination, $role, $status, $search);
    }

    /**
     * @return array<string, mixed>
     */
    public function getUser(int $id, User $caller): array
    {
        $isSelf = $caller->getId() === $id;
        if (!$isSelf && !$this->adminPolicy->canManageUsers($caller)) {
            throw new ForbiddenException();
        }

        $user = $this->userRepository->findById($id);
        if ($user === null) {
            throw new ResourceNotFoundException('User not found.');
        }

        $data = $user->toArray();
        if ($user->getRole() === UserRole::Student) {
            $data['student_profile'] = $this->userRepository->findStudentProfile($id);
        } elseif ($user->getRole() === UserRole::Instructor) {
            $data['instructor_profile'] = $this->userRepository->findInstructorProfile($id);
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createUser(array $payload, User $caller, ?string $requestId = null): array
    {
        if (!$this->adminPolicy->canManageUsers($caller)) {
            throw new ForbiddenException();
        }

        $validator = new RequestValidator($payload);
        $validator
            ->requireString('full_name', 2, 120)
            ->requireEmail('email')
            ->requireString('password', 8, 128)
            ->requireIn('role', UserRole::values())
            ->optionalIn('status', AccountStatus::values())
            ->validateOrThrow();

        $policyErrors = $this->passwordHasher->validatePolicy((string) $payload['password']);
        if ($policyErrors !== []) {
            throw new \Kampusly\Shared\Domain\Exception\ValidationException(['password' => $policyErrors]);
        }

        $email = strtolower(trim((string) $payload['email']));
        if ($this->userRepository->findByEmail($email) !== null) {
            throw new ConflictException(
                sprintf('A user with email "%s" already exists.', $email),
                'DUPLICATE_EMAIL'
            );
        }

        $user = new User(
            null,
            (string) $payload['full_name'],
            $email,
            $this->passwordHasher->hash((string) $payload['password']),
            UserRole::from((string) $payload['role']),
            AccountStatus::from((string) ($payload['status'] ?? 'active'))
        );

        $created = $this->userRepository->create($user);

        $this->auditLogger->record(
            $caller->getId(),
            'user.created',
            'user',
            $created->getId(),
            [
                'email' => $created->getEmail(),
                'role' => $created->getRole()->value,
            ],
            $requestId
        );

        return $created->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function updateUserStatus(
        int $targetUserId,
        string $newStatus,
        User $caller,
        ?string $requestId = null
    ): array {
        $targetUser = $this->userRepository->findById($targetUserId);
        if ($targetUser === null) {
            throw new ResourceNotFoundException('User not found.');
        }

        if (!$this->adminPolicy->canChangeUserStatus($caller, $targetUser)) {
            if ($caller->getId() === $targetUserId) {
                throw new ForbiddenException('Administrators cannot deactivate their own account.');
            }
            throw new ForbiddenException();
        }

        $statusEnum = AccountStatus::from($newStatus);
        $this->userRepository->updateStatus($targetUserId, $statusEnum);

        $this->auditLogger->record(
            $caller->getId(),
            'user.status_updated',
            'user',
            $targetUserId,
            ['previous_status' => $targetUser->getStatus()->value, 'new_status' => $statusEnum->value],
            $requestId
        );

        $updated = $this->userRepository->findById($targetUserId);
        return $updated !== null ? $updated->toArray() : [];
    }

    /**
     * Replaces a student's or instructor's password with a new generated one.
     * The plain password is returned once and never stored or logged.
     *
     * @return array<string, mixed>
     */
    public function resetUserPassword(int $targetUserId, User $caller, ?string $requestId = null): array
    {
        if (!$this->adminPolicy->canManageUsers($caller)) {
            throw new ForbiddenException();
        }

        $target = $this->userRepository->findById($targetUserId);
        if ($target === null) {
            throw new ResourceNotFoundException('User not found.');
        }

        if ($caller->getId() === $targetUserId) {
            throw new ForbiddenException('Use the change password option for your own account.');
        }
        if ($target->getRole() === UserRole::Admin) {
            throw new ForbiddenException('Administrator passwords cannot be reset from here.');
        }

        $plain = $this->passwordHasher->generate();
        $this->userRepository->updatePassword($targetUserId, $this->passwordHasher->hash($plain));

        $this->auditLogger->record(
            $caller->getId(),
            'user.password_reset',
            'user',
            $targetUserId,
            ['email' => $target->getEmail(), 'role' => $target->getRole()->value],
            $requestId
        );

        $data = $target->toArray();
        $data['generated_password'] = $plain;

        return $data;
    }
}

