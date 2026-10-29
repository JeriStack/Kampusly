<?php

declare(strict_types=1);

namespace Kampusly\IdentityAccess\Domain\Service;

final class PasswordHasher
{
    public const MIN_LENGTH = 8;
    public const MAX_LENGTH = 128;

    /**
     * Hash a plaintext password using the default strong algorithm (bcrypt in modern PHP).
     */
    public function hash(string $plainText): string
    {
        return password_hash($plainText, PASSWORD_DEFAULT);
    }

    /**
     * Verify a plaintext password against an existing hash using timing-attack resistant comparison.
     */
    public function verify(string $plainText, string $hash): bool
    {
        return password_verify($plainText, $hash);
    }

    /**
     * Check if a stored hash needs to be upgraded due to algorithmic or cost changes.
     */
    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_DEFAULT);
    }

    /**
     * Validate password complexity against organizational requirements.
     *
     * @return list<string> Validation error messages (empty if valid)
     */
    public function validatePolicy(string $password): array
    {
        $errors = [];
        $length = mb_strlen($password);

        if ($length < self::MIN_LENGTH) {
            $errors[] = sprintf('Password must be at least %d characters long.', self::MIN_LENGTH);
        }

        if ($length > self::MAX_LENGTH) {
            $errors[] = sprintf('Password must not exceed %d characters.', self::MAX_LENGTH);
        }

        if (preg_match('/[a-zA-Z]/', $password) !== 1) {
            $errors[] = 'Password must contain at least one letter.';
        }

        if (preg_match('/[0-9]/', $password) !== 1 && preg_match('/[^a-zA-Z0-9]/', $password) !== 1) {
            $errors[] = 'Password must contain at least one number or special character.';
        }

        return $errors;
    }
}

