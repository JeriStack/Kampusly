<?php

declare(strict_types=1);

use Kampusly\Bootstrap\Config;
use Kampusly\Bootstrap\Container;
use Kampusly\Bootstrap\Router;
use Kampusly\Shared\Infrastructure\Http\HttpRequest;
use Kampusly\Shared\Infrastructure\Http\JsonResponse;

return static function (Router $router, Container $container): void {
    $router->get('/', static function (HttpRequest $request) use ($container): JsonResponse {
        /** @var Config $config */
        $config = $container->get(Config::class);

        return new JsonResponse(
            [
                'service' => 'kampusly-api',
                'name' => $config->getString('app.name', 'Kampusly'),
                'tagline' => 'Your Campus. Your Classes. Your Time.',
                'version' => $config->getString('app.version', '0.1.0'),
                'api_prefix' => $config->getString('app.api_prefix', '/api/v1'),
                'status' => 'ok',
                'links' => [
                    'live' => '/api/v1/health/live',
                    'ready' => '/api/v1/health/ready',
                ],
            ],
            200,
            [],
            $request->getRequestId()
        );
    });

    $healthRoutes = require __DIR__ . '/health.php';
    if (is_callable($healthRoutes)) {
        $healthRoutes($router, $container);
    }
};

