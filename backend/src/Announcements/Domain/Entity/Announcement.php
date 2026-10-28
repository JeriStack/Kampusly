<?php

declare(strict_types=1);

namespace Kampusly\Announcements\Domain\Entity;

use Kampusly\Announcements\Domain\ValueObject\AnnouncementStatus;

final class Announcement
{
    private ?int $id;
    private int $authorUserId;
    private ?int $instructorId;
    private int $subjectId;
    private ?int $sectionId;
    private string $title;
    private string $message;
    private AnnouncementStatus $status;
    private ?string $publishedAt;
    private ?string $createdAt;
    private ?string $updatedAt;

    public function __construct(
        ?int $id,
        int $authorUserId,
        ?int $instructorId,
        int $subjectId,
        ?int $sectionId,
        string $title,
        string $message,
        AnnouncementStatus $status = AnnouncementStatus::Published,
        ?string $publishedAt = null,
        ?string $createdAt = null,
        ?string $updatedAt = null
    ) {
        $this->id = $id;
        $this->authorUserId = $authorUserId;
        $this->instructorId = $instructorId;
        $this->subjectId = $subjectId;
        $this->sectionId = $sectionId;
        $this->title = trim($title);
        $this->message = trim($message);
        $this->status = $status;
        $this->publishedAt = $publishedAt;
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
            (int) ($row['author_user_id'] ?? 0),
            isset($row['instructor_id']) && $row['instructor_id'] !== null ? (int) $row['instructor_id'] : null,
            (int) ($row['subject_id'] ?? 0),
            isset($row['section_id']) && $row['section_id'] !== null ? (int) $row['section_id'] : null,
            (string) ($row['title'] ?? ''),
            (string) ($row['message'] ?? ''),
            AnnouncementStatus::from((string) ($row['status'] ?? 'published')),
            isset($row['published_at']) && $row['published_at'] !== null ? (string) $row['published_at'] : null,
            isset($row['created_at']) ? (string) $row['created_at'] : null,
            isset($row['updated_at']) ? (string) $row['updated_at'] : null
        );
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAuthorUserId(): int
    {
        return $this->authorUserId;
    }

    public function getInstructorId(): ?int
    {
        return $this->instructorId;
    }

    public function getSubjectId(): int
    {
        return $this->subjectId;
    }

    public function getSectionId(): ?int
    {
        return $this->sectionId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getStatus(): AnnouncementStatus
    {
        return $this->status;
    }

    public function getPublishedAt(): ?string
    {
        return $this->publishedAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'author_user_id' => $this->authorUserId,
            'instructor_id' => $this->instructorId,
            'subject_id' => $this->subjectId,
            'section_id' => $this->sectionId,
            'title' => $this->title,
            'message' => $this->message,
            'status' => $this->status->value,
            'published_at' => $this->publishedAt,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}

