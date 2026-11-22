<?php

declare(strict_types=1);

namespace Kampusly\IdentityAccess\Infrastructure\Persistence;

use Kampusly\Academic\Domain\ValueObject\Semester;
use Kampusly\IdentityAccess\Domain\Repository\AdminArchiveRepositoryInterface;
use Kampusly\Scheduling\Domain\Repository\ScheduleRepositoryInterface;
use Kampusly\Scheduling\Domain\Service\ScheduleConflictChecker;
use Kampusly\Scheduling\Domain\ValueObject\DayOfWeek;
use Kampusly\Scheduling\Domain\ValueObject\TimeSlot;
use Kampusly\Shared\Application\DTO\PaginatedResult;
use Kampusly\Shared\Domain\ValueObject\PaginationQuery;
use PDO;

final class PdoAdminArchiveRepository implements AdminArchiveRepositoryInterface
{
    private PDO $pdo;
    private ScheduleRepositoryInterface $scheduleRepository;
    private ScheduleConflictChecker $conflictChecker;

    public function __construct(
        PDO $pdo,
        ScheduleRepositoryInterface $scheduleRepository,
        ScheduleConflictChecker $conflictChecker
    ) {
        $this->pdo = $pdo;
        $this->scheduleRepository = $scheduleRepository;
        $this->conflictChecker = $conflictChecker;
    }

    public function countArchivedByType(): array
    {
        $counts = [
            'users' => $this->countTableArchived('users'),
            'subjects' => $this->countTableArchived('subjects'),
            'rooms' => $this->countTableArchived('rooms'),
            'sections' => $this->countTableArchived('sections'),
            'schedules' => $this->countTableArchived('schedules'),
        ];

        return [
            'all' => array_sum($counts),
            'users' => $counts['users'],
            'subjects' => $counts['subjects'],
            'rooms' => $counts['rooms'],
            'sections' => $counts['sections'],
            'schedules' => $counts['schedules'],
        ];
    }

    public function paginateArchived(
        PaginationQuery $pagination,
        ?string $type = null,
        ?string $search = null
    ): PaginatedResult {
        $typesToLoad = ($type !== null && $type !== '' && $type !== 'all')
            ? [$type]
            : ['users', 'subjects', 'rooms', 'sections', 'schedules'];

        $allItems = [];
        foreach ($typesToLoad as $entityType) {
            foreach ($this->fetchArchivedByType($entityType) as $item) {
                $allItems[] = $item;
            }
        }

        if ($search !== null && trim($search) !== '') {
            $needle = strtolower(trim($search));
            $allItems = array_values(array_filter(
                $allItems,
                static function (array $item) use ($needle): bool {
                    $haystack = strtolower(implode(' ', [
                        (string) ($item['title'] ?? ''),
                        (string) ($item['subtitle'] ?? ''),
                        (string) ($item['badge'] ?? ''),
                        (string) ($item['entity_type'] ?? ''),
                    ]));

                    return str_contains($haystack, $needle);
                }
            ));
        }

        usort(
            $allItems,
            static fn (array $a, array $b): int => strcmp(
                (string) ($b['archived_at'] ?? ''),
                (string) ($a['archived_at'] ?? '')
            )
        );

        $total = count($allItems);
        $slice = array_slice($allItems, $pagination->getOffset(), $pagination->getPerPage());

        foreach ($slice as &$entry) {
            $blockReason = $this->getPermanentDeleteBlockReason(
                (string) $entry['entity_type'],
                (int) $entry['id']
            );
            $entry['can_permanently_delete'] = $blockReason === null;
            $entry['delete_block_reason'] = $blockReason;
        }
        unset($entry);

        return new PaginatedResult(
            $slice,
            $pagination->getPage(),
            $pagination->getPerPage(),
            $total
        );
    }

    public function findRecordSummary(string $type, int $id): ?array
    {
        return match ($type) {
            'users' => $this->findUserSummary($id),
            'subjects' => $this->findSubjectSummary($id),
            'rooms' => $this->findRoomSummary($id),
            'sections' => $this->findSectionSummary($id),
            'schedules' => $this->findScheduleSummary($id),
            default => null,
        };
    }

    public function markArchived(string $type, int $id): void
    {
        $table = $this->resolveTable($type);
        $status = $type === 'schedules' ? 'cancelled' : 'inactive';
        $sql = sprintf(
            'UPDATE `%s` SET `status` = :status, `archived_at` = CURRENT_TIMESTAMP, `updated_at` = CURRENT_TIMESTAMP WHERE `id` = :id',
            $table
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':status' => $status,
            ':id' => $id,
        ]);
    }

    public function markRestored(string $type, int $id): void
    {
        $table = $this->resolveTable($type);
        $status = $type === 'schedules' ? 'published' : 'active';
        $sql = sprintf(
            'UPDATE `%s` SET `status` = :status, `archived_at` = NULL, `updated_at` = CURRENT_TIMESTAMP WHERE `id` = :id',
            $table
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':status' => $status,
            ':id' => $id,
        ]);
    }

    private function resolveTable(string $type): string
    {
        return match ($type) {
            'users' => 'users',
            'subjects' => 'subjects',
            'rooms' => 'rooms',
            'sections' => 'sections',
            'schedules' => 'schedules',
            default => throw new \InvalidArgumentException('Unsupported archive type.'),
        };
    }

    public function getRestoreBlockReason(string $type, int $id): ?string
    {
        if ($type !== 'schedules') {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT s.*,
                    subj.`archived_at` AS `subject_archived_at`, subj.`subject_code`,
                    rm.`archived_at` AS `room_archived_at`, rm.`room_code`,
                    sec.`archived_at` AS `section_archived_at`, sec.`section_name`,
                    u.`archived_at` AS `instructor_archived_at`, u.`full_name` AS `instructor_name`
             FROM `schedules` s
             INNER JOIN `subjects` subj ON subj.`id` = s.`subject_id`
             INNER JOIN `rooms` rm ON rm.`id` = s.`room_id`
             INNER JOIN `sections` sec ON sec.`id` = s.`section_id`
             INNER JOIN `instructors` inst ON inst.`id` = s.`instructor_id`
             INNER JOIN `users` u ON u.`id` = inst.`user_id`
             WHERE s.`id` = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        if (!is_array($row)) {
            return null;
        }

        if ($row['subject_archived_at'] !== null) {
            return sprintf('Restore subject %s before restoring this schedule.', (string) $row['subject_code']);
        }
        if ($row['room_archived_at'] !== null) {
            return sprintf('Restore room %s before restoring this schedule.', (string) $row['room_code']);
        }
        if ($row['section_archived_at'] !== null) {
            return sprintf('Restore section %s before restoring this schedule.', (string) $row['section_name']);
        }
        if ($row['instructor_archived_at'] !== null) {
            return sprintf('Restore instructor %s before restoring this schedule.', (string) $row['instructor_name']);
        }

        $semester = Semester::from((string) $row['semester']);
        $dayOfWeek = DayOfWeek::from((string) $row['day_of_week']);
        $timeSlot = new TimeSlot((string) $row['start_time'], (string) $row['end_time']);

        $existing = $this->scheduleRepository->findActiveByTermAndDay(
            (string) $row['school_year'],
            $semester,
            $dayOfWeek,
            $id
        );

        $conflicts = $this->conflictChecker->detectConflicts(
            (int) $row['room_id'],
            (int) $row['instructor_id'],
            (int) $row['section_id'],
            (string) $row['school_year'],
            $semester,
            $dayOfWeek,
            $timeSlot,
            $existing,
            $id
        );

        if ($conflicts !== []) {
            return $conflicts[0]->getMessage();
        }

        return null;
    }

    public function getPermanentDeleteBlockReason(string $type, int $id): ?string
    {
        return match ($type) {
            'subjects' => $this->getSubjectDeleteBlockReason($id),
            'rooms' => $this->getRoomDeleteBlockReason($id),
            'sections' => $this->getSectionDeleteBlockReason($id),
            'schedules' => $this->getScheduleDeleteBlockReason($id),
            'users' => $this->getUserDeleteBlockReason($id),
            default => 'Unsupported record type.',
        };
    }

    public function permanentlyDelete(string $type, int $id): void
    {
        if ($type === 'users') {
            $this->pdo->prepare('DELETE FROM `students` WHERE `user_id` = :id')->execute([':id' => $id]);
            $this->pdo->prepare('DELETE FROM `instructors` WHERE `user_id` = :id')->execute([':id' => $id]);
            $this->pdo->prepare('DELETE FROM `users` WHERE `id` = :id')->execute([':id' => $id]);
            return;
        }

        $table = match ($type) {
            'subjects' => 'subjects',
            'rooms' => 'rooms',
            'sections' => 'sections',
            'schedules' => 'schedules',
            default => throw new \InvalidArgumentException('Unsupported archive type.'),
        };

        $stmt = $this->pdo->prepare("DELETE FROM `{$table}` WHERE `id` = :id");
        $stmt->execute([':id' => $id]);
    }

    private function countTableArchived(string $table): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM `{$table}` WHERE `archived_at` IS NOT NULL");
        return $stmt !== false ? (int) $stmt->fetchColumn() : 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchArchivedByType(string $type): array
    {
        return match ($type) {
            'users' => $this->fetchArchivedUsers(),
            'subjects' => $this->fetchArchivedSubjects(),
            'rooms' => $this->fetchArchivedRooms(),
            'sections' => $this->fetchArchivedSections(),
            'schedules' => $this->fetchArchivedSchedules(),
            default => [],
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchArchivedUsers(): array
    {
        $stmt = $this->pdo->query(
            'SELECT `id`, `full_name`, `email`, `role`, `archived_at`
             FROM `users`
             WHERE `archived_at` IS NOT NULL
             ORDER BY `archived_at` DESC'
        );
        $rows = $stmt !== false ? $stmt->fetchAll() : [];

        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $items[] = [
                'entity_type' => 'users',
                'id' => (int) $row['id'],
                'title' => (string) $row['full_name'],
                'subtitle' => (string) $row['email'],
                'badge' => ucfirst((string) $row['role']),
                'archived_at' => (string) $row['archived_at'],
            ];
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchArchivedSubjects(): array
    {
        $stmt = $this->pdo->query(
            'SELECT `id`, `subject_code`, `subject_name`, `units`, `archived_at`
             FROM `subjects`
             WHERE `archived_at` IS NOT NULL
             ORDER BY `archived_at` DESC'
        );
        $rows = $stmt !== false ? $stmt->fetchAll() : [];

        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $units = (int) $row['units'];
            $items[] = [
                'entity_type' => 'subjects',
                'id' => (int) $row['id'],
                'title' => sprintf('%s — %s', (string) $row['subject_code'], (string) $row['subject_name']),
                'subtitle' => sprintf('%d %s', $units, $units === 1 ? 'unit' : 'units'),
                'badge' => (string) $row['subject_code'],
                'archived_at' => (string) $row['archived_at'],
            ];
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchArchivedRooms(): array
    {
        $stmt = $this->pdo->query(
            'SELECT `id`, `room_code`, `room_name`, `building`, `capacity`, `archived_at`
             FROM `rooms`
             WHERE `archived_at` IS NOT NULL
             ORDER BY `archived_at` DESC'
        );
        $rows = $stmt !== false ? $stmt->fetchAll() : [];

        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $items[] = [
                'entity_type' => 'rooms',
                'id' => (int) $row['id'],
                'title' => sprintf('%s — %s', (string) $row['room_code'], (string) $row['room_name']),
                'subtitle' => sprintf('%s • %d seats', (string) $row['building'], (int) $row['capacity']),
                'badge' => (string) $row['room_code'],
                'archived_at' => (string) $row['archived_at'],
            ];
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchArchivedSections(): array
    {
        $stmt = $this->pdo->query(
            'SELECT `id`, `section_name`, `program`, `year_level`, `school_year`, `semester`, `archived_at`
             FROM `sections`
             WHERE `archived_at` IS NOT NULL
             ORDER BY `archived_at` DESC'
        );
        $rows = $stmt !== false ? $stmt->fetchAll() : [];

        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $items[] = [
                'entity_type' => 'sections',
                'id' => (int) $row['id'],
                'title' => (string) $row['section_name'],
                'subtitle' => sprintf(
                    '%s • Year %d • %s (%s)',
                    (string) $row['program'],
                    (int) $row['year_level'],
                    (string) $row['school_year'],
                    (string) $row['semester']
                ),
                'badge' => (string) $row['school_year'],
                'archived_at' => (string) $row['archived_at'],
            ];
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchArchivedSchedules(): array
    {
        $stmt = $this->pdo->query(
            'SELECT s.`id`, s.`day_of_week`, s.`start_time`, s.`end_time`, s.`school_year`, s.`semester`,
                    s.`archived_at`, subj.`subject_code`, subj.`subject_name`,
                    sec.`section_name`, rm.`room_code`,
                    CONCAT(inst.`first_name`, \' \', inst.`last_name`) AS `instructor_name`
             FROM `schedules` s
             INNER JOIN `subjects` subj ON subj.`id` = s.`subject_id`
             INNER JOIN `sections` sec ON sec.`id` = s.`section_id`
             INNER JOIN `rooms` rm ON rm.`id` = s.`room_id`
             INNER JOIN `instructors` inst ON inst.`id` = s.`instructor_id`
             WHERE s.`archived_at` IS NOT NULL
             ORDER BY s.`archived_at` DESC'
        );
        $rows = $stmt !== false ? $stmt->fetchAll() : [];

        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $start = substr((string) $row['start_time'], 0, 5);
            $end = substr((string) $row['end_time'], 0, 5);
            $items[] = [
                'entity_type' => 'schedules',
                'id' => (int) $row['id'],
                'title' => sprintf('%s (%s)', (string) $row['subject_code'], (string) $row['section_name']),
                'subtitle' => sprintf(
                    '%s %s–%s • %s • %s',
                    (string) $row['day_of_week'],
                    $start,
                    $end,
                    (string) $row['room_code'],
                    (string) $row['instructor_name']
                ),
                'badge' => sprintf('%s %s', (string) $row['school_year'], (string) $row['semester']),
                'archived_at' => (string) $row['archived_at'],
            ];
        }

        return $items;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findUserSummary(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT `id`, `full_name`, `email`, `role`, `status`, `archived_at` FROM `users` WHERE `id` = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return null;
        }

        return [
            'entity_type' => 'users',
            'id' => (int) $row['id'],
            'title' => (string) $row['full_name'],
            'subtitle' => (string) $row['email'],
            'badge' => ucfirst((string) $row['role']),
            'status' => (string) $row['status'],
            'archived_at' => $row['archived_at'] !== null ? (string) $row['archived_at'] : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findSubjectSummary(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT `id`, `subject_code`, `subject_name`, `units`, `status`, `archived_at` FROM `subjects` WHERE `id` = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return null;
        }

        return [
            'entity_type' => 'subjects',
            'id' => (int) $row['id'],
            'title' => sprintf('%s — %s', (string) $row['subject_code'], (string) $row['subject_name']),
            'subtitle' => sprintf('%d units', (int) $row['units']),
            'badge' => (string) $row['subject_code'],
            'status' => (string) $row['status'],
            'archived_at' => $row['archived_at'] !== null ? (string) $row['archived_at'] : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findRoomSummary(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT `id`, `room_code`, `room_name`, `building`, `capacity`, `status`, `archived_at` FROM `rooms` WHERE `id` = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return null;
        }

        return [
            'entity_type' => 'rooms',
            'id' => (int) $row['id'],
            'title' => sprintf('%s — %s', (string) $row['room_code'], (string) $row['room_name']),
            'subtitle' => sprintf('%s • %d seats', (string) $row['building'], (int) $row['capacity']),
            'badge' => (string) $row['room_code'],
            'status' => (string) $row['status'],
            'archived_at' => $row['archived_at'] !== null ? (string) $row['archived_at'] : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findSectionSummary(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT `id`, `section_name`, `program`, `year_level`, `school_year`, `semester`, `status`, `archived_at`
             FROM `sections`
             WHERE `id` = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return null;
        }

        return [
            'entity_type' => 'sections',
            'id' => (int) $row['id'],
            'title' => (string) $row['section_name'],
            'subtitle' => sprintf('%s • Year %d', (string) $row['program'], (int) $row['year_level']),
            'badge' => (string) $row['school_year'],
            'status' => (string) $row['status'],
            'archived_at' => $row['archived_at'] !== null ? (string) $row['archived_at'] : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findScheduleSummary(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.`id`, s.`day_of_week`, s.`start_time`, s.`end_time`, s.`status`, s.`archived_at`,
                    subj.`subject_code`, sec.`section_name`, rm.`room_code`
             FROM `schedules` s
             INNER JOIN `subjects` subj ON subj.`id` = s.`subject_id`
             INNER JOIN `sections` sec ON sec.`id` = s.`section_id`
             INNER JOIN `rooms` rm ON rm.`id` = s.`room_id`
             WHERE s.`id` = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return null;
        }

        return [
            'entity_type' => 'schedules',
            'id' => (int) $row['id'],
            'title' => sprintf('%s (%s)', (string) $row['subject_code'], (string) $row['section_name']),
            'subtitle' => sprintf(
                '%s %s–%s • %s',
                (string) $row['day_of_week'],
                substr((string) $row['start_time'], 0, 5),
                substr((string) $row['end_time'], 0, 5),
                (string) $row['room_code']
            ),
            'badge' => (string) $row['room_code'],
            'status' => (string) $row['status'],
            'archived_at' => $row['archived_at'] !== null ? (string) $row['archived_at'] : null,
        ];
    }

    private function getSubjectDeleteBlockReason(int $id): ?string
    {
        if ($this->countWhere('schedules', 'subject_id = :id', $id) > 0) {
            return 'Referenced by existing class schedules.';
        }
        if ($this->countWhere('announcements', 'subject_id = :id', $id) > 0) {
            return 'Referenced by existing announcements.';
        }

        return null;
    }

    private function getRoomDeleteBlockReason(int $id): ?string
    {
        if ($this->countWhere('schedules', 'room_id = :id', $id) > 0) {
            return 'Referenced by existing class schedules.';
        }

        return null;
    }

    private function getSectionDeleteBlockReason(int $id): ?string
    {
        if ($this->countWhere('students', 'section_id = :id', $id) > 0) {
            return 'Has enrolled student records.';
        }
        if ($this->countWhere('schedules', 'section_id = :id', $id) > 0) {
            return 'Referenced by existing class schedules.';
        }
        if ($this->countWhere('announcements', 'section_id = :id', $id) > 0) {
            return 'Referenced by existing announcements.';
        }

        return null;
    }

    private function getScheduleDeleteBlockReason(int $id): ?string
    {
        if ($this->countWhere('schedule_change_requests', 'schedule_id = :id', $id) > 0) {
            return 'Referenced by student schedule change requests.';
        }
        if ($this->countWhere('class_notices', 'schedule_id = :id', $id) > 0) {
            return 'Referenced by class notices.';
        }

        return null;
    }

    private function getUserDeleteBlockReason(int $id): ?string
    {
        if ($this->countWhere('announcements', 'author_user_id = :id', $id) > 0) {
            return 'Referenced as author of existing announcements.';
        }

        $instStmt = $this->pdo->prepare('SELECT `id` FROM `instructors` WHERE `user_id` = :id LIMIT 1');
        $instStmt->execute([':id' => $id]);
        $instructorId = $instStmt->fetchColumn();
        if ($instructorId !== false) {
            $instId = (int) $instructorId;
            if ($this->countWhere('schedules', 'instructor_id = :id', $instId) > 0) {
                return 'Instructor is referenced by class schedules.';
            }
            if ($this->countWhere('class_notices', 'instructor_id = :id', $instId) > 0) {
                return 'Instructor is referenced by class notices.';
            }
        }

        return null;
    }

    private function countWhere(string $table, string $where, int $id): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE {$where}");
        $stmt->execute([':id' => $id]);
        return (int) $stmt->fetchColumn();
    }
}
