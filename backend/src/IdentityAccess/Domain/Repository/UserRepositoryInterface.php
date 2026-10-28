<?php

declare(strict_types=1);

namespace Kampusly\IdentityAccess\Domain\Repository;

use Kampusly\IdentityAccess\Domain\Entity\User;
use Kampusly\Shared\Application\DTO\PaginatedResult;
use Kampusly\Shared\Domain\ValueObject\PaginationQuery;

interface UserRepositoryInterface
{
    public function findById(int $id): ?User;

    public function findByEmail(string $email): ?User;

    public function paginate(
        PaginationQuery $pagination,
        ?string $role = null,
        ?string $status = null,
        ?string $search = null
    ): PaginatedResult;

    public function create(User $user): User;
}

