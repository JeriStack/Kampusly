<?php

declare(strict_types=1);

namespace Kampusly\Tests\Support;

use Kampusly\IdentityAccess\Domain\Entity\User;
use Kampusly\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use Kampusly\IdentityAccess\Domain\ValueObject\AccountStatus;

final class InMemoryUserRepository implements UserRepositoryInterface
{
    /** @var array<int, User> */
    private array $users = [];

    /** @var array<int, array<string, mixed>> */
    private array $studentProfiles = [];

    /** @var array<int, array<string, mixed>> */
    private array $instructorProfiles = [];

    private int $nextId = 1;

    /**
     * @param list<User> $initialUsers
     */
    public function __construct(array $initialUsers = [])
    {
        foreach ($initialUsers as $user) {
            $id = $user->getId() ?? $this->nextId++;
            $reflection = new \ReflectionClass($user);
            $prop = $reflection->getProperty('id');
            $prop->setValue($user, $id);

            $this->users[$id] = $user;
            if ($id >= $this->nextId) {
                $this->nextId = $id + 1;
            }
        }
    }

    public function setStudentProfile(int $userId, array $profile): void
    {
        $this->studentProfiles[$userId] = $profile;
    }

    public function setInstructorProfile(int $userId, array $profile): void
    {
        $this->instructorProfiles[$userId] = $profile;
    }

    public function findById(int $id): ?User
    {
        return $this->users[$id] ?? null;
    }

    public function findByEmail(string $email): ?User
    {
        $normalized = strtolower(trim($email));
        foreach ($this->users as $user) {
            if (strtolower($user->getEmail()) === $normalized) {
                return $user;
            }
        }

        return null;
    }

    public function paginate(
        \Kampusly\Shared\Domain\ValueObject\PaginationQuery $pagination,
        ?string $role = null,
        ?string $status = null,
        ?string $search = null
    ): \Kampusly\Shared\Application\DTO\PaginatedResult {
        $result = array_values($this->users);

        if ($role !== null && $role !== '') {
            $result = array_values(array_filter($result, static fn (User $u): bool => $u->getRole()->value === $role));
        }

        if ($status !== null && $status !== '') {
            $result = array_values(array_filter($result, static fn (User $u): bool => $u->getStatus()->value === $status));
        }

        if ($search !== null && $search !== '') {
            $q = strtolower($search);
            $result = array_values(array_filter(
                $result,
                static fn (User $u): bool => str_contains(strtolower($u->getName()), $q)
                    || str_contains(strtolower($u->getEmail()), $q)
            ));
        }

        $total = count($result);
        $offset = $pagination->getOffset();
        $limit = $pagination->getPerPage();
        $paged = array_slice($result, $offset, $limit);
        $items = array_map(static fn (User $u): array => $u->toArray(), $paged);

        return new \Kampusly\Shared\Application\DTO\PaginatedResult(
            $items,
            $pagination->getPage(),
            $pagination->getPerPage(),
            $total
        );
    }

    public function create(User $user): User
    {
        $id = $this->nextId++;
        $reflection = new \ReflectionClass($user);
        $prop = $reflection->getProperty('id');
        $prop->setValue($user, $id);

        $this->users[$id] = $user;

        return $user;
    }

    public function updatePassword(int $userId, string $newPasswordHash): void
    {
        $user = $this->findById($userId);
        if ($user !== null) {
            $reflection = new \ReflectionClass($user);
            $prop = $reflection->getProperty('passwordHash');
            $prop->setValue($user, $newPasswordHash);
        }
    }

    public function updateStatus(int $userId, AccountStatus $status): void
    {
        $user = $this->findById($userId);
        if ($user !== null) {
            $reflection = new \ReflectionClass($user);
            $prop = $reflection->getProperty('status');
            $prop->setValue($user, $status);
        }
    }

    public function updateProfile(int $userId, string $fullName, string $email): void
    {
        $user = $this->findById($userId);
        if ($user !== null) {
            $reflection = new \ReflectionClass($user);
            $propName = $reflection->getProperty('fullName');
            $propName->setValue($user, $fullName);
            $propEmail = $reflection->getProperty('email');
            $propEmail->setValue($user, $email);
        }
    }

    public function updateLastLogin(int $userId): void
    {
        $user = $this->findById($userId);
        if ($user !== null) {
            $reflection = new \ReflectionClass($user);
            $prop = $reflection->getProperty('lastLoginAt');
            $prop->setValue($user, date('Y-m-d H:i:s'));
        }
    }

    public function delete(int $id): void
    {
        unset($this->users[$id], $this->studentProfiles[$id], $this->instructorProfiles[$id]);
    }

    public function findStudentProfile(int $userId): ?array
    {
        return $this->studentProfiles[$userId] ?? null;
    }

    public function findInstructorProfile(int $userId): ?array
    {
        return $this->instructorProfiles[$userId] ?? null;
    }
}
