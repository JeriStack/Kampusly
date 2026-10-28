<?php

declare(strict_types=1);

namespace Kampusly\Academic\Domain\Entity;

use Kampusly\Academic\Domain\ValueObject\ReferenceStatus;

final class Subject
{
    private ?int $id;
    private string $subjectCode;
    private string $subjectName;
    private ?string $description;
    private int $units;
    private ReferenceStatus $status;
    private ?string $createdAt;
    private ?string $updatedAt;

    public function __construct(
        ?int $id,
        string $subjectCode,
        string $subjectName,
        ?string $description,
        int $units = 3,
        ReferenceStatus $status = ReferenceStatus::Active,
        ?string $createdAt = null,
        ?string $updatedAt = null
    ) {
        $this->id = $id;
        $this->subjectCode = strtoupper(trim($subjectCode));
        $this->subjectName = trim($subjectName);
        $this->description = $description !== null && trim($description) !== '' ? trim($description) : null;
        $this->units = $units;
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
            (string) ($row['subject_code'] ?? ''),
            (string) ($row['subject_name'] ?? ''),
            isset($row['description']) ? (string) $row['description'] : null,
            isset($row['units']) ? (int) $row['units'] : 3,
            ReferenceStatus::from((string) ($row['status'] ?? 'active')),
            isset($row['created_at']) ? (string) $row['created_at'] : null,
            isset($row['updated_at']) ? (string) $row['updated_at'] : null
        );
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSubjectCode(): string
    {
        return $this->subjectCode;
    }

    public function getSubjectName(): string
    {
        return $this->subjectName;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getUnits(): int
    {
        return $this->units;
    }

    public function getStatus(): ReferenceStatus
    {
        return $this->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'subject_code' => $this->subjectCode,
            'subject_name' => $this->subjectName,
            'description' => $this->description,
            'units' => $this->units,
            'status' => $this->status->value,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}

