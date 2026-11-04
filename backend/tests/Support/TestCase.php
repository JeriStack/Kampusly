<?php

declare(strict_types=1);

namespace Kampusly\Tests\Support;

use Closure;
use Countable;
use RuntimeException;
use Throwable;

abstract class TestCase
{
    private int $assertionCount = 0;

    protected function setUp(): void
    {
    }

    protected function tearDown(): void
    {
    }

    public function runTestMethod(string $methodName): void
    {
        $this->setUp();
        try {
            $this->{$methodName}();
        } finally {
            $this->tearDown();
        }
    }

    public function getAssertionCount(): int
    {
        return $this->assertionCount;
    }

    protected function assertTrue(bool $condition, string $message = 'Expected condition to be true.'): void
    {
        $this->assertionCount++;
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }

    protected function assertFalse(bool $condition, string $message = 'Expected condition to be false.'): void
    {
        $this->assertionCount++;
        if ($condition) {
            throw new RuntimeException($message);
        }
    }

    protected function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        $this->assertionCount++;
        if ($expected !== $actual) {
            $detail = $message !== ''
                ? $message
                : sprintf(
                    'Failed asserting that %s is identical to %s.',
                    var_export($actual, true),
                    var_export($expected, true)
                );
            throw new RuntimeException($detail);
        }
    }

    protected function assertNull(mixed $actual, string $message = 'Expected value to be null.'): void
    {
        $this->assertionCount++;
        if ($actual !== null) {
            throw new RuntimeException($message);
        }
    }

    protected function assertNotNull(mixed $actual, string $message = 'Expected value not to be null.'): void
    {
        $this->assertionCount++;
        if ($actual === null) {
            throw new RuntimeException($message);
        }
    }

    /**
     * @param Countable|array<mixed, mixed> $countable
     */
    protected function assertCount(int $expectedCount, Countable|array $countable, string $message = ''): void
    {
        $this->assertionCount++;
        $actualCount = count($countable);
        if ($expectedCount !== $actualCount) {
            $detail = $message !== ''
                ? $message
                : sprintf('Failed asserting that actual size %d matches expected %d.', $actualCount, $expectedCount);
            throw new RuntimeException($detail);
        }
    }

    protected function assertStringContainsString(string $needle, string $haystack, string $message = ''): void
    {
        $this->assertionCount++;
        if (!str_contains($haystack, $needle)) {
            $detail = $message !== ''
                ? $message
                : sprintf('Failed asserting that "%s" contains "%s".', $haystack, $needle);
            throw new RuntimeException($detail);
        }
    }

    /**
     * @param array<mixed, mixed> $array
     */
    protected function assertArrayHasKey(string|int $key, array $array, string $message = ''): void
    {
        $this->assertionCount++;
        if (!array_key_exists($key, $array)) {
            $detail = $message !== ''
                ? $message
                : sprintf('Failed asserting that array has key "%s".', (string) $key);
            throw new RuntimeException($detail);
        }
    }

    /**
     * @param class-string<Throwable> $expectedExceptionClass
     */
    protected function assertThrows(string $expectedExceptionClass, Closure $callback): Throwable
    {
        $this->assertionCount++;
        try {
            $callback();
        } catch (Throwable $exception) {
            if ($exception instanceof $expectedExceptionClass) {
                return $exception;
            }

            throw new RuntimeException(
                sprintf(
                    'Expected exception %s, but %s was thrown: %s',
                    $expectedExceptionClass,
                    $exception::class,
                    $exception->getMessage()
                )
            );
        }

        throw new RuntimeException(
            sprintf('Expected exception %s was not thrown.', $expectedExceptionClass)
        );
    }
}
