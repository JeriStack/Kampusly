<?php

declare(strict_types=1);

namespace Kampusly\Scheduling\Infrastructure\Persistence;

use Kampusly\Academic\Domain\ValueObject\Semester;
use Kampusly\Scheduling\Domain\Entity\Schedule;
use Kampusly\Scheduling\Domain\Repository\ScheduleRepositoryInterface;
use Kampusly\Scheduling\Domain\ValueObject\DayOfWeek;
use Kampusly\Shared\Application\DTO\PaginatedResult;
use Kampusly\Shared\Domain\ValueObject\PaginationQuery;
use PDO;

final class PdoScheduleRepository implements ScheduleRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(int $id): ?Schedule
    {
        $sql = $this->baseSelectSql() . ' WHERE s.`id` = :id LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? Schedule::fromDatabaseRow($row) : null;
    }

    public function findActiveByTermAndDay(
        string $schoolYear,
        Semester $semester,
        DayOfWeek $dayOfWeek,
        ?int $excludeScheduleId = null
    ): array {
        $sql = $this->baseSelectSql() . '
            WHERE s.`school_year` = :school_year
              AND s.`semester` = :semester
              AND s.`day_of_week` = :day_of_week
              AND s.`status` != :cancelled_status
              AND s.`archived_at` IS NULL';

        $params = [
            ':school_year' => trim($schoolYear),
            ':semester' => $semester->value,
            ':day_of_week' => $dayOfWeek->value,
            ':cancelled_status' => 'cancelled',
        ];

        if ($excludeScheduleId !== null) {
            $sql .= ' AND s.`id` != :exclude_id';
            $params[':exclude_id'] = $excludeScheduleId;
        }

        $sql .= ' ORDER BY s.`start_time` ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $schedules = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $schedules[] = Schedule::fromDatabaseRow($row);
            }
        }

        return $schedules;
    }

    public function paginate(PaginationQuery $pagination, array $filters = []): PaginatedResult
    {
        $conditions = ['s.`archived_at` IS NULL'];
        $params = [];

        if (isset($filters['school_year']) && is_string($filters['school_year']) && $filters['school_year'] !== '') {
            $conditions[] = 's.`school_year` = :school_year';
            $params[':school_year'] = trim($filters['school_year']);
        }

        if (isset($filters['semester']) && is_string($filters['semester']) && $filters['semester'] !== '') {
            $conditions[] = 's.`semester` = :semester';
            $params[':semester'] = trim($filters['semester']);
        }

        if (isset($filters['day_of_week']) && is_string($filters['day_of_week']) && $filters['day_of_week'] !== '') {
            $conditions[] = 's.`day_of_week` = :day_of_week';
            $params[':day_of_week'] = trim($filters['day_of_week']);
        }

        if (isset($filters['section_id']) && is_int($filters['section_id'])) {
            $conditions[] = 's.`section_id` = :section_id';
            $params[':section_id'] = $filters['section_id'];
        }

        if (isset($filters['instructor_id']) && is_int($filters['instructor_id'])) {
            $conditions[] = 's.`instructor_id` = :instructor_id';
            $params[':instructor_id'] = $filters['instructor_id'];
        }

        if (isset($filters['room_id']) && is_int($filters['room_id'])) {
            $conditions[] = 's.`room_id` = :room_id';
            $params[':room_id'] = $filters['room_id'];
        }

        if (isset($filters['status']) && is_string($filters['status']) && $filters['status'] !== '') {
            $conditions[] = 's.`status` = :status';
            $params[':status'] = trim($filters['status']);
        }

        $whereSql = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM `schedules` s {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = $this->baseSelectSql()
            . " {$whereSql} ORDER BY FIELD(s.`day_of_week`, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'),"
            . ' s.`start_time` ASC LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $type);
        }
        $stmt->bindValue(':limit', $pagination->getPerPage(), PDO::PARAM_INT);
        $stmt->bindValue(':offset', $pagination->getOffset(), PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        $items = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $items[] = Schedule::fromDatabaseRow($row)->toArray();
            }
        }

        return new PaginatedResult(
            $items,
            $pagination->getPage(),
            $pagination->getPerPage(),
            $total
        );
    }

    public function create(Schedule $schedule): Schedule
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO `schedules` (
                `subject_id`, `instructor_id`, `room_id`, `section_id`,
                `school_year`, `semester`, `day_of_week`, `start_time`, `end_time`, `status`
             ) VALUES (
                :subject_id, :instructor_id, :room_id, :section_id,
                :school_year, :semester, :day_of_week, :start_time, :end_time, :status
             )'
        );
        $stmt->execute([
            ':subject_id' => $schedule->getSubjectId(),
            ':instructor_id' => $schedule->getInstructorId(),
            ':room_id' => $schedule->getRoomId(),
            ':section_id' => $schedule->getSectionId(),
            ':school_year' => $schedule->getSchoolYear(),
            ':semester' => $schedule->getSemester()->value,
            ':day_of_week' => $schedule->getDayOfWeek()->value,
            ':start_time' => $schedule->getTimeSlot()->getStartTime(),
            ':end_time' => $schedule->getTimeSlot()->getEndTime(),
            ':status' => $schedule->getStatus()->value,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        return $this->findById($id) ?? $schedule;
    }

    public function update(Schedule $schedule): Schedule
    {
        $id = $schedule->getId();
        if ($id === null) {
            throw new \InvalidArgumentException('Cannot update a schedule without an ID.');
        }

        $stmt = $this->pdo->prepare(
            'UPDATE `schedules`
             SET `subject_id` = :subject_id,
                 `instructor_id` = :instructor_id,
                 `room_id` = :room_id,
                 `section_id` = :section_id,
                 `school_year` = :school_year,
                 `semester` = :semester,
                 `day_of_week` = :day_of_week,
                 `start_time` = :start_time,
                 `end_time` = :end_time,
                 `status` = :status,
                 `updated_at` = CURRENT_TIMESTAMP
             WHERE `id` = :id'
        );
        $stmt->execute([
            ':subject_id' => $schedule->getSubjectId(),
            ':instructor_id' => $schedule->getInstructorId(),
            ':room_id' => $schedule->getRoomId(),
            ':section_id' => $schedule->getSectionId(),
            ':school_year' => $schedule->getSchoolYear(),
            ':semester' => $schedule->getSemester()->value,
            ':day_of_week' => $schedule->getDayOfWeek()->value,
            ':start_time' => $schedule->getTimeSlot()->getStartTime(),
            ':end_time' => $schedule->getTimeSlot()->getEndTime(),
            ':status' => $schedule->getStatus()->value,
            ':id' => $id,
        ]);

        return $this->findById($id) ?? $schedule;
    }

    public function hasInstructorAssignment(int $instructorId, int $sectionId, int $subjectId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM `schedules`
             WHERE `instructor_id` = :instructor_id
               AND `section_id` = :section_id
               AND `subject_id` = :subject_id
               AND `status` != :cancelled_status'
        );
        $stmt->execute([
            ':instructor_id' => $instructorId,
            ':section_id' => $sectionId,
            ':subject_id' => $subjectId,
            ':cancelled_status' => 'cancelled',
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function baseSelectSql(): string
    {
        return 'SELECT
            s.*,
            subj.`subject_code`,
            subj.`subject_name`,
            CONCAT(inst.`first_name`, \' \', inst.`last_name`) AS `instructor_name`,
            rm.`room_code`,
            sec.`section_name`
        FROM `schedules` s
        INNER JOIN `subjects` subj ON subj.`id` = s.`subject_id`
        INNER JOIN `instructors` inst ON inst.`id` = s.`instructor_id`
        INNER JOIN `rooms` rm ON rm.`id` = s.`room_id`
        INNER JOIN `sections` sec ON sec.`id` = s.`section_id`';
    }
}
