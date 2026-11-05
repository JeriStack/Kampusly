<?php

declare(strict_types=1);

namespace Kampusly\Tests\Unit\Bootstrap;

use Kampusly\Bootstrap\ErrorHandler;
use Kampusly\Bootstrap\Router;
use Kampusly\Shared\Domain\Exception\MethodNotAllowedException;
use Kampusly\Shared\Domain\Exception\ResourceNotFoundException;
use Kampusly\Shared\Domain\Exception\ValidationException;
use Kampusly\Shared\Infrastructure\Http\HttpRequest;
use Kampusly\Shared\Infrastructure\Http\JsonResponse;
use Kampusly\Shared\Infrastructure\Http\RequestValidator;
use Kampusly\Shared\Infrastructure\Logging\LoggerFactory;
use Kampusly\Tests\Support\TestCase;
use RuntimeException;

final class RouterAndValidationTest extends TestCase
{
    public function testRouterDispatchesMatchingVersionedRouteWithParameters(): void
    {
        $router = new Router('/api/v1');
        $router->get('/schedules/{id}', static function (HttpRequest $req, array $params): JsonResponse {
            return new JsonResponse([
                'schedule_id' => $params['id'] ?? null,
                'request_id' => $req->getRequestId(),
            ]);
        });

        $request = new HttpRequest('GET', '/api/v1/schedules/42', [], [], [], 'req_unit_01');
        $response = $router->dispatch($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('42', $response->getData()['schedule_id']);
        $this->assertSame('req_unit_01', $response->getData()['request_id']);
    }

    public function testRouterThrowsResourceNotFoundAndMethodNotAllowed(): void
    {
        $router = new Router('/api/v1');
        $router->get('/health/live', static fn (): JsonResponse => new JsonResponse(['status' => 'ok']));

        $this->assertThrows(
            ResourceNotFoundException::class,
            static function () use ($router): void {
                $router->dispatch(new HttpRequest('GET', '/api/v1/unknown'));
            }
        );

        $this->assertThrows(
            MethodNotAllowedException::class,
            static function () use ($router): void {
                $router->dispatch(new HttpRequest('POST', '/api/v1/health/live'));
            }
        );
    }

    public function testRequestValidatorThrowsStructuredValidationException(): void
    {
        $validator = new RequestValidator([
            'email' => 'not-an-email',
            'capacity' => 'invalid-int',
            'role' => 'guest',
        ]);

        $validator
            ->requireString('full_name', 2, 100)
            ->requireEmail('email')
            ->requireInt('capacity', 1, 500)
            ->requireIn('role', ['admin', 'instructor', 'student']);

        /** @var ValidationException $exception */
        $exception = $this->assertThrows(
            ValidationException::class,
            static function () use ($validator): void {
                $validator->validateOrThrow();
            }
        );

        $this->assertSame(422, $exception->getHttpStatus());
        $this->assertSame('VALIDATION_ERROR', $exception->getErrorCode());

        $errors = $exception->getFieldErrors();
        $this->assertArrayHasKey('full_name', $errors);
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('capacity', $errors);
        $this->assertArrayHasKey('role', $errors);
    }

    public function testErrorHandlerMasksInternalExceptionDetailsWhenDebugIsFalse(): void
    {
        $logger = new LoggerFactory(dirname(__DIR__, 3) . '/storage/logs/test.log', 'error', 'production');
        $handler = new ErrorHandler(false, $logger);
        $request = new HttpRequest('GET', '/api/v1/health/ready', [], [], [], 'req_prod_99');

        $response = $handler->handle(
            new RuntimeException('SQLSTATE[HY000] sensitive internal detail'),
            $request
        );

        $this->assertSame(500, $response->getStatusCode());
        $data = $response->getData();
        $this->assertSame('INTERNAL_SERVER_ERROR', $data['code']);
        $this->assertSame('req_prod_99', $data['request_id']);
        $this->assertFalse(str_contains((string) $data['message'], 'SQLSTATE'));
    }
}

