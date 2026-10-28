<?php

declare(strict_types=1);

use Kampusly\Bootstrap\ApplicationFactory;
use Kampusly\Bootstrap\Config;

require_once dirname(__DIR__) . '/vendor/autoload.php';

try {
    $app = ApplicationFactory::create(dirname(__DIR__));
    /** @var Config $config */
    $config = $app->getContainer()->get(Config::class);

    if ($config->getString('app.env') === 'production') {
        fwrite(STDERR, "[ABORTED] Development seeders cannot be executed in the production environment.\n");
        exit(1);
    }

    $seedersDir = dirname(__DIR__) . '/database/seeders';
    $files = glob($seedersDir . '/*.php');
    if ($files === false || $files === []) {
        fwrite(STDOUT, "Seeder runner ready (no Phase 4+ seeders registered yet in database/seeders/).\n");
        exit(0);
    }

    sort($files);
    foreach ($files as $file) {
        $seederName = pathinfo($file, PATHINFO_FILENAME);
        $seeder = require $file;
        if (is_callable($seeder)) {
            $seeder($app->getContainer());
            fwrite(STDOUT, sprintf("[SEEDED] %s\n", $seederName));
        }
    }

    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, sprintf('[ERROR] Seeding failed: %s%s', $exception->getMessage(), PHP_EOL));
    exit(1);
}

