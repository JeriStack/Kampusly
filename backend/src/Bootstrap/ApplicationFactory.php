<?php

declare(strict_types=1);

namespace Kampusly\Bootstrap;

use Kampusly\Shared\Infrastructure\Database\PdoConnectionFactory;
use Kampusly\Shared\Infrastructure\Health\HealthCheckService;
use Kampusly\Shared\Infrastructure\Http\CorsMiddleware;
use Kampusly\Shared\Infrastructure\Http\HttpRequest;
use Kampusly\Shared\Infrastructure\Http\JsonResponse;
use Kampusly\Shared\Infrastructure\Logging\LoggerFactory;
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
            HealthCheckService::class,
            static fn (Container $c): HealthCheckService => new HealthCheckService(
                $c->get(Config::class),
                $c->get(PdoConnectionFactory::class),
                $c->get(LoggerFactory::class)
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

