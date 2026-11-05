<?php

declare(strict_types=1);

namespace Kampusly\Tests\Support;

use Kampusly\Academic\Domain\Entity\Room;
use Kampusly\Academic\Domain\Repository\RoomRepositoryInterface;
use Kampusly\Shared\Application\DTO\PaginatedResult;
use Kampusly\Shared\Domain\ValueObject\PaginationQuery;
use ReflectionClass;

final class InMemoryRoomRepository implements RoomRepositoryInterface
{
    /** @var array<int, Room> */
    private array $rooms = [];
    private int $nextId = 1;
    /** @var array<int, bool> */
    private array $scheduledRoomIds = [];

    /**
     * @param array<int, Room> $initial
     */
    public function __construct(array $initial = [])
    {
        foreach ($initial as $room) {
            $id = $room->getId() ?? $this->nextId++;
            $this->rooms[$id] = $room;
            if ($id >= $this->nextId) {
                $this->nextId = $id + 1;
            }
        }
    }

    public function setHasSchedules(int $roomId, bool $has): void
    {
        $this->scheduledRoomIds[$roomId] = $has;
    }

    public function findById(int $id): ?Room
    {
        return $this->rooms[$id] ?? null;
    }

    public function findByCode(string $roomCode): ?Room
    {
        $normalized = strtoupper(trim($roomCode));
        foreach ($this->rooms as $room) {
            if (strtoupper($room->getRoomCode()) === $normalized) {
                return $room;
            }
        }
        return null;
    }

    public function paginate(
        PaginationQuery $pagination,
        ?string $status = null,
        ?string $building = null,
        ?string $search = null
    ): PaginatedResult {
        $filtered = array_filter($this->rooms, function (Room $r) use ($status, $building, $search): bool {
            if ($status !== null && $status !== '' && $r->getStatus()->value !== $status) {
                return false;
            }
            if ($building !== null && $building !== '' && $r->getBuilding() !== $building) {
                return false;
            }
            if ($search !== null && $search !== '') {
                $q = strtolower($search);
                $matchCode = str_contains(strtolower($r->getRoomCode()), $q);
                $matchName = str_contains(strtolower($r->getRoomName()), $q);
                if (!$matchCode && !$matchName) {
                    return false;
                }
            }
            return true;
        });

        $total = count($filtered);
        $offset = $pagination->getOffset();
        $limit = $pagination->getPerPage();
        $slice = array_slice(array_values($filtered), $offset, $limit);
        $items = array_map(static fn (Room $r): array => $r->toArray(), $slice);

        return new PaginatedResult($items, $pagination->getPage(), $pagination->getPerPage(), $total);
    }

    public function create(Room $room): Room
    {
        $id = $this->nextId++;
        $reflection = new ReflectionClass($room);
        $prop = $reflection->getProperty('id');
        $prop->setValue($room, $id);

        $this->rooms[$id] = $room;
        return $room;
    }

    public function update(Room $room): Room
    {
        $id = $room->getId();
        if ($id !== null) {
            $this->rooms[$id] = $room;
        }
        return $room;
    }

    public function delete(int $id): bool
    {
        if (isset($this->rooms[$id])) {
            unset($this->rooms[$id]);
            return true;
        }
        return false;
    }

    public function hasSchedules(int $roomId): bool
    {
        return $this->scheduledRoomIds[$roomId] ?? false;
    }
}
