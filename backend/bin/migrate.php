<?php

declare(strict_types=1);

use Kampusly\Bootstrap\ApplicationFactory;
use Kampusly\Bootstrap\Config;
use Kampusly\Shared\Infrastructure\Database\MigrationRunner;
use Kampusly\Shared\Infrastructure\Database\PdoConnectionFactory;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$args = $argv ?? [];
$statusOnly = in_array('--status', $args, true);
$rollback = in_array('--rollback', $args, true);
$force = in_array('--force', $args, true);
$migrationsDir = dirname(__DIR__) . '/database/migrations';

try {
    $app = ApplicationFactory::create(dirname(__DIR__));
    /** @var Config $config */
    $config = $app->getContainer()->get(Config::class);
    /** @var PdoConnectionFactory $pdoFactory */
    $pdoFactory = $app->getContainer()->get(PdoConnectionFactory::class);

    $pdoFactory->ensureDatabaseExists();
    $pdo = $pdoFactory->getConnection();
    $runner = new MigrationRunner($pdo, $migrationsDir);

    if ($statusOnly) {
        fwrite(STDOUT, "Kampusly Database Migration Status:\n");
        foreach ($runner->status() as $entry) {
            fwrite(STDOUT, sprintf("  [%s] %s\n", $entry['state'], $entry['version']));
        }
        exit(0);
    }

    if ($rollback) {
        if ($config->getString('app.env') === 'production' && !$force) {
            fwrite(STDERR, "[ABORTED] Migration rollback in production requires --force.\n");
            exit(1);
        }

        $rolledBack = $runner->rollback(1);
        if ($rolledBack === []) {
            fwrite(STDOUT, "No applied feature migrations available to roll back.\n");
            exit(0);
        }

        foreach ($rolledBack as $version) {
            fwrite(STDOUT, sprintf("[ROLLED BACK] %s\n", $version));
        }
        exit(0);
    }

    $applied = $runner->migrate();
    foreach ($applied as $item) {
        fwrite(STDOUT, sprintf("[APPLIED] %s (%d ms)\n", $item['version'], $item['execution_time_ms']));
    }

    if ($applied === []) {
        fwrite(STDOUT, "Database 'kampusly' is ready and all migrations are up to date.\n");
    }
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, sprintf('[ERROR] Migration failed: %s%s', $exception->getMessage(), PHP_EOL));
    exit(1);
}
