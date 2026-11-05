<?php

declare(strict_types=1);

namespace Kampusly\Academic\Domain\Entity;

use Kampusly\Academic\Domain\ValueObject\ReferenceStatus;

final class Room
{
    private ?int $id;
    private string $roomCode;
    private string $roomName;
    private string $building;
    private int $capacity;
    private ReferenceStatus $status;
    private ?string $createdAt;
    private ?string $updatedAt;

    public function __construct(
        ?int $id,
        string $roomCode,
        string $roomName,
        string $building,
        int $capacity = 40,
        ReferenceStatus $status = ReferenceStatus::Active,
        ?string $createdAt = null,
        ?string $updatedAt = null
    ) {
        $this->id = $id;
        $this->roomCode = strtoupper(trim($roomCode));
        $this->roomName = trim($roomName);
        $this->building = trim($building);
        $this->capacity = $capacity;
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
            (string) ($row['room_code'] ?? ''),
            (string) ($row['room_name'] ?? ''),
            (string) ($row['building'] ?? ''),
            isset($row['capacity']) ? (int) $row['capacity'] : 40,
            ReferenceStatus::from((string) ($row['status'] ?? 'active')),
            isset($row['created_at']) ? (string) $row['created_at'] : null,
            isset($row['updated_at']) ? (string) $row['updated_at'] : null
        );
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRoomCode(): string
    {
        return $this->roomCode;
    }

    public function getRoomName(): string
    {
        return $this->roomName;
    }

    public function getBuilding(): string
    {
        return $this->building;
    }

    public function getCapacity(): int
    {
        return $this->capacity;
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
            'room_code' => $this->roomCode,
            'room_name' => $this->roomName,
            'building' => $this->building,
            'capacity' => $this->capacity,
            'status' => $this->status->value,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}

