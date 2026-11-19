<?php

declare(strict_types=1);

namespace Kampusly\ClassNotices\Infrastructure\Persistence;

use Kampusly\ClassNotices\Domain\Entity\ClassNotice;
use Kampusly\ClassNotices\Domain\Repository\ClassNoticeRepositoryInterface;
use Kampusly\Shared\Application\DTO\PaginatedResult;
use Kampusly\Shared\Domain\ValueObject\PaginationQuery;
use PDO;

final class PdoClassNoticeRepository implements ClassNoticeRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(int $id): ?ClassNotice
    {
        $stmt = $this->pdo->prepare('SELECT * FROM `class_notices` WHERE `id` = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? ClassNotice::fromDatabaseRow($row) : null;
    }

    public function paginate(PaginationQuery $pagination, array $filters = []): PaginatedResult
    {
        $conditions = [];
        $params = [];

        if (isset($filters['schedule_id']) && is_int($filters['schedule_id'])) {
            $conditions[] = '`schedule_id` = :schedule_id';
            $params[':schedule_id'] = $filters['schedule_id'];
        }

        if (isset($filters['instructor_id']) && is_int($filters['instructor_id'])) {
            $conditions[] = '`instructor_id` = :instructor_id';
            $params[':instructor_id'] = $filters['instructor_id'];
        }

        if (isset($filters['status']) && is_string($filters['status']) && $filters['status'] !== '') {
            $conditions[] = '`status` = :status';
            $params[':status'] = trim($filters['status']);
        }

        if (isset($filters['affected_date']) && is_string($filters['affected_date']) && $filters['affected_date'] !== '') {
            $conditions[] = '`affected_date` = :affected_date';
            $params[':affected_date'] = trim($filters['affected_date']);
        }

        $whereSql = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM `class_notices` {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT * FROM `class_notices` {$whereSql} ORDER BY `created_at` DESC LIMIT :limit OFFSET :offset";
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
                $items[] = ClassNotice::fromDatabaseRow($row)->toArray();
            }
        }

        return new PaginatedResult(
            $items,
            $pagination->getPage(),
            $pagination->getPerPage(),
            $total
        );
    }

    public function create(ClassNotice $notice): ClassNotice
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO `class_notices` (
                `schedule_id`, `instructor_id`, `notice_type`, `affected_date`,
                `title`, `message`, `new_room_id`, `new_date`, `new_start_time`, `new_end_time`,
                `status`, `reviewed_by_user_id`, `rejection_reason`
             ) VALUES (
                :schedule_id, :instructor_id, :notice_type, :affected_date,
                :title, :message, :new_room_id, :new_date, :new_start_time, :new_end_time,
                :status, :reviewed_by_user_id, :rejection_reason
             )'
        );
        $stmt->execute([
            ':schedule_id' => $notice->getScheduleId(),
            ':instructor_id' => $notice->getInstructorId(),
            ':notice_type' => $notice->getNoticeType()->value,
            ':affected_date' => $notice->getAffectedDate(),
            ':title' => $notice->getTitle(),
            ':message' => $notice->getMessage(),
            ':new_room_id' => $notice->getNewRoomId(),
            ':new_date' => $notice->getNewDate(),
            ':new_start_time' => $notice->getNewStartTime(),
            ':new_end_time' => $notice->getNewEndTime(),
            ':status' => $notice->getStatus()->value,
            ':reviewed_by_user_id' => $notice->getReviewedByUserId(),
            ':rejection_reason' => $notice->getRejectionReason(),
        ]);

        $id = (int) $this->pdo->lastInsertId();
        return $this->findById($id) ?? $notice;
    }
}

