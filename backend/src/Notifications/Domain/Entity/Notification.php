<?php

declare(strict_types=1);

namespace Kampusly\Notifications\Domain\Entity;

use Kampusly\Notifications\Domain\ValueObject\NotificationType;

final class Notification
{
    private ?int $id;
    private int $userId;
    private string $title;
    private string $message;
    private NotificationType $type;
    private string $referenceType;
    private int $referenceId;
    private ?string $deduplicationKey;
    private ?string $readAt;
    private ?string $createdAt;

    public function __construct(
        ?int $id,
        int $userId,
        string $title,
        string $message,
        NotificationType $type,
        string $referenceType,
        int $referenceId,
        ?string $deduplicationKey = null,
        ?string $readAt = null,
        ?string $createdAt = null
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->title = trim($title);
        $this->message = trim($message);
        $this->type = $type;
        $this->referenceType = trim($referenceType);
        $this->referenceId = $referenceId;
        $this->deduplicationKey = $deduplicationKey !== null && trim($deduplicationKey) !== ''
            ? trim($deduplicationKey)
            : null;
        $this->readAt = $readAt;
        $this->createdAt = $createdAt;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromDatabaseRow(array $row): self
    {
        return new self(
            isset($row['id']) ? (int) $row['id'] : null,
            (int) ($row['user_id'] ?? 0),
            (string) ($row['title'] ?? ''),
            (string) ($row['message'] ?? ''),
            NotificationType::from((string) ($row['type'] ?? 'class_reminder')),
            (string) ($row['reference_type'] ?? ''),
            (int) ($row['reference_id'] ?? 0),
            isset($row['deduplication_key']) && $row['deduplication_key'] !== null
                ? (string) $row['deduplication_key']
                : null,
            isset($row['read_at']) && $row['read_at'] !== null ? (string) $row['read_at'] : null,
            isset($row['created_at']) ? (string) $row['created_at'] : null
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

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getType(): NotificationType
    {
        return $this->type;
    }

    public function getReferenceType(): string
    {
        return $this->referenceType;
    }

    public function getReferenceId(): int
    {
        return $this->referenceId;
    }

    public function getDeduplicationKey(): ?string
    {
        return $this->deduplicationKey;
    }

    public function isRead(): bool
    {
        return $this->readAt !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type->value,
            'reference_type' => $this->referenceType,
            'reference_id' => $this->referenceId,
            'deduplication_key' => $this->deduplicationKey,
            'read_at' => $this->readAt,
            'created_at' => $this->createdAt,
        ];
    }
}

