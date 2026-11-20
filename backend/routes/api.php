<?php

declare(strict_types=1);

use Kampusly\Academic\Presentation\Http\Controller\AcademicCatalogController;
use Kampusly\Academic\Presentation\Http\Controller\InstructorController;
use Kampusly\Academic\Presentation\Http\Controller\StudentController;
use Kampusly\Announcements\Presentation\Http\Controller\InstructorAnnouncementController;
use Kampusly\Announcements\Presentation\Http\Controller\StudentAnnouncementController;
use Kampusly\Bootstrap\Config;
use Kampusly\Bootstrap\Container;
use Kampusly\Bootstrap\Router;
use Kampusly\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use Kampusly\IdentityAccess\Presentation\Http\Controller\AdminDashboardController;
use Kampusly\IdentityAccess\Presentation\Http\Controller\AuthController;
use Kampusly\IdentityAccess\Presentation\Http\Controller\UserController;
use Kampusly\Scheduling\Presentation\Http\Controller\ScheduleFoundationController;
use Kampusly\Scheduling\Presentation\Http\Controller\StudentScheduleController;
use Kampusly\Scheduling\Presentation\Http\Controller\InstructorScheduleController;
use Kampusly\Scheduling\Presentation\Http\Controller\ScheduleChangeRequestController;
use Kampusly\Notifications\Presentation\Http\Controller\NotificationController;
use Kampusly\Shared\Infrastructure\Http\AuthenticateMiddleware;
use Kampusly\Shared\Infrastructure\Http\AuthorizeRoleMiddleware;
use Kampusly\Shared\Infrastructure\Http\HttpRequest;
use Kampusly\Shared\Infrastructure\Http\JsonResponse;
use Kampusly\Shared\Infrastructure\Http\SessionInterface;
use Kampusly\Shared\Presentation\Http\Controller\OpenApiDocumentationController;

return static function (Router $router, Container $container): void {
    $authenticate = static function (HttpRequest $request, Closure $next) use ($container): JsonResponse {
        /** @var AuthenticateMiddleware $authMiddleware */
        $authMiddleware = new AuthenticateMiddleware(
            $container->get(SessionInterface::class),
            $container->get(UserRepositoryInterface::class)
        );
        return $authMiddleware->handle($request, $next);
    };

    $authorizeRoles = static function (array $roles): Closure {
        return static function (HttpRequest $request, Closure $next) use ($roles): JsonResponse {
            $middleware = new AuthorizeRoleMiddleware($roles);
            return $middleware->handle($request, $next);
        };
    };

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
                'phase' => 'Phase 5: Authentication and Authorization',
                'links' => [
                    'health' => '/api/v1/health',
                    'live' => '/api/v1/health/live',
                    'ready' => '/api/v1/health/ready',
                    'docs' => '/api/v1/docs',
                    'openapi' => '/api/v1/openapi.yaml',
                    'auth_login' => '/api/v1/auth/login',
                    'auth_logout' => '/api/v1/auth/logout',
                    'auth_me' => '/api/v1/auth/me',
                    'users' => '/api/v1/users',
                    'admin_dashboard_summary' => '/api/v1/admin/dashboard-summary',
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

    // Authentication endpoints
    $router->post('/auth/login', static function (HttpRequest $request) use ($container): JsonResponse {
        /** @var AuthController $authController */
        $authController = $container->get(AuthController::class);
        return $authController->login($request);
    });

    $router->post('/auth/logout', static function (HttpRequest $request) use ($container): JsonResponse {
        /** @var AuthController $authController */
        $authController = $container->get(AuthController::class);
        return $authController->logout($request);
    }, [$authenticate]);

    $router->get('/auth/me', static function (HttpRequest $request) use ($container): JsonResponse {
        /** @var AuthController $authController */
        $authController = $container->get(AuthController::class);
        return $authController->me($request);
    }, [$authenticate]);

    $router->post('/auth/change-password', static function (HttpRequest $request) use ($container): JsonResponse {
        /** @var AuthController $authController */
        $authController = $container->get(AuthController::class);
        return $authController->changePassword($request);
    }, [$authenticate]);

    // User management endpoints
    $router->get('/users', static function (HttpRequest $request) use ($container): JsonResponse {
        /** @var UserController $userController */
        $userController = $container->get(UserController::class);
        return $userController->listUsers($request);
    }, [$authenticate, $authorizeRoles(['admin'])]);

    $router->post('/users', static function (HttpRequest $request) use ($container): JsonResponse {
        /** @var UserController $userController */
        $userController = $container->get(UserController::class);
        return $userController->createUser($request);
    }, [$authenticate, $authorizeRoles(['admin'])]);

    $router->get('/users/{id}', static function (HttpRequest $request, array $params) use ($container): JsonResponse {
        /** @var UserController $userController */
        $userController = $container->get(UserController::class);
        return $userController->getUser($request, $params);
    }, [$authenticate]);

    $router->patch('/users/{id}/status', static function (HttpRequest $request, array $params) use ($container): JsonResponse {
        /** @var UserController $userController */
        $userController = $container->get(UserController::class);
        return $userController->updateStatus($request, $params);
    }, [$authenticate, $authorizeRoles(['admin'])]);

    $router->post('/users/{id}/reset-password', static function (HttpRequest $request, array $params) use ($container): JsonResponse {
        /** @var UserController $userController */
        $userController = $container->get(UserController::class);
        return $userController->resetPassword($request, $params);
    }, [$authenticate, $authorizeRoles(['admin'])]);

    // Admin dashboard summary
    $router->get('/admin/dashboard-summary', static function (HttpRequest $request) use ($container): JsonResponse {
        /** @var AdminDashboardController $adminController */
        $adminController = $container->get(AdminDashboardController::class);
        return $adminController->dashboardSummary($request);
    }, [$authenticate, $authorizeRoles(['admin'])]);

    // =========================================================================
    // Phase 6: Academic Management Endpoints
    // =========================================================================

    // --- Students ---
    $router->get('/students', static function (HttpRequest $request) use ($container): JsonResponse {
        /** @var StudentController $controller */
        $controller = $container->get(StudentController::class);
        return $controller->listStudents($request);
    }, [$authenticate]);

    $router->post('/students', static function (HttpRequest $request) use ($container): JsonResponse {
        /** @var StudentController $controller */
        $controller = $container->get(StudentController::class);
        return $controller->createStudent($request);
    }, [$authenticate, $authorizeRoles(['admin'])]);

    $router->get('/students/{id}', static function (HttpRequest $request, array $params) use ($container): JsonResponse {
        /** @var StudentController $controller */
        $controller = $container->get(StudentController::class);
        return $controller->getStudent($request, $params);
    }, [$authenticate]);

    $router->put('/students/{id}', static function (HttpRequest $request, array $params) use ($container): JsonResponse {
        /** @var StudentController $controller */
        $controller = $container->get(StudentController::class);
        return $controller->updateStudent($request, $params);
    }, [$authenticate, $authorizeRoles(['admin'])]);

    $router->patch('/students/{id}/status', static function (HttpRequest $request, array $params) use ($container): JsonResponse {
        /** @var StudentController $controller */
        $controller = $container->get(StudentController::class);
        return $controller->updateStatus($request, $params);
    }, [$authenticate, $authorizeRoles(['admin'])]);

    // --- Instructors ---
    $router->get('/instructors', static function (HttpRequest $request) use ($container): JsonResponse {
        /** @var InstructorController $controller */
        $controller = $container->get(InstructorController::class);
        return $controller->listInstructors($request);
    }, [$authenticate]);

    $router->post('/instructors', static function (HttpRequest $request) use ($container): JsonResponse {
        /** @var InstructorController $controller */
        $controller = $container->get(InstructorController::class);
        return $controller->createInstructor($request);
    }, [$authenticate, $authorizeRoles(['admin'])]);

    $router->get('/instructors/{id}', static function (HttpRequest $request, array $params) use ($container): JsonResponse {
        /** @var InstructorController $controller */
        $controller = $container->get(InstructorController::class);
        return $controller->getInstructor($request, $params);
    }, [$authenticate]);

    $router->put('/instructors/{id}', static function (HttpRequest $request, array $params) use ($container): JsonResponse {
        /** @var InstructorController $controller */
        $controller = $container->get(InstructorController::class);
        return $controller->updateInstructor($request, $params);
    }, [$authenticate, $authorizeRoles(['admin'])]);

    $router->patch('/instructors/{id}/status', static function (HttpRequest $request, array $params) use ($container): JsonResponse {
        /** @var InstructorController $controller */
        $controller = $container->get(InstructorController::class);
        return $controller->updateStatus($request, $params);
    }, [$authenticate, $authorizeRoles(['admin'])]);

    // --- Subjects ---
    $router->get('/subjects', static function (HttpRequest $request) use ($container): JsonResponse {
        /** @var AcademicCatalogController $academicController */
        $academicController = $container->get(AcademicCatalogController::class);
        return $academicController->listSubjects($request);
    });

    $router->post('/subjects', static function (HttpRequest $request) use ($container): JsonResponse {
        /** @var AcademicCatalogController $academicController */
        $academicController = $container->get(AcademicCatalogController::class);
        return $academicController->createSubject($request);
    }, [$authenticate, $authorizeRoles(['admin'])]);

    $router->get('/subjects/{id}', static function (HttpRequest $request, array $params) use ($container): JsonResponse {
        /** @var AcademicCatalogController $academicController */
        $academicController = $container->get(AcademicCatalogController::class);
        return $academicController->getSubject($request, $params);
    });

    $router->put('/subjects/{id}', static function (HttpRequest $request, array $params) use ($container): JsonResponse {
        /** @var AcademicCatalogController $academicController */
        $academicController = $container->get(AcademicCatalogController::class);
        return $academicController->updateSubject($request, $params);
    }, [$authenticate, $authorizeRoles(['admin'])]);

    $router->delete('/subjects/{id}', static function (HttpRequest $request, array $params) use ($container): JsonResponse {
        /** @var AcademicCatalogController $academicController */
        $academicController = $container->get(AcademicCatalogController::class);
        return $academicController->deleteSubject($request, $params);
    }, [$authenticate, $authorizeRoles(['admin'])]);

    // --- Rooms ---
    $router->get('/rooms', static function (HttpRequest $request) use ($container): JsonResponse {
        /** @var AcademicCatalogController $academicController */
        $academicController = $container->get(AcademicCatalogController::class);
        return $academicController->listRooms($request);
    });

    $router->post('/rooms', static function (HttpRequest $request) use ($container): JsonResponse {
        /** @var AcademicCatalogController $academicController */
        $academicController = $container->get(AcademicCatalogController::class);
        return $academicController->createRoom($request);
    }, [$authenticate, $authorizeRoles(['admin'])]);

    $router->get('/rooms/{id}', static function (HttpRequest $request, array $params) use ($container): JsonResponse {
        /** @var AcademicCatalogController $academicController */
        $academicController = $container->get(AcademicCatalogController::class);
        return $academicController->getRoom($request, $params);
    });

    $router->put('/rooms/{id}', static function (HttpRequest $request, array $params) use ($container): JsonResponse {
        /** @var AcademicCatalogController $academicController */
        $academicController = $container->get(AcademicCatalogController::class);
        return $academicController->updateRoom($request, $params);
    }, [$authenticate, $authorizeRoles(['admin'])]);

    $router->delete('/rooms/{id}', static function (HttpRequest $request, array $params) use ($container): JsonResponse {
        /** @var AcademicCatalogController $academicController */
        $academicController = $container->get(AcademicCatalogController::class);
        return $academicController->deleteRoom($request, $params);
    }, [$authenticate, $authorizeRoles(['admin'])]);

    // --- Sections ---
    $router->get('/sections', static function (HttpRequest $request) use ($container): JsonResponse {
        /** @var AcademicCatalogController $academicController */
        $academicController = $container->get(AcademicCatalogController::class);
        return $academicController->listSections($request);
    });

    $router->post('/sections', static function (HttpRequest $request) use ($container): JsonResponse {
        /** @var AcademicCatalogController $academicController */
        $academicController = $container->get(AcademicCatalogController::class);
        return $academicController->createSection($request);
    }, [$authenticate, $authorizeRoles(['admin'])]);

    $router->get('/sections/{id}', static function (HttpRequest $request, array $params) use ($container): JsonResponse {
        /** @var AcademicCatalogController $academicController */
        $academicController = $container->get(AcademicCatalogController::class);
        return $academicController->getSection($request, $params);
    });

    $router->put('/sections/{id}', static function (HttpRequest $request, array $params) use ($container): JsonResponse {
        /** @var AcademicCatalogController $academicController */
        $academicController = $container->get(AcademicCatalogController::class);
        return $academicController->updateSection($request, $params);
    }, [$authenticate, $authorizeRoles(['admin'])]);

    $router->delete('/sections/{id}', static function (HttpRequest $request, array $params) use ($container): JsonResponse {
        /** @var AcademicCatalogController $academicController */
        $academicController = $container->get(AcademicCatalogController::class);
        return $academicController->deleteSection($request, $params);
    }, [$authenticate, $authorizeRoles(['admin'])]);

    $router->get('/sections/{id}/students', static function (HttpRequest $request, array $params) use ($container): JsonResponse {
        /** @var AcademicCatalogController $academicController */
        $academicController = $container->get(AcademicCatalogController::class);
        return $academicController->listSectionStudents($request, $params);
    }, [$authenticate]);

    // --- Schedules ---
    $router->get('/schedules', static function (HttpRequest $request) use ($container): JsonResponse {
        /** @var ScheduleFoundationController $scheduleController */
        $scheduleController = $container->get(ScheduleFoundationController::class);
        return $scheduleController->listSchedules($request);
    }, [$authenticate, $authorizeRoles(['admin'])]);

    $router->post('/schedules', static function (HttpRequest $request) use ($container): JsonResponse {
        /** @var ScheduleFoundationController $scheduleController */
        $scheduleController = $container->get(ScheduleFoundationController::class);
        return $scheduleController->createSchedule($request);
    }, [$authenticate, $authorizeRoles(['admin'])]);

    $router->post('/schedules/validate-conflicts', static function (HttpRequest $request) use ($container): JsonResponse {
        /** @var ScheduleFoundationController $scheduleController */
        $scheduleController = $container->get(ScheduleFoundationController::class);
        return $scheduleController->validateConflicts($request);
    }, [$authenticate, $authorizeRoles(['admin'])]);

    $router->patch('/schedules/{id}', static function (HttpRequest $request, array $params) use ($container): JsonResponse {
        /** @var ScheduleFoundationController $scheduleController */
        $scheduleController = $container->get(ScheduleFoundationController::class);
        return $scheduleController->updateSchedule($request, $params);
    }, [$authenticate, $authorizeRoles(['admin'])]);

    $router->get('/student/schedule', static function (HttpRequest $request) use ($container): JsonResponse {
        /** @var StudentScheduleController $studentScheduleController */
        $studentScheduleController = $container->get(StudentScheduleController::class);
        return $studentScheduleController->listMySchedule($request);
    }, [$authenticate, $authorizeRoles(['student'])]);

    $router->get('/instructor/schedule', static function (HttpRequest $request) use ($container): JsonResponse {
        /** @var InstructorScheduleController $controller */
        $controller = $container->get(InstructorScheduleController::class);
        return $controller->listMine($request);
    }, [$authenticate, $authorizeRoles(['instructor'])]);

    $router->get('/student/schedule-change-requests', static function (HttpRequest $request) use ($container): JsonResponse {
        /** @var ScheduleChangeRequestController $controller */
        $controller = $container->get(ScheduleChangeRequestController::class);
        return $controller->listMine($request);
    }, [$authenticate, $authorizeRoles(['student'])]);

    $router->post('/student/schedule-change-requests', static function (HttpRequest $request) use ($container): JsonResponse {
        /** @var ScheduleChangeRequestController $controller */
        $controller = $container->get(ScheduleChangeRequestController::class);
        return $controller->create($request);
    }, [$authenticate, $authorizeRoles(['student'])]);

    $router->get('/schedule-change-requests', static function (HttpRequest $request) use ($container): JsonResponse {
        /** @var ScheduleChangeRequestController $controller */
        $controller = $container->get(ScheduleChangeRequestController::class);
        return $controller->listAll($request);
    }, [$authenticate, $authorizeRoles(['admin'])]);

    $router->patch('/schedule-change-requests/{id}', static function (HttpRequest $request, array $params) use ($container): JsonResponse {
        /** @var ScheduleChangeRequestController $controller */
        $controller = $container->get(ScheduleChangeRequestController::class);
        return $controller->review($request, $params);
    }, [$authenticate, $authorizeRoles(['admin'])]);

    $router->get('/instructor/announcements', static function (HttpRequest $request) use ($container): JsonResponse {
        /** @var InstructorAnnouncementController $controller */
        $controller = $container->get(InstructorAnnouncementController::class);
        return $controller->listMine($request);
    }, [$authenticate, $authorizeRoles(['instructor'])]);

    $router->post('/instructor/announcements', static function (HttpRequest $request) use ($container): JsonResponse {
        /** @var InstructorAnnouncementController $controller */
        $controller = $container->get(InstructorAnnouncementController::class);
        return $controller->create($request);
    }, [$authenticate, $authorizeRoles(['instructor'])]);

    $router->put('/instructor/announcements/{id}', static function (HttpRequest $request, array $params) use ($container): JsonResponse {
        /** @var InstructorAnnouncementController $controller */
        $controller = $container->get(InstructorAnnouncementController::class);
        return $controller->update($request, $params);
    }, [$authenticate, $authorizeRoles(['instructor'])]);

    $router->post(
        '/instructor/announcements/{id}/archive',
        static function (HttpRequest $request, array $params) use ($container): JsonResponse {
            /** @var InstructorAnnouncementController $controller */
            $controller = $container->get(InstructorAnnouncementController::class);
            return $controller->archive($request, $params);
        },
        [$authenticate, $authorizeRoles(['instructor'])]
    );

    $router->delete(
        '/instructor/announcements/{id}/archive',
        static function (HttpRequest $request, array $params) use ($container): JsonResponse {
            /** @var InstructorAnnouncementController $controller */
            $controller = $container->get(InstructorAnnouncementController::class);
            return $controller->restore($request, $params);
        },
        [$authenticate, $authorizeRoles(['instructor'])]
    );

    $router->get('/student/announcements', static function (HttpRequest $request) use ($container): JsonResponse {
        /** @var StudentAnnouncementController $controller */
        $controller = $container->get(StudentAnnouncementController::class);
        return $controller->listMine($request);
    }, [$authenticate, $authorizeRoles(['student'])]);

    $router->post(
        '/student/announcements/{id}/archive',
        static function (HttpRequest $request, array $params) use ($container): JsonResponse {
            /** @var StudentAnnouncementController $controller */
            $controller = $container->get(StudentAnnouncementController::class);
            return $controller->archive($request, $params);
        },
        [$authenticate, $authorizeRoles(['student'])]
    );

    $router->delete(
        '/student/announcements/{id}/archive',
        static function (HttpRequest $request, array $params) use ($container): JsonResponse {
            /** @var StudentAnnouncementController $controller */
            $controller = $container->get(StudentAnnouncementController::class);
            return $controller->restore($request, $params);
        },
        [$authenticate, $authorizeRoles(['student'])]
    );

    $router->get('/notifications', static function (HttpRequest $request) use ($container): JsonResponse {
        /** @var NotificationController $controller */
        $controller = $container->get(NotificationController::class);
        return $controller->listMine($request);
    }, [$authenticate]);

    $router->patch('/notifications/{id}/read', static function (HttpRequest $request, array $params) use ($container): JsonResponse {
        /** @var NotificationController $controller */
        $controller = $container->get(NotificationController::class);
        return $controller->markRead($request, $params);
    }, [$authenticate]);

    $router->post('/notifications/read-all', static function (HttpRequest $request) use ($container): JsonResponse {
        /** @var NotificationController $controller */
        $controller = $container->get(NotificationController::class);
        return $controller->markAllRead($request);
    }, [$authenticate]);
};
