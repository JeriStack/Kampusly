<?php

declare(strict_types=1);

namespace Kampusly\Shared\Infrastructure\Http;

final class PhpSessionManager implements SessionInterface
{
    private string $cookieName;
    private int $idleTimeoutSeconds;
    private int $absoluteTimeoutSeconds;
    private bool $cookieSecure;
    private string $cookieSameSite;
    private bool $cookieHttpOnly;
    private bool $isExpired = false;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->cookieName = (string) ($config['session_cookie_name'] ?? 'kampusly_session');
        $idleMinutes = (int) ($config['session_idle_timeout_minutes'] ?? 30);
        $this->idleTimeoutSeconds = max(60, $idleMinutes * 60);

        $absHours = (int) ($config['session_absolute_timeout_hours'] ?? 8);
        $this->absoluteTimeoutSeconds = max(3600, $absHours * 3600);

        $this->cookieSecure = (bool) ($config['session_cookie_secure'] ?? false);
        $this->cookieSameSite = (string) ($config['session_cookie_samesite'] ?? 'Lax');
        $this->cookieHttpOnly = (bool) ($config['session_cookie_httponly'] ?? true);
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->validateTimeouts();
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');

        session_name($this->cookieName);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $this->cookieSecure,
            'httponly' => $this->cookieHttpOnly,
            'samesite' => $this->cookieSameSite,
        ]);

        if (!headers_sent()) {
            @session_start();
        }

        $this->validateTimeouts();
    }

    public function isStarted(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    public function getId(): string
    {
        return session_id() !== false ? session_id() : '';
    }

    public function regenerateId(bool $deleteOldSession = true): bool
    {
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            return session_regenerate_id($deleteOldSession);
        }

        return false;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->isStarted()) {
            $this->start();
        }

        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        if (!$this->isStarted()) {
            $this->start();
        }

        $_SESSION[$key] = $value;
        $_SESSION['last_activity_at'] = time();
    }

    public function has(string $key): bool
    {
        if (!$this->isStarted()) {
            $this->start();
        }

        return array_key_exists($key, $_SESSION);
    }

    public function remove(string $key): void
    {
        if (!$this->isStarted()) {
            $this->start();
        }

        unset($_SESSION[$key]);
    }

    public function clear(): void
    {
        if ($this->isStarted()) {
            $_SESSION = [];
        }
    }

    public function destroy(): void
    {
        if ($this->isStarted()) {
            $_SESSION = [];
            if (!headers_sent()) {
                setcookie(
                    $this->cookieName,
                    '',
                    [
                        'expires' => time() - 86400,
                        'path' => '/',
                        'secure' => $this->cookieSecure,
                        'httponly' => $this->cookieHttpOnly,
                        'samesite' => $this->cookieSameSite,
                    ]
                );
                @session_destroy();
            }
        }
    }

    public function isExpired(): bool
    {
        return $this->isExpired;
    }

    private function validateTimeouts(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $now = time();

        if (isset($_SESSION['login_at']) && is_int($_SESSION['login_at'])) {
            if ($now - $_SESSION['login_at'] > $this->absoluteTimeoutSeconds) {
                $this->isExpired = true;
                $this->destroy();
                return;
            }
        }

        if (isset($_SESSION['last_activity_at']) && is_int($_SESSION['last_activity_at'])) {
            if ($now - $_SESSION['last_activity_at'] > $this->idleTimeoutSeconds) {
                $this->isExpired = true;
                $this->destroy();
                return;
            }
        }

        $_SESSION['last_activity_at'] = $now;
    }
}

