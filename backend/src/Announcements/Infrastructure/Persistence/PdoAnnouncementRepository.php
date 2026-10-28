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
            $conditions[] = '`section_id` = :section_id';
            $params[':section_id'] = $filters['section_id'];
        }

        if (isset($filters['subject_id']) && is_int($filters['subject_id'])) {
            $conditions[] = '`subject_id` = :subject_id';
            $params[':subject_id'] = $filters['subject_id'];
        }

        if (isset($filters['status']) && is_string($filters['status']) && $filters['status'] !== '') {
            $conditions[] = '`status` = :status';
            $params[':status'] = trim($filters['status']);
        }

        $whereSql = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM `announcements` {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT * FROM `announcements` {$whereSql} ORDER BY `published_at` DESC LIMIT :limit OFFSET :offset";
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
                $items[] = Announcement::fromDatabaseRow($row)->toArray();
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
}

