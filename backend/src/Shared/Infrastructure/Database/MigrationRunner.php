<?php

declare(strict_types=1);

namespace Kampusly\Shared\Infrastructure\Database;

use PDO;
use RuntimeException;

final class MigrationRunner
{
    private PDO $pdo;
    private string $migrationsDir;
    private string $rollbacksDir;

    public function __construct(PDO $pdo, string $migrationsDir, ?string $rollbacksDir = null)
    {
        $this->pdo = $pdo;
        $this->migrationsDir = rtrim($migrationsDir, '/\\');
        $this->rollbacksDir = $rollbacksDir !== null
            ? rtrim($rollbacksDir, '/\\')
            : $this->migrationsDir . '/rollbacks';
    }

    public function ensureTrackingTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS `schema_migrations` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `version` VARCHAR(128) NOT NULL,
                `description` VARCHAR(255) NOT NULL,
                `checksum` CHAR(64) NOT NULL,
                `executed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `execution_time_ms` INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_schema_migrations_version` (`version`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /**
     * @return list<array{version: string, state: string, checksum: string, executed_at: string|null}>
     */
    public function status(): array
    {
        $this->ensureTrackingTable();
        $appliedMap = $this->fetchAppliedMigrations();
        $files = $this->discoverMigrationFiles();

        $report = [];
        foreach ($files as $file) {
            $version = pathinfo($file, PATHINFO_FILENAME);
            $sql = $this->readFileOrFail($file, $version);
            $checksum = self::computeChecksum($sql);
            $applied = $appliedMap[$version] ?? null;

            $state = 'PENDING';
            $executedAt = null;
            if ($applied !== null) {
                $state = $applied['checksum'] === $checksum ? 'APPLIED' : 'CHECKSUM_DRIFT';
                $executedAt = $applied['executed_at'];
            }

            $report[] = [
                'version' => $version,
                'state' => $state,
                'checksum' => $checksum,
                'executed_at' => $executedAt,
            ];
        }

        return $report;
    }

    /**
     * Apply all pending forward migrations in ascending version order.
     *
     * @return list<array{version: string, execution_time_ms: int}>
     */
    public function migrate(): array
    {
        $this->ensureTrackingTable();
        $appliedMap = $this->fetchAppliedMigrations();
        $files = $this->discoverMigrationFiles();
        $appliedNow = [];

        foreach ($files as $file) {
            $version = pathinfo($file, PATHINFO_FILENAME);
            $sql = $this->readFileOrFail($file, $version);
            $checksum = self::computeChecksum($sql);

            if (isset($appliedMap[$version])) {
                if ($appliedMap[$version]['checksum'] !== $checksum) {
                    throw new RuntimeException(
                        sprintf(
                            'Checksum drift detected on applied migration "%s". Never modify historical migrations.',
                            $version
                        )
                    );
                }
                continue;
            }

            $statements = self::splitSqlStatements($sql);
            $startTime = microtime(true);

            foreach ($statements as $statement) {
                $this->pdo->exec($statement);
            }

            $elapsedMs = (int) round((microtime(true) - $startTime) * 1000);

            $insertStmt = $this->pdo->prepare(
                'INSERT INTO `schema_migrations` (`version`, `description`, `checksum`, `execution_time_ms`)
                 VALUES (:version, :description, :checksum, :execution_time_ms)'
            );
            $insertStmt->execute([
                ':version' => $version,
                ':description' => str_replace('_', ' ', $version),
                ':checksum' => $checksum,
                ':execution_time_ms' => $elapsedMs,
            ]);

            $appliedNow[] = [
                'version' => $version,
                'execution_time_ms' => $elapsedMs,
            ];
        }

        return $appliedNow;
    }

    /**
     * Roll back the most recently applied non-bootstrap migration(s).
     *
     * @return list<string> Rolled back migration versions
     */
    public function rollback(int $steps = 1): array
    {
        if ($steps < 1) {
            return [];
        }

        $this->ensureTrackingTable();
        $appliedMap = $this->fetchAppliedMigrations();
        $versions = array_keys($appliedMap);
        rsort($versions);

        $rolledBack = [];
        foreach ($versions as $version) {
            if (count($rolledBack) >= $steps) {
                break;
            }

            if (str_starts_with($version, '0001_01_01_000000')) {
                continue;
            }

            $downFile = $this->rollbacksDir . '/' . $version . '.down.sql';
            if (!is_file($downFile)) {
                throw new RuntimeException(
                    sprintf('Rollback script not found for migration "%s".', $version)
                );
            }

            $downSql = $this->readFileOrFail($downFile, $version . '.down');
            $statements = self::splitSqlStatements($downSql);
            foreach ($statements as $statement) {
                $this->pdo->exec($statement);
            }

            $deleteStmt = $this->pdo->prepare(
                'DELETE FROM `schema_migrations` WHERE `version` = :version'
            );
            $deleteStmt->execute([':version' => $version]);

            $rolledBack[] = $version;
        }

        return $rolledBack;
    }

    /**
     * Split a SQL file into individual executable statements while stripping comment lines.
     *
     * @return list<string>
     */
    public static function splitSqlStatements(string $sql): array
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $sql);
        $lines = explode("\n", $normalized);
        $cleanLines = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '--')) {
                continue;
            }
            $cleanLines[] = $line;
        }

        $joined = implode("\n", $cleanLines);
        $rawChunks = preg_split('/;\s*(?:\n|$)/', $joined);
        if (!is_array($rawChunks)) {
            return [];
        }

        $statements = [];
        foreach ($rawChunks as $chunk) {
            $statement = trim($chunk);
            if ($statement !== '') {
                $statements[] = $statement;
            }
        }

        return $statements;
    }

    public static function computeChecksum(string $sql): string
    {
        return hash('sha256', str_replace(["\r\n", "\r"], "\n", $sql));
    }

    /**
     * @return list<string>
     */
    public function discoverMigrationFiles(): array
    {
        $files = glob($this->migrationsDir . '/*.sql');
        if ($files === false) {
            return [];
        }

        sort($files);
        return array_values($files);
    }

    /**
     * @return array<string, array{checksum: string, executed_at: string}>
     */
    private function fetchAppliedMigrations(): array
    {
        $stmt = $this->pdo->query(
            'SELECT `version`, `checksum`, `executed_at` FROM `schema_migrations` ORDER BY `version` ASC'
        );
        $rows = $stmt !== false ? $stmt->fetchAll() : [];

        $map = [];
        foreach ($rows as $row) {
            if (is_array($row) && isset($row['version'])) {
                $version = (string) $row['version'];
                $map[$version] = [
                    'checksum' => (string) ($row['checksum'] ?? ''),
                    'executed_at' => (string) ($row['executed_at'] ?? ''),
                ];
            }
        }

        return $map;
    }

    private function readFileOrFail(string $filePath, string $label): string
    {
        $contents = @file_get_contents($filePath);
        if ($contents === false) {
            throw new RuntimeException(sprintf('Unable to read migration file: %s', $label));
        }

        return $contents;
    }
}

