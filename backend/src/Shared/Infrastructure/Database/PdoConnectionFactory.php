<?php

declare(strict_types=1);

namespace Kampusly\Shared\Infrastructure\Database;

use Kampusly\Shared\Domain\Exception\ConfigurationException;
use PDO;
use PDOException;

final class PdoConnectionFactory
{
    /** @var array<string, mixed> */
    private array $config;
    private ?PDO $connection = null;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Connect to the MySQL server and ensure the configured database exists
     * before running migrations. Uses PHP's native mysqlnd driver, which
     * supports MySQL 8's caching_sha2_password authentication natively.
     */
    public function ensureDatabaseExists(): void
    {
        $driver = (string) ($this->config['driver'] ?? 'mysql');
        if ($driver !== 'mysql') {
            throw new ConfigurationException(sprintf('Unsupported database driver "%s".', $driver));
        }

        $host = (string) ($this->config['host'] ?? '127.0.0.1');
        $port = (int) ($this->config['port'] ?? 3306);
        $database = (string) ($this->config['database'] ?? 'kampusly');
        $charset = (string) ($this->config['charset'] ?? 'utf8mb4');
        $username = (string) ($this->config['username'] ?? '');
        $password = (string) ($this->config['password'] ?? '');

        if (preg_match('/^[a-zA-Z0-9_]+$/', $database) !== 1) {
            throw new ConfigurationException('Invalid database name configured in DB_DATABASE.');
        }

        $serverDsn = sprintf('mysql:host=%s;port=%d;charset=%s', $host, $port, $charset);
        $options = $this->resolveOptions();

        try {
            $serverPdo = new PDO($serverDsn, $username, $password, $options);
            $serverPdo->exec(
                sprintf(
                    'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
                    $database
                )
            );
        } catch (PDOException $exception) {
            throw new PDOException(
                sprintf(
                    'Database connection to %s:%d as user "%s" failed (%s). '
                    . 'Check DB_HOST, DB_PORT, DB_USERNAME, and DB_PASSWORD in backend/.env.',
                    $host,
                    $port,
                    $username,
                    $exception->getMessage()
                ),
                (int) $exception->getCode()
            );
        }
    }

    public function getConnection(): PDO
    {
        if ($this->connection !== null) {
            return $this->connection;
        }

        $driver = (string) ($this->config['driver'] ?? 'mysql');
        if ($driver !== 'mysql') {
            throw new ConfigurationException(sprintf('Unsupported database driver "%s".', $driver));
        }

        $host = (string) ($this->config['host'] ?? '127.0.0.1');
        $port = (int) ($this->config['port'] ?? 3306);
        $database = (string) ($this->config['database'] ?? 'kampusly');
        $charset = (string) ($this->config['charset'] ?? 'utf8mb4');
        $username = (string) ($this->config['username'] ?? '');
        $password = (string) ($this->config['password'] ?? '');
        $timezone = (string) ($this->config['timezone'] ?? '+08:00');

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $host,
            $port,
            $database,
            $charset
        );

        $options = $this->resolveOptions();

        try {
            $pdo = new PDO($dsn, $username, $password, $options);
            $pdo->exec(sprintf("SET time_zone = '%s'", addslashes($timezone)));
            $this->connection = $pdo;

            return $this->connection;
        } catch (PDOException $exception) {
            throw new PDOException(
                sprintf(
                    'Database connection to %s:%d/%s as user "%s" failed.',
                    $host,
                    $port,
                    $database,
                    $username
                ),
                (int) $exception->getCode()
            );
        }
    }

    /**
     * @return array<int, mixed>
     */
    private function resolveOptions(): array
    {
        /** @var array<int, mixed> $options */
        $options = is_array($this->config['options'] ?? null)
            ? $this->config['options']
            : [];

        $options[PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;
        $options[PDO::ATTR_DEFAULT_FETCH_MODE] = PDO::FETCH_ASSOC;
        $options[PDO::ATTR_EMULATE_PREPARES] = false;
        $options[PDO::ATTR_STRINGIFY_FETCHES] = false;

        return $options;
    }
}
