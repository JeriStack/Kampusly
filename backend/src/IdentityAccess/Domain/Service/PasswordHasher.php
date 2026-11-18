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
     * Generate a random password that always satisfies the password policy.
     *
     * Uses a cryptographically secure source, avoids look-alike characters (I, O, l, 0, 1)
     * and guarantees at least one letter, one number and one symbol.
     */
    public function generate(int $length = 12): string
    {
        $length = max(self::MIN_LENGTH, min($length, self::MAX_LENGTH));
        $letters = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        $digits = '23456789';
        $symbols = '!@#$%&*?-_';
        $all = $letters . $digits . $symbols;

        $characters = [
            $this->pick($letters),
            $this->pick($digits),
            $this->pick($symbols),
        ];
        while (count($characters) < $length) {
            $characters[] = $this->pick($all);
        }

        // Fisher-Yates shuffle with a secure RNG so the guaranteed characters are not at fixed positions.
        for ($i = count($characters) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$characters[$i], $characters[$j]] = [$characters[$j], $characters[$i]];
        }

        return implode('', $characters);
    }

    private function pick(string $set): string
    {
        return $set[random_int(0, strlen($set) - 1)];
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

