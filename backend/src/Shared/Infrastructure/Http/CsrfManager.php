<?php

declare(strict_types=1);

namespace Kampusly\Shared\Infrastructure\Http;

final class CsrfManager
{
    private SessionInterface $session;

    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    /**
     * Retrieve the active CSRF synchronizer token, generating one if not present.
     */
    public function getToken(): string
    {
        $token = $this->session->get('csrf_token');
        if (is_string($token) && $token !== '') {
            return $token;
        }

        return $this->regenerateToken();
    }

    /**
     * Generate and store a fresh 64-character cryptographic token.
     */
    public function regenerateToken(): string
    {
        $freshToken = bin2hex(random_bytes(32));
        $this->session->set('csrf_token', $freshToken);

        return $freshToken;
    }

    /**
     * Verify a submitted token using timing-attack safe comparison.
     */
    public function validateToken(?string $providedToken): bool
    {
        if ($providedToken === null || trim($providedToken) === '') {
            return false;
        }

        $sessionToken = $this->session->get('csrf_token');
        if (!is_string($sessionToken) || $sessionToken === '') {
            return false;
        }

        return hash_equals($sessionToken, trim($providedToken));
    }
}

