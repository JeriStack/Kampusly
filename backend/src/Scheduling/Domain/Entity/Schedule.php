<?php

declare(strict_types=1);

namespace Kampusly\Scheduling\Domain\Entity;

use Kampusly\Academic\Domain\ValueObject\Semester;
use Kampusly\Scheduling\Domain\ValueObject\DayOfWeek;
use Kampusly\Scheduling\Domain\ValueObject\ScheduleStatus;
use Kampusly\Scheduling\Domain\ValueObject\TimeSlot;

final class Schedule
{
    private ?int $id;
    private int $subjectId;
    private int $instructorId;
    private int $roomId;
    private int $sectionId;
    private string $schoolYear;
    private Semester $semester;
    private DayOfWeek $dayOfWeek;
    private TimeSlot $timeSlot;
    private ScheduleStatus $status;
    private ?string $subjectCode;
    private ?string $subjectName;
    private ?string $instructorName;
    private ?string $roomCode;
    private ?string $sectionName;
    private ?string $createdAt;
    private ?string $updatedAt;

    public function __construct(
        ?int $id,
        int $subjectId,
        int $instructorId,
        int $roomId,
        int $sectionId,
        string $schoolYear,
        Semester $semester,
        DayOfWeek $dayOfWeek,
        TimeSlot $timeSlot,
        ScheduleStatus $status = ScheduleStatus::Published,
        ?string $subjectCode = null,
        ?string $subjectName = null,
        ?string $instructorName = null,
        ?string $roomCode = null,
        ?string $sectionName = null,
        ?string $createdAt = null,
        ?string $updatedAt = null
    ) {
        $this->id = $id;
        $this->subjectId = $subjectId;
        $this->instructorId = $instructorId;
        $this->roomId = $roomId;
        $this->sectionId = $sectionId;
        $this->schoolYear = trim($schoolYear);
        $this->semester = $semester;
        $this->dayOfWeek = $dayOfWeek;
        $this->timeSlot = $timeSlot;
        $this->status = $status;
        $this->subjectCode = $subjectCode;
        $this->subjectName = $subjectName;
        $this->instructorName = $instructorName;
        $this->roomCode = $roomCode;
        $this->sectionName = $sectionName;
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
            (int) ($row['subject_id'] ?? 0),
            (int) ($row['instructor_id'] ?? 0),
            (int) ($row['room_id'] ?? 0),
            (int) ($row['section_id'] ?? 0),
            (string) ($row['school_year'] ?? ''),
            Semester::from((string) ($row['semester'] ?? '1st')),
            DayOfWeek::from((string) ($row['day_of_week'] ?? 'Monday')),
            new TimeSlot(
                (string) ($row['start_time'] ?? '08:00:00'),
                (string) ($row['end_time'] ?? '09:00:00')
            ),
            ScheduleStatus::from((string) ($row['status'] ?? 'published')),
            isset($row['subject_code']) ? (string) $row['subject_code'] : null,
            isset($row['subject_name']) ? (string) $row['subject_name'] : null,
            isset($row['instructor_name']) ? (string) $row['instructor_name'] : null,
            isset($row['room_code']) ? (string) $row['room_code'] : null,
            isset($row['section_name']) ? (string) $row['section_name'] : null,
            isset($row['created_at']) ? (string) $row['created_at'] : null,
            isset($row['updated_at']) ? (string) $row['updated_at'] : null
        );
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSubjectId(): int
    {
        return $this->subjectId;
    }

    public function getInstructorId(): int
    {
        return $this->instructorId;
    }

    public function getRoomId(): int
    {
        return $this->roomId;
    }

    public function getSectionId(): int
    {
        return $this->sectionId;
    }

    public function getSchoolYear(): string
    {
        return $this->schoolYear;
    }

    public function getSemester(): Semester
    {
        return $this->semester;
    }

    public function getDayOfWeek(): DayOfWeek
    {
        return $this->dayOfWeek;
    }

    public function getTimeSlot(): TimeSlot
    {
        return $this->timeSlot;
    }

    public function getStatus(): ScheduleStatus
    {
        return $this->status;
    }

    public function isCancelled(): bool
    {
        return $this->status === ScheduleStatus::Cancelled;
    }

    public function describeDisplayLabel(): string
    {
        $subjectLabel = $this->subjectCode ?? ('Subject #' . $this->subjectId);
        $sectionLabel = $this->sectionName ?? ('Section #' . $this->sectionId);

        return sprintf('%s (%s)', $subjectLabel, $sectionLabel);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'subject_id' => $this->subjectId,
            'subject_code' => $this->subjectCode,
            'subject_name' => $this->subjectName,
            'instructor_id' => $this->instructorId,
            'instructor_name' => $this->instructorName,
            'room_id' => $this->roomId,
            'room_code' => $this->roomCode,
            'section_id' => $this->sectionId,
            'section_name' => $this->sectionName,
            'school_year' => $this->schoolYear,
            'semester' => $this->semester->value,
            'day_of_week' => $this->dayOfWeek->value,
            'start_time' => $this->timeSlot->getStartTime(),
            'end_time' => $this->timeSlot->getEndTime(),
            'status' => $this->status->value,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}

