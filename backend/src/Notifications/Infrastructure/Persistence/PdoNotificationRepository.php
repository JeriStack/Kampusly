<?php

declare(strict_types=1);

namespace Kampusly\Notifications\Infrastructure\Persistence;

use Kampusly\Notifications\Domain\Entity\Notification;
use Kampusly\Notifications\Domain\Repository\NotificationRepositoryInterface;
use Kampusly\Shared\Application\DTO\PaginatedResult;
use Kampusly\Shared\Domain\ValueObject\PaginationQuery;
use PDO;

final class PdoNotificationRepository implements NotificationRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(int $id): ?Notification
    {
        $stmt = $this->pdo->prepare('SELECT * FROM `notifications` WHERE `id` = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? Notification::fromDatabaseRow($row) : null;
    }

    public function paginateForUser(
        int $userId,
        PaginationQuery $pagination,
        ?bool $unreadOnly = null
    ): PaginatedResult {
        $conditions = ['`user_id` = :user_id'];
        $params = [':user_id' => $userId];

        if ($unreadOnly === true) {
            $conditions[] = '`read_at` IS NULL';
        }

        $whereSql = 'WHERE ' . implode(' AND ', $conditions);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM `notifications` {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT * FROM `notifications` {$whereSql} ORDER BY `created_at` DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $pagination->getPerPage(), PDO::PARAM_INT);
        $stmt->bindValue(':offset', $pagination->getOffset(), PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        $items = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $items[] = Notification::fromDatabaseRow($row)->toArray();
            }
        }

        return new PaginatedResult(
            $items,
            $pagination->getPage(),
            $pagination->getPerPage(),
            $total
        );
    }

    public function create(Notification $notification): Notification
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO `notifications` (
                `user_id`, `title`, `message`, `type`, `reference_type`, `reference_id`, `deduplication_key`
             ) VALUES (
                :user_id, :title, :message, :type, :reference_type, :reference_id, :deduplication_key
             )'
        );
        $stmt->execute([
            ':user_id' => $notification->getUserId(),
            ':title' => $notification->getTitle(),
            ':message' => $notification->getMessage(),
            ':type' => $notification->getType()->value,
            ':reference_type' => $notification->getReferenceType(),
            ':reference_id' => $notification->getReferenceId(),
            ':deduplication_key' => $notification->getDeduplicationKey(),
        ]);

        $id = (int) $this->pdo->lastInsertId();
        return $this->findById($id) ?? $notification;
    }
}

