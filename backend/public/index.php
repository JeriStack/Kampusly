<?php

declare(strict_types=1);

use Kampusly\Bootstrap\ApplicationFactory;
use Kampusly\Shared\Infrastructure\Http\HttpRequest;
use Kampusly\Shared\Infrastructure\Http\JsonResponse;

$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoloadPath)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'code' => 'BOOTSTRAP_ERROR',
        'message' => 'Composer autoloader is missing. Run "composer dump-autoload" or "composer install" in backend/.',
    ], JSON_UNESCAPED_SLASHES);
    exit(1);
}

require_once $autoloadPath;

try {
    $app = ApplicationFactory::create(dirname(__DIR__));
    $request = HttpRequest::fromGlobals();
    $response = $app->handle($request);
    $response->send();
} catch (Throwable $fatalError) {
    $response = new JsonResponse([
        'code' => 'BOOTSTRAP_ERROR',
        'message' => 'Application failed to initialize. Verify server environment configuration.',
    ], 500);
    $response->send();
}

