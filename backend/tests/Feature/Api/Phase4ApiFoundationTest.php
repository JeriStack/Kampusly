<?php

declare(strict_types=1);

namespace Kampusly\Tests\Feature\Api;

use Kampusly\Academic\Domain\Entity\Section;
use Kampusly\Academic\Domain\Entity\StudentProfile;
use Kampusly\Academic\Domain\ValueObject\ReferenceStatus;
use Kampusly\Academic\Domain\ValueObject\Semester;
use Kampusly\Bootstrap\ApplicationFactory;
use Kampusly\IdentityAccess\Domain\Entity\User;
use Kampusly\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use Kampusly\IdentityAccess\Domain\Service\PasswordHasher;
use Kampusly\IdentityAccess\Domain\ValueObject\AccountStatus;
use Kampusly\IdentityAccess\Domain\ValueObject\UserRole;
use Kampusly\Scheduling\Application\Service\ScheduleFoundationService;
use Kampusly\Scheduling\Domain\Repository\ScheduleRepositoryInterface;
use Kampusly\Scheduling\Domain\Service\ScheduleConflictChecker;
use Kampusly\Scheduling\Domain\Entity\Schedule;
use Kampusly\Scheduling\Domain\ValueObject\DayOfWeek;
use Kampusly\Scheduling\Domain\ValueObject\ScheduleStatus;
use Kampusly\Scheduling\Domain\ValueObject\TimeSlot;
use Kampusly\Scheduling\Presentation\Http\Controller\ScheduleFoundationController;
use Kampusly\Academic\Domain\Repository\SectionRepositoryInterface;
use Kampusly\Academic\Domain\Repository\StudentRepositoryInterface;
use Kampusly\Shared\Application\Contract\AuditLoggerInterface;
use Kampusly\Shared\Application\Contract\TransactionManagerInterface;
use Kampusly\Shared\Infrastructure\Http\ArraySessionManager;
use Kampusly\Shared\Infrastructure\Http\CsrfManager;
use Kampusly\Shared\Infrastructure\Http\HttpRequest;
use Kampusly\Shared\Infrastructure\Http\SessionInterface;
use Kampusly\Tests\Support\InMemoryUserRepository;
use Kampusly\Tests\Support\InMemorySectionRepository;
use Kampusly\Tests\Support\InMemoryStudentRepository;
use Kampusly\Tests\Support\InMemoryScheduleRepository;
use Kampusly\Tests\Support\NullAuditLogger;
use Kampusly\Tests\Support\NullTransactionManager;
use Kampusly\Tests\Support\TestCase;

final class Phase4ApiFoundationTest extends TestCase
{
    private ApplicationFactory $app;
    private ArraySessionManager $session;
    private CsrfManager $csrfManager;

    protected function setUp(): void
    {
        $this->app = ApplicationFactory::create(dirname(__DIR__, 3));
        $container = $this->app->getContainer();

        $inMemoryRepo = new InMemoryScheduleRepository();
        $nullTx = new NullTransactionManager();
        $nullAudit = new NullAuditLogger();
        $this->session = new ArraySessionManager();
        $this->csrfManager = new CsrfManager($this->session);
        $hasher = new PasswordHasher();
        $sectionRepository = new InMemorySectionRepository([
            new Section(
                1,
                'BSCS 2A',
                'BS Computer Science',
                2,
                '2026-2027',
                Semester::First
            ),
        ]);
        $studentRepository = new InMemoryStudentRepository([
            new StudentProfile(1, 2, 1, '2026-0001', 'Student', 'User', 'BS Computer Science'),
        ]);

        $container->instance(ScheduleRepositoryInterface::class, $inMemoryRepo);
        $container->instance(SectionRepositoryInterface::class, $sectionRepository);
        $container->instance(StudentRepositoryInterface::class, $studentRepository);
        $container->instance(TransactionManagerInterface::class, $nullTx);
        $container->instance(AuditLoggerInterface::class, $nullAudit);
        $container->instance(SessionInterface::class, $this->session);
        $container->instance(CsrfManager::class, $this->csrfManager);
        $container->instance(
            UserRepositoryInterface::class,
            new InMemoryUserRepository([
                new User(
                    1,
                    'Administrator',
                    'admin@kampusly.edu.ph',
                    $hasher->hash('AdminPassword123!'),
                    UserRole::Admin,
                    AccountStatus::Active
                ),
                new User(
                    2,
                    'Student',
                    'student@kampusly.edu.ph',
                    $hasher->hash('StudentPassword123!'),
                    UserRole::Student,
                    AccountStatus::Active
                ),
            ])
        );

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
        $this->assertStringContainsString('Phase', (string) $data['phase']);
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
        $this->session->set('auth_user_id', 1);
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
            [
                'Content-Type' => 'application/json',
                'X-CSRF-Token' => $this->csrfManager->getToken(),
            ],
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

    public function testScheduleListRequiresAdminAuthentication(): void
    {
        $unauthenticated = $this->app->handle(new HttpRequest('GET', '/api/v1/schedules'));
        $this->assertSame(401, $unauthenticated->getStatusCode());

        $this->session->set('auth_user_id', 1);
        $authenticated = $this->app->handle(new HttpRequest('GET', '/api/v1/schedules'));
        $this->assertSame(200, $authenticated->getStatusCode());
        $this->assertArrayHasKey('data', $authenticated->getData());
    }

    public function testStudentCanReadOnlyTheirAssignedSectionScheduleIncludingDrafts(): void
    {
        $scheduleRepository = $this->app->getContainer()->get(ScheduleRepositoryInterface::class);
        $scheduleRepository->create(new Schedule(
            null,
            1,
            1,
            1,
            1,
            '2026-2027',
            Semester::First,
            DayOfWeek::Monday,
            new TimeSlot('08:00', '09:00'),
            ScheduleStatus::Draft
        ));
        $scheduleRepository->create(new Schedule(
            null,
            1,
            1,
            1,
            2,
            '2026-2027',
            Semester::First,
            DayOfWeek::Monday,
            new TimeSlot('08:00', '09:00'),
            ScheduleStatus::Published
        ));

        $this->session->set('auth_user_id', 2);
        $response = $this->app->handle(new HttpRequest('GET', '/api/v1/student/schedule'));

        $this->assertSame(200, $response->getStatusCode());
        $data = $response->getData()['data'];
        $this->assertSame('BSCS 2A', $data['section']['section_name']);
        $this->assertCount(1, $data['schedules']);
        $this->assertSame('draft', $data['schedules'][0]['status']);
        $this->assertSame(1, $data['schedules'][0]['section_id']);
    }

    public function testAdminCannotReadStudentScheduleEndpoint(): void
    {
        $this->session->set('auth_user_id', 1);
        $response = $this->app->handle(new HttpRequest('GET', '/api/v1/student/schedule'));

        $this->assertSame(403, $response->getStatusCode());
    }

    public function testAdminCanCreateDraftSchedule(): void
    {
        $this->session->set('auth_user_id', 1);
        $token = $this->csrfManager->getToken();
        $request = new HttpRequest(
            'POST',
            '/api/v1/schedules',
            [],
            ['Content-Type' => 'application/json', 'X-CSRF-Token' => $token],
            [
                'subject_id' => 1,
                'instructor_id' => 1,
                'room_id' => 1,
                'section_id' => 1,
                'school_year' => '2026-2027',
                'semester' => '1st',
                'day_of_week' => 'Monday',
                'start_time' => '08:00',
                'end_time' => '09:00',
                'status' => 'draft',
            ]
        );

        $response = $this->app->handle($request);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('draft', $response->getData()['data']['status']);
        $this->assertSame('Monday', $response->getData()['data']['day_of_week']);
    }

    public function testAdminCanPublishScheduleWithConflictValidation(): void
    {
        $this->session->set('auth_user_id', 1);
        $draft = $this->app->getContainer()->get(ScheduleRepositoryInterface::class)->create(new Schedule(
            null,
            1,
            1,
            1,
            1,
            '2026-2027',
            Semester::First,
            DayOfWeek::Monday,
            new TimeSlot('08:00', '09:00'),
            ScheduleStatus::Draft
        ));
        $token = $this->csrfManager->getToken();
        $response = $this->app->handle(new HttpRequest(
            'PATCH',
            '/api/v1/schedules/' . $draft->getId(),
            [],
            ['Content-Type' => 'application/json', 'X-CSRF-Token' => $token],
            [
                'subject_id' => 1,
                'instructor_id' => 1,
                'room_id' => 1,
                'section_id' => 1,
                'school_year' => '2026-2027',
                'semester' => '1st',
                'day_of_week' => 'Monday',
                'start_time' => '08:00',
                'end_time' => '09:00',
                'status' => 'published',
            ]
        ));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('published', $response->getData()['data']['status']);
    }

    public function testNonAdminCannotCreateSchedule(): void
    {
        $this->session->set('auth_user_id', 2);
        $token = $this->csrfManager->getToken();
        $request = new HttpRequest(
            'POST',
            '/api/v1/schedules',
            [],
            ['Content-Type' => 'application/json', 'X-CSRF-Token' => $token],
            [
                'subject_id' => 1,
                'instructor_id' => 1,
                'room_id' => 1,
                'section_id' => 1,
                'school_year' => '2026-2027',
                'semester' => '1st',
                'day_of_week' => 'Monday',
                'start_time' => '08:00',
                'end_time' => '09:00',
                'status' => 'draft',
            ]
        );

        $response = $this->app->handle($request);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function testScheduleConflictValidationRejectsInvalidTimeOrder(): void
    {
        $this->session->set('auth_user_id', 1);
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
            [
                'Content-Type' => 'application/json',
                'X-CSRF-Token' => $this->csrfManager->getToken(),
            ],
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
