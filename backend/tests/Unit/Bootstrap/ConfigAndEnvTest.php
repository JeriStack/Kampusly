<?php

declare(strict_types=1);

namespace Kampusly\Tests\Unit\Bootstrap;

use Kampusly\Bootstrap\Config;
use Kampusly\Bootstrap\EnvLoader;
use Kampusly\Shared\Domain\Exception\ConfigurationException;
use Kampusly\Shared\Infrastructure\Logging\LoggerFactory;
use Kampusly\Tests\Support\TestCase;

final class ConfigAndEnvTest extends TestCase
{
    public function testConfigDotNotationAccessReturnsExpectedValues(): void
    {
        $config = new Config([
            'app' => [
                'name' => 'Kampusly',
                'debug' => false,
                'api_prefix' => '/api/v1',
            ],
            'database' => [
                'port' => 3306,
            ],
        ]);

        $this->assertSame('Kampusly', $config->getString('app.name'));
        $this->assertFalse($config->getBool('app.debug'));
        $this->assertSame('/api/v1', $config->getString('app.api_prefix'));
        $this->assertSame(3306, $config->getInt('database.port'));
        $this->assertSame('fallback', $config->getString('app.non_existent', 'fallback'));
    }

    public function testEnvLoaderFailsSafelyWhenRequiredVariablesAreMissing(): void
    {
        $exception = $this->assertThrows(
            ConfigurationException::class,
            static function (): void {
                EnvLoader::validateRequired([
                    'APP_ENV' => 'local',
                    'APP_TIMEZONE' => 'Asia/Manila',
                    'DB_HOST' => '',
                    'DB_PORT' => '3306',
                    'DB_DATABASE' => 'kampusly',
                    'DB_USERNAME' => '',
                ]);
            }
        );

        $this->assertTrue(str_contains($exception->getMessage(), 'DB_HOST'));
        $this->assertTrue(str_contains($exception->getMessage(), 'DB_USERNAME'));
    }

    public function testEnvLoaderRejectsPlaceholderPasswordInProduction(): void
    {
        $this->assertThrows(
            ConfigurationException::class,
            static function (): void {
                EnvLoader::validateRequired([
                    'APP_ENV' => 'production',
                    'APP_TIMEZONE' => 'Asia/Manila',
                    'DB_HOST' => 'mysql',
                    'DB_PORT' => '3306',
                    'DB_DATABASE' => 'kampusly',
                    'DB_USERNAME' => 'kampusly_app',
                    'DB_PASSWORD' => 'replace_me',
                ]);
            }
        );
    }

    public function testLoggerRedactsSensitiveKeys(): void
    {
        $logger = new LoggerFactory(dirname(__DIR__, 3) . '/storage/logs/test.log', 'debug', 'testing');
        $redacted = $logger->redactSecrets([
            'username' => 'admin@kampusly.local',
            'password' => 'super_secret_123',
            'nested' => [
                'db_password' => 'db_secret',
                'Authorization' => 'Bearer token_abc',
                'safe_field' => 'visible',
            ],
        ]);

        $this->assertSame('admin@kampusly.local', $redacted['username']);
        $this->assertSame('[REDACTED]', $redacted['password']);
        /** @var array<string, mixed> $nested */
        $nested = $redacted['nested'];
        $this->assertSame('[REDACTED]', $nested['db_password']);
        $this->assertSame('[REDACTED]', $nested['Authorization']);
        $this->assertSame('visible', $nested['safe_field']);
    }
}

