<?php

declare(strict_types=1);

namespace Kampusly\Bootstrap;

use Kampusly\Shared\Domain\Exception\ConfigurationException;

final class EnvLoader
{
    private const ALLOWED_ENVIRONMENTS = ['local', 'development', 'testing', 'staging', 'production'];

    /**
     * Load key=value pairs from one or more .env files without overwriting
     * variables already present in the process environment.
     *
     * @param list<string> $paths
     */
    public static function load(array $paths): void
    {
        foreach ($paths as $path) {
            if (!is_file($path)) {
                continue;
            }

            $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false) {
                continue;
            }

            foreach ($lines as $line) {
                $trimmed = trim($line);
                if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                    continue;
                }

                $separatorPos = strpos($trimmed, '=');
                if ($separatorPos === false) {
                    continue;
                }

                $name = trim(substr($trimmed, 0, $separatorPos));
                $rawValue = trim(substr($trimmed, $separatorPos + 1));

                if ($name === '') {
                    continue;
                }

                $value = self::stripQuotes($rawValue);

                if (getenv($name) === false && !array_key_exists($name, $_ENV)) {
                    $_ENV[$name] = $value;
                    $_SERVER[$name] = $value;
                    putenv(sprintf('%s=%s', $name, $value));
                }
            }
        }
    }

    /**
     * Validate required environment variables at startup and fail safely
     * without leaking secret values.
     *
     * @param array<string, mixed>|null $overrides Optional environment overrides for testing
     */
    public static function validateRequired(?array $overrides = null): void
    {
        $requiredKeys = [
            'APP_ENV',
            'APP_TIMEZONE',
            'DB_HOST',
            'DB_PORT',
            'DB_DATABASE',
            'DB_USERNAME',
        ];

        $missing = [];
        foreach ($requiredKeys as $key) {
            $value = self::readRaw($key, $overrides);
            if ($value === null || trim($value) === '') {
                $missing[] = $key;
            }
        }

        if ($missing !== []) {
            throw new ConfigurationException(
                sprintf(
                    'Missing required environment configuration key(s): %s.',
                    implode(', ', $missing)
                )
            );
        }

        $appEnv = strtolower((string) self::readRaw('APP_ENV', $overrides));
        if (!in_array($appEnv, self::ALLOWED_ENVIRONMENTS, true)) {
            throw new ConfigurationException(
                sprintf(
                    'Invalid APP_ENV value. Expected one of: %s.',
                    implode(', ', self::ALLOWED_ENVIRONMENTS)
                )
            );
        }

        $dbPortRaw = (string) self::readRaw('DB_PORT', $overrides);
        if (!ctype_digit($dbPortRaw) || (int) $dbPortRaw < 1 || (int) $dbPortRaw > 65535) {
            throw new ConfigurationException('Invalid DB_PORT value. Expected an integer between 1 and 65535.');
        }

        if ($appEnv === 'production') {
            $dbPassword = (string) (self::readRaw('DB_PASSWORD', $overrides) ?? '');
            if ($dbPassword === '' || $dbPassword === 'replace_me') {
                throw new ConfigurationException(
                    'Invalid production database configuration: DB_PASSWORD must be set to a non-placeholder secret.'
                );
            }
        }
    }

    public static function getString(string $key, string $default = ''): string
    {
        $raw = self::readRaw($key);
        if ($raw === null) {
            return $default;
        }

        return $raw;
    }

    public static function getInt(string $key, int $default = 0): int
    {
        $raw = self::readRaw($key);
        if ($raw === null || trim($raw) === '' || !is_numeric($raw)) {
            return $default;
        }

        return (int) $raw;
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $raw = self::readRaw($key);
        if ($raw === null || trim($raw) === '') {
            return $default;
        }

        return match (strtolower(trim($raw))) {
            '1', 'true', 'yes', 'on' => true,
            '0', 'false', 'no', 'off' => false,
            default => $default,
        };
    }

    /**
     * @param array<string, mixed>|null $overrides
     */
    private static function readRaw(string $key, ?array $overrides = null): ?string
    {
        if ($overrides !== null) {
            if (!array_key_exists($key, $overrides) || $overrides[$key] === null) {
                return null;
            }

            return (string) $overrides[$key];
        }

        if (array_key_exists($key, $_ENV)) {
            return (string) $_ENV[$key];
        }

        $envVal = getenv($key);
        if ($envVal !== false) {
            return (string) $envVal;
        }

        return null;
    }

    private static function stripQuotes(string $value): string
    {
        $length = strlen($value);
        if ($length >= 2) {
            $first = $value[0];
            $last = $value[$length - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                return substr($value, 1, -1);
            }
        }

        $commentPos = strpos($value, ' #');
        if ($commentPos !== false) {
            return rtrim(substr($value, 0, $commentPos));
        }

        return $value;
    }
}
