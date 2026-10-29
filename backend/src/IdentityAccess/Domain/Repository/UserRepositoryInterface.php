<?php

declare(strict_types=1);

namespace Kampusly\IdentityAccess\Domain\Repository;

use Kampusly\IdentityAccess\Domain\Entity\User;
use Kampusly\IdentityAccess\Domain\ValueObject\AccountStatus;
use Kampusly\Shared\Application\DTO\PaginatedResult;
use Kampusly\Shared\Domain\ValueObject\PaginationQuery;

interface UserRepositoryInterface
{
    public function findById(int $id): ?User;

    public function findByEmail(string $email): ?User;

    public function paginate(
        PaginationQuery $pagination,
        ?string $role = null,
        ?string $status = null,
        ?string $search = null
    ): PaginatedResult;

    public function create(User $user): User;

    public function updatePassword(int $userId, string $newPasswordHash): void;

    public function updateStatus(int $userId, AccountStatus $status): void;

    public function updateProfile(int $userId, string $fullName, string $email): void;

    public function updateLastLogin(int $userId): void;

    /**
     * @return array{id: int, student_number: string, section_id: int, section_name: string, program: string}|null
     */
    public function findStudentProfile(int $userId): ?array;

    /**
     * @return array{id: int, employee_number: string, department: string}|null
     */
    public function findInstructorProfile(int $userId): ?array;
}
