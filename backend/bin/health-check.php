<?php

declare(strict_types=1);

use Kampusly\Bootstrap\ApplicationFactory;
use Kampusly\Shared\Infrastructure\Health\HealthCheckService;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$liveOnly = in_array('--live-only', $argv ?? [], true);

try {
    $app = ApplicationFactory::create(dirname(__DIR__));
    /** @var HealthCheckService $healthService */
    $healthService = $app->getContainer()->get(HealthCheckService::class);

    if ($liveOnly) {
        $live = $healthService->checkLiveness();
        fwrite(STDOUT, json_encode($live, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
        exit(0);
    }

    $ready = $healthService->checkReadiness();
    fwrite(STDOUT, json_encode($ready['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit($ready['http_status'] === 200 ? 0 : 1);
} catch (Throwable $exception) {
    fwrite(STDERR, sprintf('[ERROR] Health check failed: %s%s', $exception->getMessage(), PHP_EOL));
    exit(1);
}

