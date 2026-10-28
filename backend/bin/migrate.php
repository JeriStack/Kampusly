<?php

declare(strict_types=1);

use Kampusly\Bootstrap\ApplicationFactory;
use Kampusly\Shared\Infrastructure\Database\PdoConnectionFactory;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$statusOnly = in_array('--status', $argv ?? [], true);
$migrationsDir = dirname(__DIR__) . '/database/migrations';

try {
    $app = ApplicationFactory::create(dirname(__DIR__));
    /** @var PdoConnectionFactory $pdoFactory */
    $pdoFactory = $app->getContainer()->get(PdoConnectionFactory::class);

    $pdoFactory->ensureDatabaseExists();
    $pdo = $pdoFactory->getConnection();

    $pdo->exec(
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

    $appliedStmt = $pdo->query(
        'SELECT `version`, `checksum`, `executed_at` FROM `schema_migrations` ORDER BY `version` ASC'
    );
    $appliedRows = $appliedStmt !== false ? $appliedStmt->fetchAll() : [];

    $appliedMap = [];
    foreach ($appliedRows as $row) {
        if (is_array($row) && isset($row['version'])) {
            $appliedMap[(string) $row['version']] = $row;
        }
    }

    $files = glob($migrationsDir . '/*.sql');
    if ($files === false) {
        $files = [];
    }
    sort($files);

    if ($statusOnly) {
        fwrite(STDOUT, "Kampusly Database Migration Status:\n");
        foreach ($files as $file) {
            $version = pathinfo($file, PATHINFO_FILENAME);
            $state = isset($appliedMap[$version]) ? 'APPLIED' : 'PENDING';
            fwrite(STDOUT, sprintf("  [%s] %s\n", $state, $version));
        }
        exit(0);
    }

    $appliedCount = 0;
    foreach ($files as $file) {
        $version = pathinfo($file, PATHINFO_FILENAME);
        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new RuntimeException(sprintf('Unable to read migration file: %s', $version));
        }

        $checksum = hash('sha256', str_replace("\r\n", "\n", $sql));
        if (isset($appliedMap[$version])) {
            continue;
        }

        $startTime = microtime(true);
        $pdo->exec($sql);
        $elapsedMs = (int) round((microtime(true) - $startTime) * 1000);

        $insertStmt = $pdo->prepare(
            'INSERT INTO `schema_migrations` (`version`, `description`, `checksum`, `execution_time_ms`)
             VALUES (:version, :description, :checksum, :execution_time_ms)'
        );
        $insertStmt->execute([
            ':version' => $version,
            ':description' => str_replace('_', ' ', $version),
            ':checksum' => $checksum,
            ':execution_time_ms' => $elapsedMs,
        ]);

        fwrite(STDOUT, sprintf("[APPLIED] %s (%d ms)\n", $version, $elapsedMs));
        $appliedCount++;
    }

    if ($appliedCount === 0) {
        fwrite(STDOUT, "Database 'kampusly' is ready and all migrations are up to date.\n");
    }
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, sprintf('[ERROR] Migration failed: %s%s', $exception->getMessage(), PHP_EOL));
    exit(1);
}
