<?php

declare(strict_types=1);

namespace Kampusly\Scheduling\Infrastructure\Persistence;

use Kampusly\Scheduling\Domain\Repository\ScheduleChangeRequestRepositoryInterface;
use Kampusly\Shared\Application\DTO\PaginatedResult;
use Kampusly\Shared\Domain\ValueObject\PaginationQuery;
use PDO;

final class PdoScheduleChangeRequestRepository implements ScheduleChangeRequestRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function create(array $request): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO `schedule_change_requests` (
                `student_id`, `schedule_id`, `current_day_of_week`, `current_start_time`,
                `current_end_time`, `current_room_code`, `preferred_day_of_week`,
                `preferred_start_time`, `preferred_end_time`, `preferred_room_id`, `reason`
             ) VALUES (
                :student_id, :schedule_id, :current_day, :current_start, :current_end,
                :current_room_code, :preferred_day, :preferred_start,
                :preferred_end, :preferred_room_id, :reason
             )'
        );
        $stmt->execute([
            ':student_id' => $request['student_id'],
            ':schedule_id' => $request['schedule_id'],
            ':current_day' => $request['current_day_of_week'],
            ':current_start' => $request['current_start_time'],
            ':current_end' => $request['current_end_time'],
            ':current_room_code' => $request['current_room_code'],
            ':preferred_day' => $request['preferred_day_of_week'],
            ':preferred_start' => $request['preferred_start_time'],
            ':preferred_end' => $request['preferred_end_time'],
            ':preferred_room_id' => $request['preferred_room_id'],
            ':reason' => $request['reason'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare($this->baseSelectSql() . ' WHERE cr.`id` = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function hasPendingRequest(int $studentId, int $scheduleId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM `schedule_change_requests`
             WHERE `student_id` = :student_id AND `schedule_id` = :schedule_id AND `status` = :status'
        );
        $stmt->execute([
            ':student_id' => $studentId,
            ':schedule_id' => $scheduleId,
            ':status' => 'pending',
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function paginateForStudent(int $studentId, PaginationQuery $pagination): PaginatedResult
    {
        return $this->paginate('cr.`student_id` = :student_id', [':student_id' => $studentId], $pagination);
    }

    public function paginateAll(?string $status, PaginationQuery $pagination): PaginatedResult
    {
        $condition = $status !== null ? 'cr.`status` = :status' : '1 = 1';
        $params = $status !== null ? [':status' => $status] : [];

        return $this->paginate($condition, $params, $pagination);
    }

    public function review(int $id, int $reviewerUserId, string $status, ?string $note): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE `schedule_change_requests`
             SET `status` = :status, `reviewed_by_user_id` = :reviewer_user_id,
                 `review_note` = :review_note, `reviewed_at` = CURRENT_TIMESTAMP
             WHERE `id` = :id AND `status` = :pending_status'
        );
        $stmt->execute([
            ':status' => $status,
            ':reviewer_user_id' => $reviewerUserId,
            ':review_note' => $note,
            ':id' => $id,
            ':pending_status' => 'pending',
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function paginate(string $condition, array $params, PaginationQuery $pagination): PaginatedResult
    {
        $where = 'WHERE ' . $condition;
        $count = $this->pdo->prepare("SELECT COUNT(*) FROM `schedule_change_requests` cr {$where}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $stmt = $this->pdo->prepare(
            $this->baseSelectSql()
            . " {$where} ORDER BY cr.`created_at` DESC LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $pagination->getPerPage(), PDO::PARAM_INT);
        $stmt->bindValue(':offset', $pagination->getOffset(), PDO::PARAM_INT);
        $stmt->execute();

        return new PaginatedResult(
            $stmt->fetchAll(PDO::FETCH_ASSOC),
            $pagination->getPage(),
            $pagination->getPerPage(),
            $total
        );
    }

    private function baseSelectSql(): string
    {
        return 'SELECT cr.*,
                    st.`user_id` AS student_user_id, st.`student_number`,
                    CONCAT(st.`first_name`, " ", st.`last_name`) AS student_name,
                    sec.`section_name`, sec.`term_start_date`,
                    sch.`instructor_id`, sch.`subject_id`,
                    subj.`subject_code`, subj.`subject_name`,
                    cr.`current_day_of_week`, cr.`current_start_time`, cr.`current_end_time`,
                    cr.`current_room_code`,
                    preferred_room.`room_code` AS preferred_room_code
                FROM `schedule_change_requests` cr
                INNER JOIN `students` st ON st.`id` = cr.`student_id`
                INNER JOIN `schedules` sch ON sch.`id` = cr.`schedule_id`
                INNER JOIN `sections` sec ON sec.`id` = sch.`section_id`
                INNER JOIN `subjects` subj ON subj.`id` = sch.`subject_id`
                LEFT JOIN `rooms` preferred_room ON preferred_room.`id` = cr.`preferred_room_id`';
    }
}
