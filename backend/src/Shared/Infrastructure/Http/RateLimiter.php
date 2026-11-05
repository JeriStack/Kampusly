<?php

declare(strict_types=1);

namespace Kampusly\Shared\Infrastructure\Http;

use Kampusly\Shared\Domain\Exception\RateLimitExceededException;

final class RateLimiter
{
    private string $storageDir;
    /** @var array<string, array{count: int, reset_at: int}> */
    private array $memoryCache = [];
    private bool $useMemoryOnly;

    public function __construct(?string $storageDir = null, bool $useMemoryOnly = false)
    {
        $this->useMemoryOnly = $useMemoryOnly;
        $this->storageDir = $storageDir ?? sys_get_temp_dir() . '/kampusly_rate_limits';
        if (!$this->useMemoryOnly && !is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0775, true);
        }
    }

    /**
     * Increment attempts for a key and return the new attempt count.
     */
    public function hit(string $key, int $decaySeconds = 60): int
    {
        $sanitizedKey = self::hashKey($key);
        $now = time();

        if ($this->useMemoryOnly) {
            $data = $this->memoryCache[$sanitizedKey] ?? ['count' => 0, 'reset_at' => $now + $decaySeconds];
            if ($now >= $data['reset_at']) {
                $data = ['count' => 1, 'reset_at' => $now + $decaySeconds];
            } else {
                $data['count']++;
            }
            $this->memoryCache[$sanitizedKey] = $data;

            return $data['count'];
        }

        $file = $this->storageDir . '/' . $sanitizedKey . '.json';
        $data = ['count' => 0, 'reset_at' => $now + $decaySeconds];

        $fp = @fopen($file, 'c+');
        if ($fp !== false) {
            flock($fp, LOCK_EX);
            $content = stream_get_contents($fp);
            if (is_string($content) && trim($content) !== '') {
                $decoded = json_decode($content, true);
                if (is_array($decoded) && isset($decoded['reset_at'], $decoded['count'])) {
                    if ($now < (int) $decoded['reset_at']) {
                        $data = [
                            'count' => (int) $decoded['count'],
                            'reset_at' => (int) $decoded['reset_at'],
                        ];
                    }
                }
            }

            $data['count']++;
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, (string) json_encode($data));
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
        }

        return $data['count'];
    }

    /**
     * Check if attempts exceed maximum allowed within window.
     */
    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        return $this->attempts($key) >= $maxAttempts;
    }

    /**
     * Get current number of attempts within the active window.
     */
    public function attempts(string $key): int
    {
        $sanitizedKey = self::hashKey($key);
        $now = time();

        if ($this->useMemoryOnly) {
            $data = $this->memoryCache[$sanitizedKey] ?? null;
            if ($data === null || $now >= $data['reset_at']) {
                return 0;
            }

            return $data['count'];
        }

        $file = $this->storageDir . '/' . $sanitizedKey . '.json';
        if (!is_file($file)) {
            return 0;
        }

        $content = @file_get_contents($file);
        if ($content === false) {
            return 0;
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded) || !isset($decoded['reset_at'], $decoded['count'])) {
            return 0;
        }

        if ($now >= (int) $decoded['reset_at']) {
            @unlink($file);
            return 0;
        }

        return (int) $decoded['count'];
    }

    /**
     * Return seconds remaining until current throttle window expires.
     */
    public function availableIn(string $key, int $decaySeconds = 60): int
    {
        $sanitizedKey = self::hashKey($key);
        $now = time();

        if ($this->useMemoryOnly) {
            $data = $this->memoryCache[$sanitizedKey] ?? null;
            if ($data === null || $now >= $data['reset_at']) {
                return 0;
            }

            return max(1, $data['reset_at'] - $now);
        }

        $file = $this->storageDir . '/' . $sanitizedKey . '.json';
        if (!is_file($file)) {
            return 0;
        }

        $content = @file_get_contents($file);
        if ($content === false) {
            return 0;
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded) || !isset($decoded['reset_at'])) {
            return 0;
        }

        $resetAt = (int) $decoded['reset_at'];
        return max(1, $resetAt - $now);
    }

    /**
     * Clear all recorded attempts for a key (e.g. after successful login).
     */
    public function reset(string $key): void
    {
        $sanitizedKey = self::hashKey($key);
        if ($this->useMemoryOnly) {
            unset($this->memoryCache[$sanitizedKey]);
            return;
        }

        $file = $this->storageDir . '/' . $sanitizedKey . '.json';
        if (is_file($file)) {
            @unlink($file);
        }
    }

    /**
     * Enforce a throttle limit or throw RateLimitExceededException (429).
     */
    public function enforce(string $key, int $maxAttempts, int $decaySeconds = 60): void
    {
        if ($this->tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = $this->availableIn($key, $decaySeconds);
            throw new RateLimitExceededException($retryAfter);
        }
    }

    /**
     * Safely resolve client IP without trusting spoofable forward headers.
     */
    public static function resolveClientIp(HttpRequest $request): string
    {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? null;
        if (is_string($remoteAddr) && filter_var($remoteAddr, FILTER_VALIDATE_IP) !== false) {
            return $remoteAddr;
        }

        return '127.0.0.1';
    }

    private static function hashKey(string $key): string
    {
        return hash('sha256', strtolower(trim($key)));
    }
}

