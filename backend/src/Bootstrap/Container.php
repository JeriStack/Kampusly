<?php

declare(strict_types=1);

namespace Kampusly\Bootstrap;

use Closure;
use Kampusly\Shared\Domain\Exception\ConfigurationException;

final class Container
{
    /** @var array<string, Closure(self): mixed> */
    private array $bindings = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /**
     * @param Closure(self): mixed $factory
     */
    public function singleton(string $id, Closure $factory): void
    {
        $this->bindings[$id] = function (self $container) use ($id, $factory): mixed {
            if (!array_key_exists($id, $this->instances)) {
                $this->instances[$id] = $factory($container);
            }

            return $this->instances[$id];
        };
    }

    /**
     * @param Closure(self): mixed $factory
     */
    public function set(string $id, Closure $factory): void
    {
        $this->bindings[$id] = $factory;
    }

    public function instance(string $id, mixed $value): void
    {
        $this->instances[$id] = $value;
        $this->bindings[$id] = fn (): mixed => $this->instances[$id];
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->bindings) || array_key_exists($id, $this->instances);
    }

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (!array_key_exists($id, $this->bindings)) {
            throw new ConfigurationException(sprintf('Service "%s" is not registered in the container.', $id));
        }

        $factory = $this->bindings[$id];
        return $factory($this);
    }
}

