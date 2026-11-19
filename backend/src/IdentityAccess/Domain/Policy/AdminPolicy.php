<?php

declare(strict_types=1);

namespace Kampusly\IdentityAccess\Domain\Policy;

use Kampusly\IdentityAccess\Domain\Entity\User;
use Kampusly\IdentityAccess\Domain\ValueObject\UserRole;

final class AdminPolicy
{
    public function canManageUsers(User $actor): bool
    {
        return $actor->getRole() === UserRole::Admin && $actor->isActive();
    }

    public function canManageAcademicData(User $actor): bool
    {
        return $actor->getRole() === UserRole::Admin && $actor->isActive();
    }

    public function canChangeUserStatus(User $actor, User $target): bool
    {
        if ($actor->getRole() !== UserRole::Admin || !$actor->isActive()) {
            return false;
        }

        // Prevent self-deactivation by administrators to prevent administrative lockout
        if ($actor->getId() === $target->getId()) {
            return false;
        }

        return true;
    }

    public function canViewDashboardSummary(User $actor): bool
    {
        return $actor->getRole() === UserRole::Admin && $actor->isActive();
    }
}

