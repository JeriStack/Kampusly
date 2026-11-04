<?php

declare(strict_types=1);

use Kampusly\Bootstrap\ApplicationFactory;
use Kampusly\Shared\Infrastructure\Http\HttpRequest;
use Kampusly\Shared\Infrastructure\Http\JsonResponse;

// Built-in PHP CLI server router support (serves static files if they exist on disk)
if (php_sapi_name() === 'cli-server') {
    $uri = urldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
    if ($uri !== '/' && is_file(__DIR__ . $uri)) {
        return false;
    }
}

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

