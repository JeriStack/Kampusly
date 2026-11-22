<?php

declare(strict_types=1);

namespace Kampusly\Academic\Infrastructure\Persistence;

use Kampusly\Academic\Domain\Entity\Room;
use Kampusly\Academic\Domain\Repository\RoomRepositoryInterface;
use Kampusly\Shared\Application\DTO\PaginatedResult;
use Kampusly\Shared\Domain\ValueObject\PaginationQuery;
use PDO;

final class PdoRoomRepository implements RoomRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(int $id): ?Room
    {
        $stmt = $this->pdo->prepare('SELECT * FROM `rooms` WHERE `id` = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? Room::fromDatabaseRow($row) : null;
    }

    public function findByCode(string $roomCode): ?Room
    {
        $stmt = $this->pdo->prepare('SELECT * FROM `rooms` WHERE `room_code` = :code LIMIT 1');
        $stmt->execute([':code' => strtoupper(trim($roomCode))]);
        $row = $stmt->fetch();

        return is_array($row) ? Room::fromDatabaseRow($row) : null;
    }

    public function paginate(
        PaginationQuery $pagination,
        ?string $status = null,
        ?string $building = null,
        ?string $search = null
    ): PaginatedResult {
        $conditions = ['`archived_at` IS NULL'];
        $params = [];

        if ($status !== null && $status !== '') {
            $conditions[] = '`status` = :status';
            $params[':status'] = $status;
        }

        if ($building !== null && trim($building) !== '') {
            $conditions[] = '`building` = :building';
            $params[':building'] = trim($building);
        }

        if ($search !== null && trim($search) !== '') {
            $conditions[] = '(`room_code` LIKE :search OR `room_name` LIKE :search_name)';
            $wildcard = '%' . trim($search) . '%';
            $params[':search'] = $wildcard;
            $params[':search_name'] = $wildcard;
        }

        $whereSql = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM `rooms` {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT * FROM `rooms` {$whereSql} ORDER BY `room_code` ASC LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $pagination->getPerPage(), PDO::PARAM_INT);
        $stmt->bindValue(':offset', $pagination->getOffset(), PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        $items = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $items[] = Room::fromDatabaseRow($row)->toArray();
            }
        }

        return new PaginatedResult(
            $items,
            $pagination->getPage(),
            $pagination->getPerPage(),
            $total
        );
    }

    public function create(Room $room): Room
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO `rooms` (`room_code`, `room_name`, `building`, `capacity`, `status`)
             VALUES (:room_code, :room_name, :building, :capacity, :status)'
        );
        $stmt->execute([
            ':room_code' => $room->getRoomCode(),
            ':room_name' => $room->getRoomName(),
            ':building' => $room->getBuilding(),
            ':capacity' => $room->getCapacity(),
            ':status' => $room->getStatus()->value,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        return $this->findById($id) ?? $room;
    }

    public function update(Room $room): Room
    {
        $stmt = $this->pdo->prepare(
            'UPDATE `rooms`
             SET `room_code` = :room_code,
                 `room_name` = :room_name,
                 `building` = :building,
                 `capacity` = :capacity,
                 `status` = :status
             WHERE `id` = :id'
        );
        $stmt->execute([
            ':id' => $room->getId(),
            ':room_code' => $room->getRoomCode(),
            ':room_name' => $room->getRoomName(),
            ':building' => $room->getBuilding(),
            ':capacity' => $room->getCapacity(),
            ':status' => $room->getStatus()->value,
        ]);

        return $this->findById((int) $room->getId()) ?? $room;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM `rooms` WHERE `id` = :id');
        return $stmt->execute([':id' => $id]);
    }

    public function hasSchedules(int $roomId): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM `schedules` WHERE `room_id` = :id');
        $stmt->execute([':id' => $roomId]);
        return ((int) $stmt->fetchColumn()) > 0;
    }
}
