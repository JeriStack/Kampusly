<?php

declare(strict_types=1);

namespace Kampusly\Tests\Unit\IdentityAccess;

use Kampusly\Shared\Infrastructure\Http\ArraySessionManager;
use Kampusly\Shared\Infrastructure\Http\CsrfManager;
use Kampusly\Tests\Support\TestCase;

final class SessionAndCsrfTest extends TestCase
{
    public function testArraySessionManagerStoreAndRetrieve(): void
    {
        $session = new ArraySessionManager();
        $this->assertFalse($session->has('user_id'));
        $this->assertNull($session->get('user_id'));

        $session->set('user_id', 42);
        $this->assertTrue($session->has('user_id'));
        $this->assertSame(42, $session->get('user_id'));

        $session->remove('user_id');
        $this->assertFalse($session->has('user_id'));
    }

    public function testSessionRegenerationChangesIdAndPreservesAttributes(): void
    {
        $session = new ArraySessionManager();
        $originalId = $session->getId();
        $session->set('auth_user_role', 'instructor');

        $session->regenerateId();
        $newId = $session->getId();

        $this->assertFalse($originalId === $newId);
        $this->assertSame('instructor', $session->get('auth_user_role'));
    }

    public function testSessionExpirationOnIdleTimeout(): void
    {
        // 1 minute idle timeout
        $session = new ArraySessionManager(1, 8);
        $session->start();
        $session->set('auth_user_id', 1);

        // Not expired immediately
        $this->assertFalse($session->isExpired());

        // Fast-forward simulated last activity by 70 seconds
        $session->simulateTimeAdvance(70);
        $this->assertTrue($session->isExpired());
    }

    public function testSessionExpirationOnAbsoluteTimeout(): void
    {
        // 60 min idle, 1 hour absolute timeout
        $session = new ArraySessionManager(60, 1);
        $session->start();
        $session->set('auth_user_id', 1);

        $this->assertFalse($session->isExpired());

        // Fast-forward by 3700 seconds (> 1 hour absolute)
        $session->simulateTimeAdvance(3700);
        $this->assertTrue($session->isExpired());
    }

    public function testCsrfManagerGeneratesAndValidatesToken(): void
    {
        $session = new ArraySessionManager();
        $csrfManager = new CsrfManager($session);

        $token1 = $csrfManager->getToken();
        $this->assertSame(64, strlen($token1));
        $this->assertTrue($csrfManager->validateToken($token1));

        // Reusing same session returns the same token
        $this->assertSame($token1, $csrfManager->getToken());

        // Invalid or null tokens are safely rejected
        $this->assertFalse($csrfManager->validateToken(null));
        $this->assertFalse($csrfManager->validateToken(''));
        $this->assertFalse($csrfManager->validateToken('invalid_token_hash_value_that_does_not_match'));
        $this->assertFalse($csrfManager->validateToken($token1 . 'tampered'));

        // Regenerating token issues a fresh token and invalidates old
        $token2 = $csrfManager->regenerateToken();
        $this->assertFalse($token1 === $token2);
        $this->assertTrue($csrfManager->validateToken($token2));
        $this->assertFalse($csrfManager->validateToken($token1));
    }
}
