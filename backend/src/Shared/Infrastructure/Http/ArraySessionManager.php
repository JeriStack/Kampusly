<?php

declare(strict_types=1);

namespace Kampusly\Shared\Infrastructure\Http;

final class ArraySessionManager implements SessionInterface
{
    /** @var array<string, mixed> */
    private array $data = [];
    private string $id;
    private bool $started = false;
    private bool $isExpired = false;
    private int $idleTimeoutSeconds;
    private int $absoluteTimeoutSeconds;

    public function __construct(int $idleTimeoutMinutes = 30, int $absoluteTimeoutHours = 8)
    {
        $this->id = 'sess_' . bin2hex(random_bytes(16));
        $this->idleTimeoutSeconds = max(60, $idleTimeoutMinutes * 60);
        $this->absoluteTimeoutSeconds = max(3600, $absoluteTimeoutHours * 3600);
    }

    public function start(): void
    {
        $this->started = true;
        $this->validateTimeouts();
    }

    public function isStarted(): bool
    {
        return $this->started;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function regenerateId(bool $deleteOldSession = true): bool
    {
        $this->id = 'sess_' . bin2hex(random_bytes(16));
        if ($deleteOldSession) {
            $preserve = [
                'auth_user_id' => $this->data['auth_user_id'] ?? null,
                'auth_user_role' => $this->data['auth_user_role'] ?? null,
                'csrf_token' => $this->data['csrf_token'] ?? null,
                'login_at' => $this->data['login_at'] ?? time(),
                'last_activity_at' => time(),
            ];
            $this->data = array_filter($preserve, static fn ($v): bool => $v !== null);
        }

        return true;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
        $this->data['last_activity_at'] = time();
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    public function clear(): void
    {
        $this->data = [];
    }

    public function destroy(): void
    {
        $this->data = [];
        $this->started = false;
    }

    public function isExpired(): bool
    {
        return $this->isExpired;
    }

    public function simulateTimeAdvance(int $seconds): void
    {
        if (isset($this->data['last_activity_at']) && is_int($this->data['last_activity_at'])) {
            $this->data['last_activity_at'] -= $seconds;
        }
        if (isset($this->data['login_at']) && is_int($this->data['login_at'])) {
            $this->data['login_at'] -= $seconds;
        }
        $this->validateTimeouts();
    }

    private function validateTimeouts(): void
    {
        $now = time();

        if (isset($this->data['login_at']) && is_int($this->data['login_at'])) {
            if ($now - $this->data['login_at'] > $this->absoluteTimeoutSeconds) {
                $this->isExpired = true;
                $this->destroy();
                return;
            }
        }

        if (isset($this->data['last_activity_at']) && is_int($this->data['last_activity_at'])) {
            if ($now - $this->data['last_activity_at'] > $this->idleTimeoutSeconds) {
                $this->isExpired = true;
                $this->destroy();
                return;
            }
        }

        if ($this->started) {
            $this->data['last_activity_at'] = $now;
        }
    }
}

