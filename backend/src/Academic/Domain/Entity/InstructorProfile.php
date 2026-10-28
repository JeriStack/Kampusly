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

    public function __construct(
        ?int $id,
        int $userId,
        string $employeeNumber,
        string $firstName,
        string $lastName,
        string $department
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->employeeNumber = strtoupper(trim($employeeNumber));
        $this->firstName = trim($firstName);
        $this->lastName = trim($lastName);
        $this->department = trim($department);
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

    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
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
            'department' => $this->department,
        ];
    }
}

