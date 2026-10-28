<?php

declare(strict_types=1);

use Kampusly\Bootstrap\Container;
use Kampusly\Bootstrap\Router;
use Kampusly\Shared\Infrastructure\Health\HealthCheckService;
use Kampusly\Shared\Infrastructure\Http\HttpRequest;
use Kampusly\Shared\Infrastructure\Http\JsonResponse;

return static function (Router $router, Container $container): void {
    $router->get('/health/live', static function (HttpRequest $request) use ($container): JsonResponse {
        /** @var HealthCheckService $healthService */
        $healthService = $container->get(HealthCheckService::class);
        $payload = $healthService->checkLiveness();

        return new JsonResponse($payload, 200, [], $request->getRequestId());
    });

    $router->get('/health/ready', static function (HttpRequest $request) use ($container): JsonResponse {
        /** @var HealthCheckService $healthService */
        $healthService = $container->get(HealthCheckService::class);
        $result = $healthService->checkReadiness();

        return new JsonResponse(
            $result['payload'],
            $result['http_status'],
            [],
            $request->getRequestId()
        );
    });
};

