<?php

declare(strict_types=1);

namespace Kampusly\IdentityAccess\Domain\Policy;

use Kampusly\IdentityAccess\Domain\Entity\User;
use Kampusly\IdentityAccess\Domain\ValueObject\UserRole;

final class StudentPolicy
{
    public function canAccessStudentTimetable(User $actor, int $targetSectionId, ?int $actorSectionId): bool
    {
        if ($actor->getRole() === UserRole::Admin || $actor->getRole() === UserRole::Instructor) {
            return true;
        }

        if ($actor->getRole() === UserRole::Student && $actorSectionId !== null) {
            return $actorSectionId === $targetSectionId;
        }

        return false;
    }

    public function canAccessOwnProfile(User $actor, int $targetUserId): bool
    {
        if ($actor->getRole() === UserRole::Admin) {
            return true;
        }

        return $actor->getId() === $targetUserId;
    }

    public function canAccessOwnNotifications(User $actor, int $targetUserId): bool
    {
        return $actor->getId() === $targetUserId;
    }
}

