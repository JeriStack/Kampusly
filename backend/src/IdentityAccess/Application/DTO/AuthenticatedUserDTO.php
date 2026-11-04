<?php

declare(strict_types=1);

namespace Kampusly\IdentityAccess\Application\DTO;

use Kampusly\IdentityAccess\Domain\Entity\User;

final class AuthenticatedUserDTO
{
    private int $id;
    private string $fullName;
    private string $email;
    private string $role;
    private string $status;
    /** @var array<string, mixed>|null */
    private ?array $studentProfile;
    /** @var array<string, mixed>|null */
    private ?array $instructorProfile;
    private string $csrfToken;

    /**
     * @param array<string, mixed>|null $studentProfile
     * @param array<string, mixed>|null $instructorProfile
     */
    public function __construct(
        int $id,
        string $fullName,
        string $email,
        string $role,
        string $status,
        ?array $studentProfile,
        ?array $instructorProfile,
        string $csrfToken
    ) {
        $this->id = $id;
        $this->fullName = $fullName;
        $this->email = $email;
        $this->role = $role;
        $this->status = $status;
        $this->studentProfile = $studentProfile;
        $this->instructorProfile = $instructorProfile;
        $this->csrfToken = $csrfToken;
    }

    /**
     * @param array<string, mixed>|null $studentProfile
     * @param array<string, mixed>|null $instructorProfile
     */
    public static function fromUser(
        User $user,
        string $csrfToken,
        ?array $studentProfile = null,
        ?array $instructorProfile = null
    ): self {
        return new self(
            $user->getId() ?? 0,
            $user->getFullName(),
            $user->getEmail(),
            $user->getRole()->value,
            $user->getStatus()->value,
            $studentProfile,
            $instructorProfile,
            $csrfToken
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function getCsrfToken(): string
    {
        return $this->csrfToken;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->fullName,
            'email' => $this->email,
            'role' => $this->role,
            'status' => $this->status,
            'student_profile' => $this->studentProfile,
            'instructor_profile' => $this->instructorProfile,
            'csrf_token' => $this->csrfToken,
        ];
    }
}

