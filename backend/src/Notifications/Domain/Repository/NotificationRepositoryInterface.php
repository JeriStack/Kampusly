<?php

declare(strict_types=1);

namespace Kampusly\Notifications\Domain\Repository;

use Kampusly\Notifications\Domain\Entity\Notification;
use Kampusly\Shared\Application\DTO\PaginatedResult;
use Kampusly\Shared\Domain\ValueObject\PaginationQuery;

interface NotificationRepositoryInterface
{
    public function findById(int $id): ?Notification;

    public function paginateForUser(
        int $userId,
        PaginationQuery $pagination,
        ?bool $unreadOnly = null
    ): PaginatedResult;

    public function create(Notification $notification): Notification;

    public function countUnreadForUser(int $userId): int;

    public function markAsRead(int $userId, int $notificationId): bool;

    public function markAllAsRead(int $userId): int;
}
