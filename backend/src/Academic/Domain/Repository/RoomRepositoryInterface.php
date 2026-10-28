<?php

declare(strict_types=1);

namespace Kampusly\Academic\Domain\Repository;

use Kampusly\Academic\Domain\Entity\Room;
use Kampusly\Shared\Application\DTO\PaginatedResult;
use Kampusly\Shared\Domain\ValueObject\PaginationQuery;

interface RoomRepositoryInterface
{
    public function findById(int $id): ?Room;

    public function findByCode(string $roomCode): ?Room;

    public function paginate(
        PaginationQuery $pagination,
        ?string $status = null,
        ?string $building = null,
        ?string $search = null
    ): PaginatedResult;

    public function create(Room $room): Room;
}

