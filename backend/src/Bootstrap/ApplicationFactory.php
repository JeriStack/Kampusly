<?php

declare(strict_types=1);

namespace Kampusly\Bootstrap;

use Kampusly\Academic\Application\Service\AcademicCatalogService;
use Kampusly\Academic\Domain\Repository\RoomRepositoryInterface;
use Kampusly\Academic\Domain\Repository\SectionRepositoryInterface;
use Kampusly\Academic\Domain\Repository\SubjectRepositoryInterface;
use Kampusly\Academic\Infrastructure\Persistence\PdoRoomRepository;
use Kampusly\Academic\Infrastructure\Persistence\PdoSectionRepository;
use Kampusly\Academic\Infrastructure\Persistence\PdoSubjectRepository;
use Kampusly\Academic\Presentation\Http\Controller\AcademicCatalogController;
use Kampusly\Announcements\Domain\Repository\AnnouncementRepositoryInterface;
use Kampusly\Announcements\Infrastructure\Persistence\PdoAnnouncementRepository;
use Kampusly\ClassNotices\Application\Service\ClassNoticeFoundationService;
use Kampusly\ClassNotices\Domain\Repository\ClassNoticeRepositoryInterface;
use Kampusly\ClassNotices\Infrastructure\Persistence\PdoClassNoticeRepository;
use Kampusly\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use Kampusly\IdentityAccess\Infrastructure\Persistence\PdoUserRepository;
use Kampusly\Notifications\Domain\Repository\NotificationRepositoryInterface;
use Kampusly\Notifications\Infrastructure\Persistence\PdoNotificationRepository;
use Kampusly\Scheduling\Application\Service\ScheduleFoundationService;
use Kampusly\Scheduling\Domain\Repository\ScheduleRepositoryInterface;
use Kampusly\Scheduling\Domain\Service\ScheduleConflictChecker;
use Kampusly\Scheduling\Infrastructure\Persistence\PdoScheduleRepository;
use Kampusly\Scheduling\Presentation\Http\Controller\ScheduleFoundationController;
use Kampusly\Shared\Application\Contract\AuditLoggerInterface;
use Kampusly\Shared\Application\Contract\TransactionManagerInterface;
use Kampusly\Shared\Infrastructure\Database\PdoConnectionFactory;
use Kampusly\Shared\Infrastructure\Database\PdoTransactionManager;
use Kampusly\Shared\Infrastructure\Health\HealthCheckService;
use Kampusly\Shared\Infrastructure\Http\CorsMiddleware;
use Kampusly\Shared\Infrastructure\Http\HttpRequest;
use Kampusly\Shared\Infrastructure\Http\JsonResponse;
use Kampusly\Shared\Infrastructure\Logging\LoggerFactory;
use Kampusly\Shared\Infrastructure\Logging\PdoAuditLogger;
use Kampusly\Shared\Presentation\Http\Controller\OpenApiDocumentationController;
use Throwable;

final class ApplicationFactory
{
    private Container $container;
    private Router $router;
    private ErrorHandler $errorHandler;
    private CorsMiddleware $corsMiddleware;

    public function __construct(
        Container $container,
        Router $router,
        ErrorHandler $errorHandler,
        CorsMiddleware $corsMiddleware
    ) {
        $this->container = $container;
        $this->router = $router;
        $this->errorHandler = $errorHandler;
        $this->corsMiddleware = $corsMiddleware;
    }

    /**
     * Boot the Kampusly application container, configuration, and routes.
     *
     * @param string|null $basePath Defaults to the `backend/` root directory
     */
    public static function create(?string $basePath = null): self
    {
        $rootDir = $basePath ?? dirname(__DIR__, 2);

        EnvLoader::load([
            $rootDir . '/.env',
            dirname($rootDir) . '/.env',
            $rootDir . '/.env.example',
            dirname($rootDir) . '/.env.example',
        ]);
        EnvLoader::validateRequired();

        $config = Config::fromDirectory($rootDir . '/config');
        $timezone = $config->getString('app.timezone', 'Asia/Manila');
        date_default_timezone_set($timezone);

        $logger = new LoggerFactory(
            $config->getString('logging.path', $rootDir . '/storage/logs/kampusly.log'),
            $config->getString('logging.level', 'debug'),
            $config->getString('app.env', 'local')
        );

        $container = new Container();
        $container->instance(Config::class, $config);
        $container->instance(LoggerFactory::class, $logger);

        $container->singleton(
            PdoConnectionFactory::class,
            static fn (Container $c): PdoConnectionFactory => new PdoConnectionFactory(
                $c->get(Config::class)->getArray('database')
            )
        );

        $container->singleton(
            TransactionManagerInterface::class,
            static fn (Container $c): TransactionManagerInterface => new PdoTransactionManager(
                $c->get(PdoConnectionFactory::class)->getConnection()
            )
        );

        $container->singleton(
            AuditLoggerInterface::class,
            static fn (Container $c): AuditLoggerInterface => new PdoAuditLogger(
                $c->get(PdoConnectionFactory::class)->getConnection(),
                $c->get(LoggerFactory::class)
            )
        );

        $container->singleton(
            HealthCheckService::class,
            static fn (Container $c): HealthCheckService => new HealthCheckService(
                $c->get(Config::class),
                $c->get(PdoConnectionFactory::class),
                $c->get(LoggerFactory::class)
            )
        );

        $container->singleton(
            UserRepositoryInterface::class,
            static fn (Container $c): UserRepositoryInterface => new PdoUserRepository(
                $c->get(PdoConnectionFactory::class)->getConnection()
            )
        );

        $container->singleton(
            SubjectRepositoryInterface::class,
            static fn (Container $c): SubjectRepositoryInterface => new PdoSubjectRepository(
                $c->get(PdoConnectionFactory::class)->getConnection()
            )
        );

        $container->singleton(
            RoomRepositoryInterface::class,
            static fn (Container $c): RoomRepositoryInterface => new PdoRoomRepository(
                $c->get(PdoConnectionFactory::class)->getConnection()
            )
        );

        $container->singleton(
            SectionRepositoryInterface::class,
            static fn (Container $c): SectionRepositoryInterface => new PdoSectionRepository(
                $c->get(PdoConnectionFactory::class)->getConnection()
            )
        );

        $container->singleton(
            AcademicCatalogService::class,
            static fn (Container $c): AcademicCatalogService => new AcademicCatalogService(
                $c->get(SubjectRepositoryInterface::class),
                $c->get(RoomRepositoryInterface::class),
                $c->get(SectionRepositoryInterface::class),
                $c->get(TransactionManagerInterface::class),
                $c->get(AuditLoggerInterface::class)
            )
        );

        $container->singleton(
            AcademicCatalogController::class,
            static fn (Container $c): AcademicCatalogController => new AcademicCatalogController(
                $c->get(AcademicCatalogService::class)
            )
        );

        $container->singleton(
            ScheduleConflictChecker::class,
            static fn (): ScheduleConflictChecker => new ScheduleConflictChecker()
        );

        $container->singleton(
            ScheduleRepositoryInterface::class,
            static fn (Container $c): ScheduleRepositoryInterface => new PdoScheduleRepository(
                $c->get(PdoConnectionFactory::class)->getConnection()
            )
        );

        $container->singleton(
            ScheduleFoundationService::class,
            static fn (Container $c): ScheduleFoundationService => new ScheduleFoundationService(
                $c->get(ScheduleRepositoryInterface::class),
                $c->get(ScheduleConflictChecker::class),
                $c->get(TransactionManagerInterface::class),
                $c->get(AuditLoggerInterface::class)
            )
        );

        $container->singleton(
            ScheduleFoundationController::class,
            static fn (Container $c): ScheduleFoundationController => new ScheduleFoundationController(
                $c->get(ScheduleFoundationService::class)
            )
        );

        $container->singleton(
            ClassNoticeRepositoryInterface::class,
            static fn (Container $c): ClassNoticeRepositoryInterface => new PdoClassNoticeRepository(
                $c->get(PdoConnectionFactory::class)->getConnection()
            )
        );

        $container->singleton(
            ClassNoticeFoundationService::class,
            static fn (Container $c): ClassNoticeFoundationService => new ClassNoticeFoundationService(
                $c->get(ClassNoticeRepositoryInterface::class),
                $c->get(TransactionManagerInterface::class),
                $c->get(AuditLoggerInterface::class)
            )
        );

        $container->singleton(
            AnnouncementRepositoryInterface::class,
            static fn (Container $c): AnnouncementRepositoryInterface => new PdoAnnouncementRepository(
                $c->get(PdoConnectionFactory::class)->getConnection()
            )
        );

        $container->singleton(
            NotificationRepositoryInterface::class,
            static fn (Container $c): NotificationRepositoryInterface => new PdoNotificationRepository(
                $c->get(PdoConnectionFactory::class)->getConnection()
            )
        );

        $specFile = is_file(dirname($rootDir) . '/docs/api/openapi.yaml')
            ? dirname($rootDir) . '/docs/api/openapi.yaml'
            : dirname($rootDir) . '/docs/phase-2/phase-2-api-contract.yaml';

        $container->singleton(
            OpenApiDocumentationController::class,
            static fn (Container $c): OpenApiDocumentationController => new OpenApiDocumentationController(
                $c->get(Config::class),
                $specFile
            )
        );

        $router = new Router($config->getString('app.api_prefix', '/api/v1'));
        $routesFile = $rootDir . '/routes/api.php';
        if (is_file($routesFile)) {
            $registerRoutes = require $routesFile;
            if (is_callable($registerRoutes)) {
                $registerRoutes($router, $container);
            }
        }

        $errorHandler = new ErrorHandler(
            $config->getBool('app.debug', false),
            $logger
        );

        $corsMiddleware = new CorsMiddleware(
            $config->getArray('security')
        );

        return new self($container, $router, $errorHandler, $corsMiddleware);
    }

    public function handle(HttpRequest $request): JsonResponse
    {
        $preflight = $this->corsMiddleware->handlePreflight($request);
        if ($preflight !== null) {
            return $preflight;
        }

        try {
            $response = $this->router->dispatch($request);
        } catch (Throwable $exception) {
            $response = $this->errorHandler->handle($exception, $request);
        }

        return $this->corsMiddleware->applyHeaders($request, $response);
    }

    public function getContainer(): Container
    {
        return $this->container;
    }
}
