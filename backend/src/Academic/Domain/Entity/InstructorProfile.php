<?php

declare(strict_types=1);

namespace Kampusly\Academic\Domain\Entity;

final class InstructorProfile
{
    private ?int $id;
    private int $userId;
    private string $employeeNumber;
    private string $firstName;
    private string $lastName;
    private string $department;
    private ?string $email;
    private ?string $status;
    private ?string $createdAt;
    private ?string $updatedAt;

    public function __construct(
        ?int $id,
        int $userId,
        string $employeeNumber,
        string $firstName,
        string $lastName,
        string $department,
        ?string $email = null,
        ?string $status = 'active',
        ?string $createdAt = null,
        ?string $updatedAt = null
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->employeeNumber = strtoupper(trim($employeeNumber));
        $this->firstName = trim($firstName);
        $this->lastName = trim($lastName);
        $this->department = trim($department);
        $this->email = $email !== null ? strtolower(trim($email)) : null;
        $this->status = $status !== null ? trim($status) : 'active';
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
            isset($row['user_id']) ? (int) $row['user_id'] : 0,
            (string) ($row['employee_number'] ?? ''),
            (string) ($row['first_name'] ?? ''),
            (string) ($row['last_name'] ?? ''),
            (string) ($row['department'] ?? ''),
            isset($row['email']) ? (string) $row['email'] : null,
            isset($row['status']) ? (string) $row['status'] : 'active',
            isset($row['created_at']) ? (string) $row['created_at'] : null,
            isset($row['updated_at']) ? (string) $row['updated_at'] : null
        );
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getEmployeeNumber(): string
    {
        return $this->employeeNumber;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    public function getDepartment(): string
    {
        return $this->department;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?string
    {
        return $this->updatedAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'employee_number' => $this->employeeNumber,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'full_name' => $this->getFullName(),
            'department' => $this->department,
            'email' => $this->email,
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
