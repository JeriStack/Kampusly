<?php

declare(strict_types=1);

namespace Kampusly\IdentityAccess\Domain\Entity;

use Kampusly\IdentityAccess\Domain\ValueObject\AccountStatus;
use Kampusly\IdentityAccess\Domain\ValueObject\UserRole;

final class User
{
    private ?int $id;
    private string $fullName;
    private string $email;
    private string $passwordHash;
    private UserRole $role;
    private AccountStatus $status;
    private ?string $emailVerifiedAt;
    private ?string $lastLoginAt;
    private ?string $createdAt;
    private ?string $updatedAt;

    public function __construct(
        ?int $id,
        string $fullName,
        string $email,
        string $passwordHash,
        UserRole $role,
        AccountStatus $status = AccountStatus::Active,
        ?string $emailVerifiedAt = null,
        ?string $lastLoginAt = null,
        ?string $createdAt = null,
        ?string $updatedAt = null
    ) {
        $this->id = $id;
        $this->fullName = trim($fullName);
        $this->email = strtolower(trim($email));
        $this->passwordHash = $passwordHash;
        $this->role = $role;
        $this->status = $status;
        $this->emailVerifiedAt = $emailVerifiedAt;
        $this->lastLoginAt = $lastLoginAt;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromDatabaseRow(array $row): self
    {
        return new self(
            isset($row['id']) ? (int) $row['id'] : null,
            (string) ($row['full_name'] ?? ''),
            (string) ($row['email'] ?? ''),
            (string) ($row['password_hash'] ?? ''),
            UserRole::from((string) ($row['role'] ?? 'student')),
            AccountStatus::from((string) ($row['status'] ?? 'active')),
            isset($row['email_verified_at']) ? (string) $row['email_verified_at'] : null,
            isset($row['last_login_at']) ? (string) $row['last_login_at'] : null,
            isset($row['created_at']) ? (string) $row['created_at'] : null,
            isset($row['updated_at']) ? (string) $row['updated_at'] : null
        );
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function getRole(): UserRole
    {
        return $this->role;
    }

    public function getStatus(): AccountStatus
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === AccountStatus::Active;
    }

    public function verifyPassword(string $plainTextPassword): bool
    {
        return password_verify($plainTextPassword, $this->passwordHash);
    }

    /**
     * Safe public serialization that strictly omits password_hash.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->fullName,
            'email' => $this->email,
            'role' => $this->role->value,
            'status' => $this->status->value,
            'email_verified_at' => $this->emailVerifiedAt,
            'last_login_at' => $this->lastLoginAt,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}

