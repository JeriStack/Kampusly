<?php

declare(strict_types=1);

namespace Kampusly\ClassNotices\Domain\Entity;

use Kampusly\ClassNotices\Domain\ValueObject\NoticeStatus;
use Kampusly\ClassNotices\Domain\ValueObject\NoticeType;

final class ClassNotice
{
    private ?int $id;
    private int $scheduleId;
    private int $instructorId;
    private NoticeType $noticeType;
    private string $affectedDate;
    private string $title;
    private string $message;
    private ?int $newRoomId;
    private ?string $newDate;
    private ?string $newStartTime;
    private ?string $newEndTime;
    private NoticeStatus $status;
    private ?int $reviewedByUserId;
    private ?string $rejectionReason;
    private ?string $reviewedAt;
    private ?string $createdAt;
    private ?string $updatedAt;

    public function __construct(
        ?int $id,
        int $scheduleId,
        int $instructorId,
        NoticeType $noticeType,
        string $affectedDate,
        string $title,
        string $message,
        ?int $newRoomId = null,
        ?string $newDate = null,
        ?string $newStartTime = null,
        ?string $newEndTime = null,
        NoticeStatus $status = NoticeStatus::PendingApproval,
        ?int $reviewedByUserId = null,
        ?string $rejectionReason = null,
        ?string $reviewedAt = null,
        ?string $createdAt = null,
        ?string $updatedAt = null
    ) {
        $this->id = $id;
        $this->scheduleId = $scheduleId;
        $this->instructorId = $instructorId;
        $this->noticeType = $noticeType;
        $this->affectedDate = trim($affectedDate);
        $this->title = trim($title);
        $this->message = trim($message);
        $this->newRoomId = $newRoomId;
        $this->newDate = $newDate !== null && trim($newDate) !== '' ? trim($newDate) : null;
        $this->newStartTime = $newStartTime !== null && trim($newStartTime) !== '' ? trim($newStartTime) : null;
        $this->newEndTime = $newEndTime !== null && trim($newEndTime) !== '' ? trim($newEndTime) : null;
        $this->status = $status;
        $this->reviewedByUserId = $reviewedByUserId;
        $this->rejectionReason = $rejectionReason !== null && trim($rejectionReason) !== '' ? trim($rejectionReason) : null;
        $this->reviewedAt = $reviewedAt;
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
            (int) ($row['schedule_id'] ?? 0),
            (int) ($row['instructor_id'] ?? 0),
            NoticeType::from((string) ($row['notice_type'] ?? 'unavailable')),
            (string) ($row['affected_date'] ?? ''),
            (string) ($row['title'] ?? ''),
            (string) ($row['message'] ?? ''),
            isset($row['new_room_id']) && $row['new_room_id'] !== null ? (int) $row['new_room_id'] : null,
            isset($row['new_date']) && $row['new_date'] !== null ? (string) $row['new_date'] : null,
            isset($row['new_start_time']) && $row['new_start_time'] !== null ? (string) $row['new_start_time'] : null,
            isset($row['new_end_time']) && $row['new_end_time'] !== null ? (string) $row['new_end_time'] : null,
            NoticeStatus::from((string) ($row['status'] ?? 'pending_approval')),
            isset($row['reviewed_by_user_id']) && $row['reviewed_by_user_id'] !== null
                ? (int) $row['reviewed_by_user_id']
                : null,
            isset($row['rejection_reason']) && $row['rejection_reason'] !== null
                ? (string) $row['rejection_reason']
                : null,
            isset($row['reviewed_at']) && $row['reviewed_at'] !== null ? (string) $row['reviewed_at'] : null,
            isset($row['created_at']) ? (string) $row['created_at'] : null,
            isset($row['updated_at']) ? (string) $row['updated_at'] : null
        );
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getScheduleId(): int
    {
        return $this->scheduleId;
    }

    public function getInstructorId(): int
    {
        return $this->instructorId;
    }

    public function getNoticeType(): NoticeType
    {
        return $this->noticeType;
    }

    public function getAffectedDate(): string
    {
        return $this->affectedDate;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getNewRoomId(): ?int
    {
        return $this->newRoomId;
    }

    public function getNewDate(): ?string
    {
        return $this->newDate;
    }

    public function getNewStartTime(): ?string
    {
        return $this->newStartTime;
    }

    public function getNewEndTime(): ?string
    {
        return $this->newEndTime;
    }

    public function getStatus(): NoticeStatus
    {
        return $this->status;
    }

    public function getReviewedByUserId(): ?int
    {
        return $this->reviewedByUserId;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'schedule_id' => $this->scheduleId,
            'instructor_id' => $this->instructorId,
            'notice_type' => $this->noticeType->value,
            'affected_date' => $this->affectedDate,
            'title' => $this->title,
            'message' => $this->message,
            'new_room_id' => $this->newRoomId,
            'new_date' => $this->newDate,
            'new_start_time' => $this->newStartTime,
            'new_end_time' => $this->newEndTime,
            'status' => $this->status->value,
            'reviewed_by_user_id' => $this->reviewedByUserId,
            'rejection_reason' => $this->rejectionReason,
            'reviewed_at' => $this->reviewedAt,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}

