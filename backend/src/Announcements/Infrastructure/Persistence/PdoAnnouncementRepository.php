<?php

declare(strict_types=1);

namespace Kampusly\Announcements\Infrastructure\Persistence;

use Kampusly\Announcements\Domain\Entity\Announcement;
use Kampusly\Announcements\Domain\Repository\AnnouncementRepositoryInterface;
use Kampusly\Shared\Application\DTO\PaginatedResult;
use Kampusly\Shared\Domain\ValueObject\PaginationQuery;
use PDO;

final class PdoAnnouncementRepository implements AnnouncementRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(int $id): ?Announcement
    {
        $stmt = $this->pdo->prepare('SELECT * FROM `announcements` WHERE `id` = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? Announcement::fromDatabaseRow($row) : null;
    }

    public function paginate(PaginationQuery $pagination, array $filters = []): PaginatedResult
    {
        $conditions = [];
        $params = [];

        if (isset($filters['section_id']) && is_int($filters['section_id'])) {
            $conditions[] = 'a.`section_id` = :section_id';
            $params[':section_id'] = $filters['section_id'];
        }

        if (isset($filters['subject_id']) && is_int($filters['subject_id'])) {
            $conditions[] = 'a.`subject_id` = :subject_id';
            $params[':subject_id'] = $filters['subject_id'];
        }

        if (isset($filters['status']) && is_string($filters['status']) && $filters['status'] !== '') {
            $conditions[] = 'a.`status` = :status';
            $params[':status'] = trim($filters['status']);
        }

        if (isset($filters['instructor_id']) && is_int($filters['instructor_id'])) {
            $conditions[] = 'a.`instructor_id` = :instructor_id';
            $params[':instructor_id'] = $filters['instructor_id'];
        }

        // Per-student archive view. 'active' hides any archived row (including expired ones),
        // 'archived' only shows archives still inside the retention window.
        $archiveJoin = '';
        $archiveSelect = '';
        if (isset($filters['student_id']) && is_int($filters['student_id'])) {
            $archiveJoin = 'LEFT JOIN `student_announcement_archives` sa
                ON sa.`announcement_id` = a.`id` AND sa.`student_id` = :student_id';
            $archiveSelect = ', sa.`archived_at` AS `archived_at`';
            $params[':student_id'] = $filters['student_id'];

            $retentionDays = isset($filters['archive_retention_days']) && is_int($filters['archive_retention_days'])
                ? max(1, $filters['archive_retention_days'])
                : 7;
            if (($filters['archive_view'] ?? 'active') === 'archived') {
                $conditions[] = "sa.`archived_at` IS NOT NULL AND sa.`archived_at` >= DATE_SUB(NOW(), INTERVAL {$retentionDays} DAY)";
            } else {
                $conditions[] = 'sa.`archived_at` IS NULL';
            }
        }

        $whereSql = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM `announcements` a {$archiveJoin} {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT a.*, s.`subject_code`, s.`subject_name`, u.`full_name` AS `author_name`{$archiveSelect}
                FROM `announcements` a
                LEFT JOIN `subjects` s ON s.`id` = a.`subject_id`
                LEFT JOIN `users` u ON u.`id` = a.`author_user_id`
                {$archiveJoin}
                {$whereSql}
                ORDER BY a.`published_at` DESC
                LIMIT :limit OFFSET :offset";
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
                $item = Announcement::fromDatabaseRow($row)->toArray();
                $item['subject_code'] = isset($row['subject_code']) ? (string) $row['subject_code'] : null;
                $item['subject_name'] = isset($row['subject_name']) ? (string) $row['subject_name'] : null;
                $item['author_name'] = isset($row['author_name']) ? (string) $row['author_name'] : null;
                if (array_key_exists('archived_at', $row)) {
                    $item['archived_at'] = $row['archived_at'] !== null ? (string) $row['archived_at'] : null;
                }
                $items[] = $item;
            }
        }

        return new PaginatedResult(
            $items,
            $pagination->getPage(),
            $pagination->getPerPage(),
            $total
        );
    }

    public function create(Announcement $announcement): Announcement
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO `announcements` (
                `author_user_id`, `instructor_id`, `subject_id`, `section_id`,
                `title`, `message`, `status`, `published_at`
             ) VALUES (
                :author_user_id, :instructor_id, :subject_id, :section_id,
                :title, :message, :status, CURRENT_TIMESTAMP
             )'
        );
        $stmt->execute([
            ':author_user_id' => $announcement->getAuthorUserId(),
            ':instructor_id' => $announcement->getInstructorId(),
            ':subject_id' => $announcement->getSubjectId(),
            ':section_id' => $announcement->getSectionId(),
            ':title' => $announcement->getTitle(),
            ':message' => $announcement->getMessage(),
            ':status' => $announcement->getStatus()->value,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        return $this->findById($id) ?? $announcement;
    }

    public function update(Announcement $announcement): Announcement
    {
        $id = $announcement->getId();
        if ($id === null) {
            throw new \InvalidArgumentException('Cannot update an announcement without an ID.');
        }

        $stmt = $this->pdo->prepare(
            'UPDATE `announcements`
             SET `title` = :title, `message` = :message, `subject_id` = :subject_id,
                 `section_id` = :section_id, `status` = :status,
                 `published_at` = CURRENT_TIMESTAMP, `updated_at` = CURRENT_TIMESTAMP
             WHERE `id` = :id'
        );
        $stmt->execute([
            ':title' => $announcement->getTitle(),
            ':message' => $announcement->getMessage(),
            ':subject_id' => $announcement->getSubjectId(),
            ':section_id' => $announcement->getSectionId(),
            ':status' => $announcement->getStatus()->value,
            ':id' => $id,
        ]);

        return $this->findById($id) ?? $announcement;
    }

    public function archiveForStudent(int $studentId, int $announcementId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO `student_announcement_archives` (`student_id`, `announcement_id`)
             VALUES (:student_id, :announcement_id)
             ON DUPLICATE KEY UPDATE `archived_at` = `archived_at`'
        );
        $stmt->execute([
            ':student_id' => $studentId,
            ':announcement_id' => $announcementId,
        ]);
    }

    public function restoreForStudent(int $studentId, int $announcementId, int $retentionDays): bool
    {
        $days = max(1, $retentionDays);
        $stmt = $this->pdo->prepare(
            "DELETE FROM `student_announcement_archives`
             WHERE `student_id` = :student_id
               AND `announcement_id` = :announcement_id
               AND `archived_at` >= DATE_SUB(NOW(), INTERVAL {$days} DAY)"
        );
        $stmt->execute([
            ':student_id' => $studentId,
            ':announcement_id' => $announcementId,
        ]);

        return $stmt->rowCount() > 0;
    }
}
