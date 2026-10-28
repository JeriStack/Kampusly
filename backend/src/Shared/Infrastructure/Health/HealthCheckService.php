<?php

declare(strict_types=1);

namespace Kampusly\Shared\Infrastructure\Health;

use Kampusly\Bootstrap\Config;
use Kampusly\Shared\Infrastructure\Database\PdoConnectionFactory;
use Kampusly\Shared\Infrastructure\Logging\LoggerFactory;
use Throwable;

final class HealthCheckService
{
    private Config $config;
    private PdoConnectionFactory $pdoFactory;
    private LoggerFactory $logger;

    public function __construct(
        Config $config,
        PdoConnectionFactory $pdoFactory,
        LoggerFactory $logger
    ) {
        $this->config = $config;
        $this->pdoFactory = $pdoFactory;
        $this->logger = $logger;
    }

    /**
     * Liveness probe: verifies that the PHP runtime and configuration are loaded.
     *
     * @return array{
     *   status: string,
     *   service: string,
     *   version: string,
     *   environment: string,
     *   timezone: string,
     *   timestamp: string
     * }
     */
    public function checkLiveness(): array
    {
        return [
            'status' => 'ok',
            'service' => 'kampusly-api',
            'version' => $this->config->getString('app.version', '0.1.0'),
            'environment' => $this->config->getString('app.env', 'local'),
            'timezone' => $this->config->getString('app.timezone', 'Asia/Manila'),
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        ];
    }

    /**
     * Readiness probe: verifies database connectivity via a prepared PDO query.
     *
     * @return array{
     *   http_status: int,
     *   payload: array<string, mixed>
     * }
     */
    public function checkReadiness(): array
    {
        $startTime = microtime(true);
        $dbStatus = 'up';
        $httpStatus = 200;
        $overallStatus = 'ready';

        try {
            $pdo = $this->pdoFactory->getConnection();
            $statement = $pdo->prepare('SELECT 1 AS ok');
            $statement->execute();
            $row = $statement->fetch();

            if (!is_array($row) || (int) ($row['ok'] ?? 0) !== 1) {
                $dbStatus = 'down';
                $httpStatus = 503;
                $overallStatus = 'degraded';
            }
        } catch (Throwable $exception) {
            $dbStatus = 'down';
            $httpStatus = 503;
            $overallStatus = 'degraded';

            $this->logger->warning('Database readiness check failed', [
                'reason' => $exception->getMessage(),
            ]);
        }

        $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

        return [
            'http_status' => $httpStatus,
            'payload' => [
                'status' => $overallStatus,
                'service' => 'kampusly-api',
                'version' => $this->config->getString('app.version', '0.1.0'),
                'checks' => [
                    'database' => [
                        'status' => $dbStatus,
                        'driver' => $this->config->getString('database.driver', 'mysql'),
                        'latency_ms' => $latencyMs,
                    ],
                ],
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            ],
        ];
    }
}

