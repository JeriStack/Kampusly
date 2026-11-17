<?php

declare(strict_types=1);

namespace Kampusly\Announcements\Application\Service;

use Kampusly\Academic\Domain\Repository\InstructorRepositoryInterface;
use Kampusly\Academic\Domain\Entity\InstructorProfile;
use Kampusly\Academic\Domain\Repository\StudentRepositoryInterface;
use Kampusly\Announcements\Domain\Entity\Announcement;
use Kampusly\Announcements\Domain\Repository\AnnouncementRepositoryInterface;
use Kampusly\Announcements\Domain\ValueObject\AnnouncementStatus;
use Kampusly\IdentityAccess\Domain\Entity\User;
use Kampusly\Notifications\Domain\Entity\Notification;
use Kampusly\Notifications\Domain\Repository\NotificationRepositoryInterface;
use Kampusly\Notifications\Domain\ValueObject\NotificationType;
use Kampusly\Scheduling\Domain\Repository\ScheduleRepositoryInterface;
use Kampusly\Shared\Application\Contract\AuditLoggerInterface;
use Kampusly\Shared\Application\Contract\TransactionManagerInterface;
use Kampusly\Shared\Application\DTO\PaginatedResult;
use Kampusly\Shared\Domain\Exception\ForbiddenException;
use Kampusly\Shared\Domain\Exception\ConflictException;
use Kampusly\Shared\Domain\Exception\ResourceNotFoundException;
use Kampusly\Shared\Domain\ValueObject\PaginationQuery;
use Kampusly\Shared\Infrastructure\Http\RequestValidator;

final class InstructorAnnouncementService
{
    /** Days an archived announcement is kept before it is deleted for the student. */
    public const ARCHIVE_RETENTION_DAYS = 7;

    private AnnouncementRepositoryInterface $announcements;
    private InstructorRepositoryInterface $instructors;
    private StudentRepositoryInterface $students;
    private ScheduleRepositoryInterface $schedules;
    private NotificationRepositoryInterface $notifications;
    private TransactionManagerInterface $transactions;
    private AuditLoggerInterface $auditLogger;

    public function __construct(
        AnnouncementRepositoryInterface $announcements,
        InstructorRepositoryInterface $instructors,
        StudentRepositoryInterface $students,
        ScheduleRepositoryInterface $schedules,
        NotificationRepositoryInterface $notifications,
        TransactionManagerInterface $transactions,
        AuditLoggerInterface $auditLogger
    ) {
        $this->announcements = $announcements;
        $this->instructors = $instructors;
        $this->students = $students;
        $this->schedules = $schedules;
        $this->notifications = $notifications;
        $this->transactions = $transactions;
        $this->auditLogger = $auditLogger;
    }

    public function listForInstructor(User $user, array $query): PaginatedResult
    {
        $instructor = $this->instructors->findByUserId((int) $user->getId());
        if ($instructor === null || $instructor->getId() === null) {
            throw new ResourceNotFoundException('Instructor profile was not found.');
        }

        // Expired archives are filtered out by the query anyway; this physically removes them.
        $this->announcements->purgeExpiredArchived(self::ARCHIVE_RETENTION_DAYS);

        $archived = in_array(strtolower((string) ($query['archived'] ?? '')), ['1', 'true'], true);

        return $this->announcements->paginate(
            PaginationQuery::fromQuery($query),
            [
                'instructor_id' => $instructor->getId(),
                'archive_state' => $archived ? 'archived' : 'active',
                'archive_retention_days' => self::ARCHIVE_RETENTION_DAYS,
            ]
        );
    }

    public function listForStudent(int $studentId, int $sectionId, array $query): PaginatedResult
    {
        $archived = in_array(strtolower((string) ($query['archived'] ?? '')), ['1', 'true'], true);

        return $this->announcements->paginate(
            PaginationQuery::fromQuery($query),
            [
                'section_id' => $sectionId,
                'status' => AnnouncementStatus::Published->value,
                'student_id' => $studentId,
                'archive_view' => $archived ? 'archived' : 'active',
                'archive_retention_days' => self::ARCHIVE_RETENTION_DAYS,
            ]
        );
    }

    public function archiveForStudent(int $studentId, int $sectionId, int $announcementId): void
    {
        $this->requireStudentAnnouncement($sectionId, $announcementId);
        $this->announcements->archiveForStudent($studentId, $announcementId);
    }

    public function restoreForStudent(int $studentId, int $sectionId, int $announcementId): void
    {
        $this->requireStudentAnnouncement($sectionId, $announcementId);
        $restored = $this->announcements->restoreForStudent(
            $studentId,
            $announcementId,
            self::ARCHIVE_RETENTION_DAYS
        );
        if (!$restored) {
            throw new ResourceNotFoundException('Archived announcement not found or already deleted.');
        }
    }

    private function requireStudentAnnouncement(int $sectionId, int $announcementId): Announcement
    {
        $announcement = $this->announcements->findById($announcementId);
        if (
            $announcement === null
            || $announcement->getSectionId() !== $sectionId
            || $announcement->getStatus() !== AnnouncementStatus::Published
        ) {
            throw new ResourceNotFoundException('Announcement not found.');
        }

        return $announcement;
    }

    /** @param array<string, mixed> $payload */
    public function create(User $user, array $payload, ?string $requestId): Announcement
    {
        $data = $this->validatePayload($payload);
        $instructor = $this->requireAssignedInstructor($user, $data['section_id'], $data['subject_id']);
        $announcement = new Announcement(
            null,
            (int) $user->getId(),
            $instructor->getId(),
            $data['subject_id'],
            $data['section_id'],
            $data['title'],
            $data['message'],
            AnnouncementStatus::Published
        );

        return $this->transactions->transactional(function () use ($announcement, $requestId): Announcement {
            $created = $this->announcements->create($announcement);
            $this->notifySection($created, false);
            $this->auditLogger->record(
                $created->getAuthorUserId(),
                'announcement.created',
                'announcement',
                $created->getId(),
                ['section_id' => $created->getSectionId()],
                $requestId
            );

            return $created;
        });
    }

    /** @param array<string, mixed> $payload */
    public function update(int $id, User $user, array $payload, ?string $requestId): Announcement
    {
        $existing = $this->requireOwnedAnnouncement($id, $user);
        if ($existing->getStatus() === AnnouncementStatus::Archived) {
            throw new ConflictException(
                'Archived announcements cannot be edited. Restore it first.',
                'ANNOUNCEMENT_ARCHIVED'
            );
        }
        $data = $this->validatePayload($payload);
        if ($existing->getSectionId() !== $data['section_id']
            || $existing->getSubjectId() !== $data['subject_id']
        ) {
            throw new ConflictException(
                'An announcement cannot be moved to a different section or subject after publishing.',
                'ANNOUNCEMENT_TARGET_IMMUTABLE'
            );
        }
        $instructor = $this->requireAssignedInstructor($user, $data['section_id'], $data['subject_id']);
        $updated = new Announcement(
            $id,
            (int) $user->getId(),
            $instructor->getId(),
            $data['subject_id'],
            $data['section_id'],
            $data['title'],
            $data['message'],
            AnnouncementStatus::Published,
            $existing->getPublishedAt()
        );

        return $this->transactions->transactional(function () use ($updated, $requestId): Announcement {
            $result = $this->announcements->update($updated);
            $this->notifySection($result, true);
            $this->auditLogger->record(
                $result->getAuthorUserId(),
                'announcement.updated',
                'announcement',
                $result->getId(),
                ['section_id' => $result->getSectionId()],
                $requestId
            );

            return $result;
        });
    }

    /**
     * Archive an announcement. Students stop seeing it right away and it is deleted
     * automatically once it has been archived for ARCHIVE_RETENTION_DAYS days.
     */
    public function archive(int $id, User $user, ?string $requestId): void
    {
        $existing = $this->requireOwnedAnnouncement($id, $user);
        if ($existing->getStatus() === AnnouncementStatus::Archived) {
            return;
        }
        if ($existing->getStatus() !== AnnouncementStatus::Published) {
            throw new ConflictException(
                'Only published announcements can be archived.',
                'ANNOUNCEMENT_NOT_PUBLISHED'
            );
        }

        $this->transactions->transactional(function () use ($id, $existing, $requestId): void {
            $this->announcements->archive($id);
            $this->auditLogger->record(
                $existing->getAuthorUserId(),
                'announcement.archived',
                'announcement',
                $id,
                [],
                $requestId
            );
        });
    }

    public function restore(int $id, User $user, ?string $requestId): void
    {
        $existing = $this->requireOwnedAnnouncement($id, $user);

        $this->transactions->transactional(function () use ($id, $existing, $requestId): void {
            if (!$this->announcements->restore($id, self::ARCHIVE_RETENTION_DAYS)) {
                throw new ResourceNotFoundException('Archived announcement not found or already deleted.');
            }
            $this->auditLogger->record(
                $existing->getAuthorUserId(),
                'announcement.restored',
                'announcement',
                $id,
                [],
                $requestId
            );
        });
    }

    /** @return array{section_id: int, subject_id: int, title: string, message: string} */
    private function validatePayload(array $payload): array
    {
        $validator = new RequestValidator($payload);
        $validator
            ->requirePositiveInt('section_id')
            ->requirePositiveInt('subject_id')
            ->requireString('title', 3, 180)
            ->requireString('message', 3, 5000)
            ->validateOrThrow();

        return [
            'section_id' => (int) $payload['section_id'],
            'subject_id' => (int) $payload['subject_id'],
            'title' => trim((string) $payload['title']),
            'message' => trim((string) $payload['message']),
        ];
    }

    private function requireAssignedInstructor(
        User $user,
        int $sectionId,
        int $subjectId
    ): InstructorProfile {
        $instructor = $this->instructors->findByUserId((int) $user->getId());
        if ($instructor === null || $instructor->getId() === null) {
            throw new ResourceNotFoundException('Instructor profile was not found.');
        }
        if (!$this->schedules->hasInstructorAssignment($instructor->getId(), $sectionId, $subjectId)) {
            throw new ForbiddenException('You can only post announcements for sections and subjects assigned to you.');
        }

        return $instructor;
    }

    private function requireOwnedAnnouncement(int $id, User $user): Announcement
    {
        $announcement = $this->announcements->findById($id);
        if ($announcement === null) {
            throw new ResourceNotFoundException('Announcement not found.');
        }
        if ($announcement->getAuthorUserId() !== (int) $user->getId()) {
            throw new ForbiddenException('You can only manage your own announcements.');
        }

        return $announcement;
    }

    private function notifySection(Announcement $announcement, bool $isUpdate): void
    {
        $sectionId = $announcement->getSectionId();
        if ($sectionId === null || $announcement->getId() === null) {
            return;
        }

        foreach ($this->students->findActiveUserIdsBySection($sectionId) as $studentUserId) {
            $this->notifications->create(new Notification(
                null,
                $studentUserId,
                $isUpdate ? 'Class announcement updated' : $announcement->getTitle(),
                $announcement->getMessage(),
                NotificationType::Announcement,
                'announcement',
                $announcement->getId(),
                $isUpdate
                    ? null
                    : sprintf('announcement:%d:published:%d', $announcement->getId(), $studentUserId)
            ));
        }
    }
}
