<?php

declare(strict_types=1);

use Kampusly\Academic\Presentation\Http\Controller\AcademicCatalogController;
use Kampusly\Bootstrap\Config;
use Kampusly\Bootstrap\Container;
use Kampusly\Bootstrap\Router;
use Kampusly\Scheduling\Presentation\Http\Controller\ScheduleFoundationController;
use Kampusly\Shared\Infrastructure\Http\HttpRequest;
use Kampusly\Shared\Infrastructure\Http\JsonResponse;
use Kampusly\Shared\Presentation\Http\Controller\OpenApiDocumentationController;

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
                'phase' => 'Phase 4: Database and Backend Foundation',
                'links' => [
                    'health' => '/api/v1/health',
                    'live' => '/api/v1/health/live',
                    'ready' => '/api/v1/health/ready',
                    'docs' => '/api/v1/docs',
                    'openapi' => '/api/v1/openapi.yaml',
                    'subjects' => '/api/v1/subjects',
                    'rooms' => '/api/v1/rooms',
                    'sections' => '/api/v1/sections',
                    'schedules' => '/api/v1/schedules',
                ],
            ],
            200,
            [],
            $request->getRequestId()
        );
    });

    $router->get('/openapi.yaml', static function (HttpRequest $request) use ($container): JsonResponse {
        /** @var OpenApiDocumentationController $docsController */
        $docsController = $container->get(OpenApiDocumentationController::class);
        return $docsController->serveSpec($request);
    });

    $router->get('/docs', static function (HttpRequest $request) use ($container): JsonResponse {
        /** @var OpenApiDocumentationController $docsController */
        $docsController = $container->get(OpenApiDocumentationController::class);
        return $docsController->serveUi($request);
    });

    $healthRoutes = require __DIR__ . '/health.php';
    if (is_callable($healthRoutes)) {
        $healthRoutes($router, $container);
    }

    $router->get('/subjects', static function (HttpRequest $request) use ($container): JsonResponse {
        /** @var AcademicCatalogController $academicController */
        $academicController = $container->get(AcademicCatalogController::class);
        return $academicController->listSubjects($request);
    });

    $router->get('/rooms', static function (HttpRequest $request) use ($container): JsonResponse {
        /** @var AcademicCatalogController $academicController */
        $academicController = $container->get(AcademicCatalogController::class);
        return $academicController->listRooms($request);
    });

    $router->get('/sections', static function (HttpRequest $request) use ($container): JsonResponse {
        /** @var AcademicCatalogController $academicController */
        $academicController = $container->get(AcademicCatalogController::class);
        return $academicController->listSections($request);
    });

    $router->get('/schedules', static function (HttpRequest $request) use ($container): JsonResponse {
        /** @var ScheduleFoundationController $scheduleController */
        $scheduleController = $container->get(ScheduleFoundationController::class);
        return $scheduleController->listSchedules($request);
    });

    $router->post('/schedules/validate-conflicts', static function (HttpRequest $request) use ($container): JsonResponse {
        /** @var ScheduleFoundationController $scheduleController */
        $scheduleController = $container->get(ScheduleFoundationController::class);
        return $scheduleController->validateConflicts($request);
    });
};
