<?php

declare(strict_types=1);

namespace Kampusly\Tests\Unit\IdentityAccess;

use Kampusly\IdentityAccess\Domain\Service\PasswordHasher;
use Kampusly\Shared\Domain\Exception\ValidationException;
use Kampusly\Tests\Support\TestCase;

final class PasswordSecurityTest extends TestCase
{
    private PasswordHasher $hasher;

    protected function setUp(): void
    {
        $this->hasher = new PasswordHasher();
    }

    public function testPasswordHashingAndVerification(): void
    {
        $plain = 'CorrectHorseBatteryStaple123!';
        $hash = $this->hasher->hash($plain);

        $this->assertNotNull($hash);
        $this->assertFalse(str_contains($hash, $plain));
        $this->assertTrue($this->hasher->verify($plain, $hash));
        $this->assertFalse($this->hasher->verify('WrongPassword123!', $hash));
        $this->assertFalse($this->hasher->verify('', $hash));
    }

    public function testPasswordPolicyAcceptsStrongPasswords(): void
    {
        // 8+ chars with letters and numbers/symbols
        $this->assertCount(0, $this->hasher->validatePolicy('ValidPass123!'));
        $this->assertCount(0, $this->hasher->validatePolicy('kampusly2026'));
        $this->assertCount(0, $this->hasher->validatePolicy('Secure_Admin_#99'));
    }

    public function testPasswordPolicyRejectsShortOrSimplePasswords(): void
    {
        $shortErrors = $this->hasher->validatePolicy('short');
        $this->assertTrue(count($shortErrors) > 0);

        $noNumberErrors = $this->hasher->validatePolicy('onlylowercaseletters');
        $this->assertTrue(count($noNumberErrors) > 0);

        $noLetterErrors = $this->hasher->validatePolicy('1234567890');
        $this->assertTrue(count($noLetterErrors) > 0);
    }

    public function testPasswordNeedsRehashReturnsFalseForCurrentCost(): void
    {
        $hash = $this->hasher->hash('CurrentCostPass123!');
        $this->assertFalse($this->hasher->needsRehash($hash));
    }
}
