<?php

declare(strict_types=1);

namespace Kampusly\Academic\Domain\Entity;

use Kampusly\Academic\Domain\ValueObject\ReferenceStatus;
use Kampusly\Academic\Domain\ValueObject\Semester;

final class Section
{
    private ?int $id;
    private string $sectionName;
    private string $program;
    private int $yearLevel;
    private string $schoolYear;
    private Semester $semester;
    private ?string $termStartDate;
    private ReferenceStatus $status;
    private ?string $createdAt;
    private ?string $updatedAt;

    public function __construct(
        ?int $id,
        string $sectionName,
        string $program,
        int $yearLevel,
        string $schoolYear,
        Semester $semester,
        ReferenceStatus $status = ReferenceStatus::Active,
        ?string $createdAt = null,
        ?string $updatedAt = null,
        ?string $termStartDate = null
    ) {
        $this->id = $id;
        $this->sectionName = trim($sectionName);
        $this->program = trim($program);
        $this->yearLevel = $yearLevel;
        $this->schoolYear = trim($schoolYear);
        $this->semester = $semester;
        $this->termStartDate = $termStartDate;
        $this->status = $status;
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
            (string) ($row['section_name'] ?? ''),
            (string) ($row['program'] ?? ''),
            isset($row['year_level']) ? (int) $row['year_level'] : 1,
            (string) ($row['school_year'] ?? ''),
            Semester::from((string) ($row['semester'] ?? '1st')),
            ReferenceStatus::from((string) ($row['status'] ?? 'active')),
            isset($row['created_at']) ? (string) $row['created_at'] : null,
            isset($row['updated_at']) ? (string) $row['updated_at'] : null,
            isset($row['term_start_date']) && $row['term_start_date'] !== null
                ? (string) $row['term_start_date']
                : null
        );
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSectionName(): string
    {
        return $this->sectionName;
    }

    public function getProgram(): string
    {
        return $this->program;
    }

    public function getYearLevel(): int
    {
        return $this->yearLevel;
    }

    public function getSchoolYear(): string
    {
        return $this->schoolYear;
    }

    public function getSemester(): Semester
    {
        return $this->semester;
    }

    public function getStatus(): ReferenceStatus
    {
        return $this->status;
    }

    public function getTermStartDate(): ?string
    {
        return $this->termStartDate;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'section_name' => $this->sectionName,
            'program' => $this->program,
            'year_level' => $this->yearLevel,
            'school_year' => $this->schoolYear,
            'semester' => $this->semester->value,
            'term_start_date' => $this->termStartDate,
            'status' => $this->status->value,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
