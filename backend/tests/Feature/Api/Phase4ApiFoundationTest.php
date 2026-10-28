<?php

declare(strict_types=1);

namespace Kampusly\Tests\Feature\Api;

use Kampusly\Bootstrap\ApplicationFactory;
use Kampusly\Scheduling\Application\Service\ScheduleFoundationService;
use Kampusly\Scheduling\Domain\Repository\ScheduleRepositoryInterface;
use Kampusly\Scheduling\Domain\Service\ScheduleConflictChecker;
use Kampusly\Scheduling\Presentation\Http\Controller\ScheduleFoundationController;
use Kampusly\Shared\Application\Contract\AuditLoggerInterface;
use Kampusly\Shared\Application\Contract\TransactionManagerInterface;
use Kampusly\Shared\Infrastructure\Http\HttpRequest;
use Kampusly\Tests\Support\InMemoryScheduleRepository;
use Kampusly\Tests\Support\NullAuditLogger;
use Kampusly\Tests\Support\NullTransactionManager;
use Kampusly\Tests\Support\TestCase;

final class Phase4ApiFoundationTest extends TestCase
{
    private ApplicationFactory $app;

    protected function setUp(): void
    {
        $this->app = ApplicationFactory::create(dirname(__DIR__, 3));
        $container = $this->app->getContainer();

        $inMemoryRepo = new InMemoryScheduleRepository();
        $nullTx = new NullTransactionManager();
        $nullAudit = new NullAuditLogger();

        $container->instance(ScheduleRepositoryInterface::class, $inMemoryRepo);
        $container->instance(TransactionManagerInterface::class, $nullTx);
        $container->instance(AuditLoggerInterface::class, $nullAudit);

        $scheduleService = new ScheduleFoundationService(
            $inMemoryRepo,
            new ScheduleConflictChecker(),
            $nullTx,
            $nullAudit
        );

        $container->instance(ScheduleFoundationService::class, $scheduleService);
        $container->instance(
            ScheduleFoundationController::class,
            new ScheduleFoundationController($scheduleService)
        );
    }

    public function testRootEndpointReturnsServiceMetadataAndPhase4Status(): void
    {
        $request = new HttpRequest('GET', '/api/v1');
        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = $response->getData();

        $this->assertArrayHasKey('service', $data);
        $this->assertSame('kampusly-api', $data['service']);
        $this->assertSame('ok', $data['status']);
        $this->assertArrayHasKey('phase', $data);
        $this->assertStringContainsString('Phase 4', (string) $data['phase']);
        $this->assertArrayHasKey('links', $data);
    }

    public function testHealthProbeEndpoints(): void
    {
        $healthReq = new HttpRequest('GET', '/api/v1/health');
        $healthRes = $this->app->handle($healthReq);
        $this->assertSame(200, $healthRes->getStatusCode());
        $this->assertSame('ok', $healthRes->getData()['status']);

        $liveReq = new HttpRequest('GET', '/api/v1/health/live');
        $liveRes = $this->app->handle($liveReq);
        $this->assertSame(200, $liveRes->getStatusCode());
        $this->assertSame('ok', $liveRes->getData()['status']);
    }

    public function testOpenApiYamlEndpointReturnsSpec(): void
    {
        $request = new HttpRequest('GET', '/api/v1/openapi.yaml');
        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('application/yaml', $response->getContentType());
        $this->assertStringContainsString('openapi: 3.1.0', (string) $response->getRawBody());
    }

    public function testSwaggerUiEndpointReturnsHtml(): void
    {
        $request = new HttpRequest('GET', '/api/v1/docs');
        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('text/html', $response->getContentType());
        $this->assertStringContainsString('SwaggerUIBundle', (string) $response->getRawBody());
    }

    public function testScheduleConflictValidationEndpoint(): void
    {
        $payload = [
            'subject_id' => 1,
            'instructor_id' => 1,
            'room_id' => 1,
            'section_id' => 1,
            'school_year' => '2026-2027',
            'semester' => '1st',
            'day_of_week' => 'Monday',
            'start_time' => '14:00',
            'end_time' => '16:00',
        ];

        $request = new HttpRequest(
            'POST',
            '/api/v1/schedules/validate-conflicts',
            [],
            ['Content-Type' => 'application/json'],
            $payload
        );

        $response = $this->app->handle($request);
        $this->assertSame(200, $response->getStatusCode());
        $data = $response->getData();

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('has_conflicts', $data['data']);
        $this->assertFalse($data['data']['has_conflicts']);
        $this->assertArrayHasKey('conflicts', $data['data']);
        $this->assertCount(0, $data['data']['conflicts']);
    }

    public function testScheduleConflictValidationRejectsInvalidTimeOrder(): void
    {
        $invalidPayload = [
            'subject_id' => 1,
            'instructor_id' => 1,
            'room_id' => 1,
            'section_id' => 1,
            'school_year' => '2026-2027',
            'semester' => '1st',
            'day_of_week' => 'Monday',
            'start_time' => '16:00',
            'end_time' => '14:00', // start is later than end
        ];

        $request = new HttpRequest(
            'POST',
            '/api/v1/schedules/validate-conflicts',
            [],
            ['Content-Type' => 'application/json'],
            $invalidPayload
        );

        $response = $this->app->handle($request);
        $this->assertSame(422, $response->getStatusCode());
        $data = $response->getData();

        $this->assertSame('VALIDATION_ERROR', $data['code']);
        $this->assertArrayHasKey('errors', $data);
        $this->assertArrayHasKey('end_time', $data['errors']);
    }

    public function testUnsupportedContentTypeRejectedOnMutatingRequest(): void
    {
        $request = new HttpRequest(
            'POST',
            '/api/v1/schedules/validate-conflicts',
            [],
            ['Content-Type' => 'text/plain'],
            ['key' => 'value']
        );

        $response = $this->app->handle($request);
        $this->assertSame(415, $response->getStatusCode());
        $this->assertSame('UNSUPPORTED_MEDIA_TYPE', $response->getData()['code']);
    }

    public function testUnknownRouteReturns404(): void
    {
        $request = new HttpRequest('GET', '/api/v1/non-existent-endpoint');
        $response = $this->app->handle($request);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('RESOURCE_NOT_FOUND', $response->getData()['code']);
    }

    public function testDisallowedMethodReturns405(): void
    {
        $request = new HttpRequest('DELETE', '/api/v1');
        $response = $this->app->handle($request);

        $this->assertSame(405, $response->getStatusCode());
        $this->assertSame('METHOD_NOT_ALLOWED', $response->getData()['code']);
        $this->assertArrayHasKey('Allow', $response->getHeaders());
    }
}
