<?php

declare(strict_types=1);

namespace Kampusly\Tests\Unit\IdentityAccess;

use Kampusly\IdentityAccess\Domain\Entity\User;
use Kampusly\IdentityAccess\Domain\ValueObject\AccountStatus;
use Kampusly\IdentityAccess\Domain\ValueObject\UserRole;
use Kampusly\Tests\Support\TestCase;

final class UserEntityTest extends TestCase
{
    public function testUserPasswordVerificationAndState(): void
    {
        $hash = password_hash('SecretP@ss123', PASSWORD_BCRYPT);
        $user = new User(
            1,
            'Test Administrator',
            'admin@kampusly.local',
            $hash,
            UserRole::Admin,
            AccountStatus::Active
        );

        $this->assertTrue($user->isActive());
        $this->assertTrue($user->verifyPassword('SecretP@ss123'));
        $this->assertFalse($user->verifyPassword('WrongPassword'));
        $this->assertSame(UserRole::Admin, $user->getRole());
    }

    public function testUserToArrayStrictlyOmitsPasswordHash(): void
    {
        $hash = password_hash('SecretP@ss123', PASSWORD_BCRYPT);
        $user = new User(
            1,
            'Test Administrator',
            'admin@kampusly.local',
            $hash,
            UserRole::Admin,
            AccountStatus::Active
        );

        $array = $user->toArray();
        $this->assertFalse(array_key_exists('password_hash', $array));
        $this->assertFalse(array_key_exists('password', $array));
        $this->assertSame('admin@kampusly.local', $array['email']);
        $this->assertSame('admin', $array['role']);
    }
}

