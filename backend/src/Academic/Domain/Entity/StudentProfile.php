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

    public function __construct(
        ?int $id,
        int $userId,
        int $sectionId,
        string $studentNumber,
        string $firstName,
        string $lastName,
        string $program
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->sectionId = $sectionId;
        $this->studentNumber = strtoupper(trim($studentNumber));
        $this->firstName = trim($firstName);
        $this->lastName = trim($lastName);
        $this->program = trim($program);
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
            'program' => $this->program,
        ];
    }
}

