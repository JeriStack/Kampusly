<?php

declare(strict_types=1);

namespace Kampusly\Tests\Feature\Api;

use Kampusly\Bootstrap\ApplicationFactory;
use Kampusly\IdentityAccess\Application\Service\UserAdministrationService;
use Kampusly\IdentityAccess\Domain\Entity\User;
use Kampusly\IdentityAccess\Domain\Policy\AdminPolicy;
use Kampusly\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use Kampusly\IdentityAccess\Domain\Service\PasswordHasher;
use Kampusly\IdentityAccess\Domain\ValueObject\AccountStatus;
use Kampusly\IdentityAccess\Domain\ValueObject\UserRole;
use Kampusly\IdentityAccess\Presentation\Http\Controller\UserController;
use Kampusly\Shared\Application\Contract\AuditLoggerInterface;
use Kampusly\Shared\Infrastructure\Http\ArraySessionManager;
use Kampusly\Shared\Infrastructure\Http\CsrfManager;
use Kampusly\Shared\Infrastructure\Http\HttpRequest;
use Kampusly\Shared\Infrastructure\Http\SessionInterface;
use Kampusly\Tests\Support\InMemoryUserRepository;
use Kampusly\Tests\Support\NullAuditLogger;
use Kampusly\Tests\Support\TestCase;

final class UserAdministrationEndpointTest extends TestCase
{
    private ApplicationFactory $app;
    private InMemoryUserRepository $userRepo;
    private ArraySessionManager $session;
    private CsrfManager $csrfManager;

    protected function setUp(): void
    {
        $this->app = ApplicationFactory::create(dirname(__DIR__, 3));
        $container = $this->app->getContainer();

        $hasher = new PasswordHasher();
        $this->session = new ArraySessionManager();
        $this->csrfManager = new CsrfManager($this->session);

        $adminHash = $hasher->hash('AdminPass123!');
        $studentHash = $hasher->hash('StudentPass123!');

        $this->userRepo = new InMemoryUserRepository([
            new User(1, 'Administrator', 'admin@kampusly.edu.ph', $adminHash, UserRole::Admin, AccountStatus::Active),
            new User(2, 'Prof. Cruz', 'cruz@kampusly.edu.ph', $studentHash, UserRole::Instructor, AccountStatus::Active),
            new User(3, 'Maria Santos', 'santos@kampusly.edu.ph', $studentHash, UserRole::Student, AccountStatus::Active),
        ]);

        $adminPolicy = new AdminPolicy();
        $nullAudit = new NullAuditLogger();

        $container->instance(SessionInterface::class, $this->session);
        $container->instance(CsrfManager::class, $this->csrfManager);
        $container->instance(UserRepositoryInterface::class, $this->userRepo);
        $container->instance(AuditLoggerInterface::class, $nullAudit);

        $userService = new UserAdministrationService(
            $this->userRepo,
            $hasher,
            $adminPolicy,
            $nullAudit
        );

        $container->instance(UserAdministrationService::class, $userService);
        $container->instance(UserController::class, new UserController($userService));
    }

    public function testListUsersAllowsAdmin(): void
    {
        $this->session->set('auth_user_id', 1);

        $request = new HttpRequest('GET', '/api/v1/users');
        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = $response->getData();

        $this->assertArrayHasKey('data', $data);
        $this->assertSame(3, count($data['data']));
    }

    public function testListUsersRejectsNonAdminWith403(): void
    {
        $this->session->set('auth_user_id', 3); // Student

        $request = new HttpRequest('GET', '/api/v1/users');
        $response = $this->app->handle($request);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('FORBIDDEN', $response->getData()['code']);
    }

    public function testAdminCanCreateNewUser(): void
    {
        $this->session->set('auth_user_id', 1);
        $csrfToken = $this->csrfManager->getToken();

        $payload = [
            'full_name' => 'Dr. Jose Rizal',
            'email' => 'rizal@kampusly.edu.ph',
            'password' => 'HeroicPass123!',
            'role' => 'instructor',
            'status' => 'active',
        ];

        $request = new HttpRequest(
            'POST',
            '/api/v1/users',
            [],
            ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrfToken],
            $payload
        );
        $response = $this->app->handle($request);

        $this->assertSame(201, $response->getStatusCode());
        $data = $response->getData();
        $this->assertSame('rizal@kampusly.edu.ph', $data['data']['email']);
        $this->assertSame('instructor', $data['data']['role']);

        $persisted = $this->userRepo->findByEmail('rizal@kampusly.edu.ph');
        $this->assertNotNull($persisted);
    }

    public function testAdminCanUpdateUserStatus(): void
    {
        $this->session->set('auth_user_id', 1);
        $csrfToken = $this->csrfManager->getToken();

        $request = new HttpRequest(
            'PATCH',
            '/api/v1/users/2/status',
            [],
            ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrfToken],
            ['status' => 'inactive']
        );
        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $user2 = $this->userRepo->findById(2);
        $this->assertNotNull($user2);
        $this->assertSame('inactive', $user2->getStatus()->value);
    }

    public function testAdminCannotDeactivateOwnAccount(): void
    {
        $this->session->set('auth_user_id', 1);
        $csrfToken = $this->csrfManager->getToken();

        $request = new HttpRequest(
            'PATCH',
            '/api/v1/users/1/status',
            [],
            ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrfToken],
            ['status' => 'inactive']
        );
        $response = $this->app->handle($request);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('FORBIDDEN', $response->getData()['code']);

        // Remains active
        $admin = $this->userRepo->findById(1);
        $this->assertNotNull($admin);
        $this->assertSame('active', $admin->getStatus()->value);
    }

    public function testMutatingRequestWithoutCsrfTokenIsRejected(): void
    {
        $this->session->set('auth_user_id', 1);

        // Missing X-CSRF-Token header on PATCH
        $request = new HttpRequest(
            'PATCH',
            '/api/v1/users/2/status',
            [],
            ['Content-Type' => 'application/json'],
            ['status' => 'inactive']
        );
        $response = $this->app->handle($request);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('FORBIDDEN', $response->getData()['code']);
    }
}
