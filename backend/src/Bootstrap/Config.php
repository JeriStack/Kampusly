<?php

declare(strict_types=1);

namespace Kampusly\Bootstrap;

use Kampusly\Shared\Domain\Exception\ConfigurationException;

final class Config
{
    /** @var array<string, mixed> */
    private array $items;

    /**
     * @param array<string, mixed> $items
     */
    public function __construct(array $items)
    {
        $this->items = $items;
    }

    public static function fromDirectory(string $configDir): self
    {
        if (!is_dir($configDir)) {
            throw new ConfigurationException(sprintf('Configuration directory not found: %s', $configDir));
        }

        $files = glob(rtrim($configDir, '/\\') . DIRECTORY_SEPARATOR . '*.php');
        if ($files === false) {
            throw new ConfigurationException('Failed to scan configuration directory.');
        }

        sort($files);
        $items = [];

        foreach ($files as $file) {
            $section = pathinfo($file, PATHINFO_FILENAME);
            $loaded = require $file;
            if (!is_array($loaded)) {
                throw new ConfigurationException(
                    sprintf('Configuration file "%s.php" must return an array.', $section)
                );
            }
            $items[$section] = $loaded;
        }

        return new self($items);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $current = $this->items;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }
            $current = $current[$segment];
        }

        return $current;
    }

    public function getString(string $key, string $default = ''): string
    {
        $value = $this->get($key, $default);
        return is_scalar($value) ? (string) $value : $default;
    }

    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->get($key, $default);
        return is_numeric($value) ? (int) $value : $default;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);
        return is_bool($value) ? $value : $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function getArray(string $key, array $default = []): array
    {
        $value = $this->get($key, $default);
        return is_array($value) ? $value : $default;
    }
}

