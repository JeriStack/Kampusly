<?php

declare(strict_types=1);

namespace Kampusly\Academic\Domain\Entity;

final class StudentProfile
{
    private ?int $id;
    private int $userId;
    private int $sectionId;
    private string $studentNumber;
    private string $firstName;
    private string $lastName;
    private string $program;
    private ?string $email;
    private ?string $status;
    private ?string $sectionName;
    private ?string $createdAt;
    private ?string $updatedAt;

    public function __construct(
        ?int $id,
        int $userId,
        int $sectionId,
        string $studentNumber,
        string $firstName,
        string $lastName,
        string $program,
        ?string $email = null,
        ?string $status = 'active',
        ?string $sectionName = null,
        ?string $createdAt = null,
        ?string $updatedAt = null
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->sectionId = $sectionId;
        $this->studentNumber = strtoupper(trim($studentNumber));
        $this->firstName = trim($firstName);
        $this->lastName = trim($lastName);
        $this->program = trim($program);
        $this->email = $email !== null ? strtolower(trim($email)) : null;
        $this->status = $status !== null ? trim($status) : 'active';
        $this->sectionName = $sectionName !== null ? trim($sectionName) : null;
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
            isset($row['section_id']) ? (int) $row['section_id'] : 0,
            (string) ($row['student_number'] ?? ''),
            (string) ($row['first_name'] ?? ''),
            (string) ($row['last_name'] ?? ''),
            (string) ($row['program'] ?? ''),
            isset($row['email']) ? (string) $row['email'] : null,
            isset($row['status']) ? (string) $row['status'] : 'active',
            isset($row['section_name']) ? (string) $row['section_name'] : null,
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

    public function getSectionId(): int
    {
        return $this->sectionId;
    }

    public function getStudentNumber(): string
    {
        return $this->studentNumber;
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

    public function getProgram(): string
    {
        return $this->program;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function getSectionName(): ?string
    {
        return $this->sectionName;
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
            'section_id' => $this->sectionId,
            'student_number' => $this->studentNumber,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'full_name' => $this->getFullName(),
            'program' => $this->program,
            'email' => $this->email,
            'status' => $this->status,
            'section_name' => $this->sectionName,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
