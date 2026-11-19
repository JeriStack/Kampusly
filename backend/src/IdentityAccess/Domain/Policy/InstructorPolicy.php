<?php

declare(strict_types=1);

namespace Kampusly\IdentityAccess\Domain\Policy;

use Kampusly\IdentityAccess\Domain\Entity\User;
use Kampusly\IdentityAccess\Domain\ValueObject\UserRole;

final class InstructorPolicy
{
    public function canAccessInstructorSchedule(User $actor, int $assignedInstructorId, ?int $actorInstructorId): bool
    {
        if ($actor->getRole() === UserRole::Admin) {
            return true;
        }

        if ($actor->getRole() === UserRole::Instructor && $actorInstructorId !== null) {
            return $actorInstructorId === $assignedInstructorId;
        }

        return false;
    }

    public function canSubmitNotice(User $actor, int $targetInstructorId, ?int $actorInstructorId): bool
    {
        if ($actor->getRole() === UserRole::Admin) {
            return true;
        }

        if ($actor->getRole() === UserRole::Instructor && $actorInstructorId !== null) {
            return $actorInstructorId === $targetInstructorId;
        }

        return false;
    }

    public function canReviewNotice(User $actor): bool
    {
        return $actor->getRole() === UserRole::Admin && $actor->isActive();
    }
}

