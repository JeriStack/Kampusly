<?php

declare(strict_types=1);

namespace Kampusly\Tests\Unit\IdentityAccess;

use Kampusly\Shared\Infrastructure\Http\RateLimiter;
use Kampusly\Tests\Support\TestCase;

final class RateLimiterTest extends TestCase
{
    private string $cacheDir;
    private RateLimiter $limiter;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/kampusly_rate_limiter_tests_' . bin2hex(random_bytes(4));
        $this->limiter = new RateLimiter($this->cacheDir);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->cacheDir)) {
            $files = glob($this->cacheDir . '/*');
            if ($files !== false) {
                foreach ($files as $file) {
                    @unlink($file);
                }
            }
            @rmdir($this->cacheDir);
        }
    }

    public function testRateLimiterEnforcesMaxAttempts(): void
    {
        $key = 'auth_login:ip_127_0_0_1';
        $maxAttempts = 3;
        $decaySeconds = 60;

        // Attempts 1, 2, 3 should be permitted
        for ($i = 1; $i <= $maxAttempts; $i++) {
            $this->assertFalse($this->limiter->tooManyAttempts($key, $maxAttempts));
            $attempts = $this->limiter->hit($key, $decaySeconds);
            $this->assertSame($i, $attempts);
        }

        // 4th attempt exceeds max attempts
        $this->assertTrue($this->limiter->tooManyAttempts($key, $maxAttempts));
        $this->assertTrue($this->limiter->availableIn($key) > 0);

        // Reset clears the limit
        $this->limiter->reset($key);
        $this->assertFalse($this->limiter->tooManyAttempts($key, $maxAttempts));
        $this->assertSame(0, $this->limiter->availableIn($key));
    }

    public function testDifferentKeysAreIsolated(): void
    {
        $keyA = 'auth_login:client_a';
        $keyB = 'auth_login:client_b';

        $this->limiter->hit($keyA, 60);
        $this->limiter->hit($keyA, 60);

        $this->assertTrue($this->limiter->tooManyAttempts($keyA, 2));
        $this->assertFalse($this->limiter->tooManyAttempts($keyB, 2));
    }
}

