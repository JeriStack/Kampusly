<?php

declare(strict_types=1);

namespace Kampusly\Shared\Infrastructure\Logging;

final class LoggerFactory
{
    private const LEVEL_PRIORITY = [
        'debug' => 100,
        'info' => 200,
        'warning' => 300,
        'error' => 400,
        'critical' => 500,
    ];

    private const SENSITIVE_KEYS = [
        'password',
        'password_hash',
        'db_password',
        'db_root_password',
        'smtp_password',
        'secret',
        'token',
        'authorization',
        'cookie',
    ];

    private string $logPath;
    private string $minLevel;
    private string $environment;

    public function __construct(string $logPath, string $minLevel = 'debug', string $environment = 'local')
    {
        $this->logPath = $logPath;
        $this->minLevel = array_key_exists($minLevel, self::LEVEL_PRIORITY) ? $minLevel : 'debug';
        $this->environment = $environment;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $normalizedLevel = strtolower($level);
        $priority = self::LEVEL_PRIORITY[$normalizedLevel] ?? 200;
        $threshold = self::LEVEL_PRIORITY[$this->minLevel] ?? 100;

        if ($priority < $threshold) {
            return;
        }

        $entry = [
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'level' => strtoupper($normalizedLevel),
            'env' => $this->environment,
            'message' => $message,
            'context' => $this->redactSecrets($context),
        ];

        $directory = dirname($this->logPath);
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($line !== false) {
            @file_put_contents($this->logPath, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function redactSecrets(array $context): array
    {
        $sanitized = [];
        foreach ($context as $key => $value) {
            $lowerKey = strtolower((string) $key);
            if ($this->isSensitiveKey($lowerKey)) {
                $sanitized[$key] = '[REDACTED]';
                continue;
            }

            if (is_array($value)) {
                /** @var array<string, mixed> $nested */
                $nested = $value;
                $sanitized[$key] = $this->redactSecrets($nested);
                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    private function isSensitiveKey(string $lowerKey): bool
    {
        foreach (self::SENSITIVE_KEYS as $needle) {
            if (str_contains($lowerKey, $needle)) {
                return true;
            }
        }

        return false;
    }
}

