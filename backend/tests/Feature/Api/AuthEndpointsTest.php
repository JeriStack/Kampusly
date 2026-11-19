<?php

declare(strict_types=1);

namespace Kampusly\Tests\Feature\Api;

use Kampusly\Bootstrap\ApplicationFactory;
use Kampusly\IdentityAccess\Application\Service\AuthenticationService;
use Kampusly\IdentityAccess\Domain\Entity\User;
use Kampusly\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use Kampusly\IdentityAccess\Domain\Service\PasswordHasher;
use Kampusly\IdentityAccess\Domain\ValueObject\AccountStatus;
use Kampusly\IdentityAccess\Domain\ValueObject\UserRole;
use Kampusly\IdentityAccess\Presentation\Http\Controller\AuthController;
use Kampusly\Shared\Application\Contract\AuditLoggerInterface;
use Kampusly\Shared\Infrastructure\Http\ArraySessionManager;
use Kampusly\Shared\Infrastructure\Http\CsrfManager;
use Kampusly\Shared\Infrastructure\Http\HttpRequest;
use Kampusly\Shared\Infrastructure\Http\RateLimiter;
use Kampusly\Shared\Infrastructure\Http\SessionInterface;
use Kampusly\Tests\Support\InMemoryUserRepository;
use Kampusly\Tests\Support\NullAuditLogger;
use Kampusly\Tests\Support\TestCase;

final class AuthEndpointsTest extends TestCase
{
    private ApplicationFactory $app;
    private InMemoryUserRepository $userRepo;
    private ArraySessionManager $session;
    private CsrfManager $csrfManager;
    private PasswordHasher $hasher;

    protected function setUp(): void
    {
        $this->app = ApplicationFactory::create(dirname(__DIR__, 3));
        $container = $this->app->getContainer();

        $this->hasher = new PasswordHasher();
        $this->session = new ArraySessionManager();
        $this->csrfManager = new CsrfManager($this->session);

        $adminHash = $this->hasher->hash('AdminPassword123!');
        $instructorHash = $this->hasher->hash('ProfCruz123!');
        $studentHash = $this->hasher->hash('StudentSantos123!');

        $this->userRepo = new InMemoryUserRepository([
            new User(1, 'Administrator', 'admin@kampusly.edu.ph', $adminHash, UserRole::Admin, AccountStatus::Active),
            new User(2, 'Prof. Cruz', 'cruz@kampusly.edu.ph', $instructorHash, UserRole::Instructor, AccountStatus::Active),
            new User(3, 'Maria Santos', 'santos@kampusly.edu.ph', $studentHash, UserRole::Student, AccountStatus::Active),
            new User(4, 'Inactive User', 'inactive@kampusly.edu.ph', $studentHash, UserRole::Student, AccountStatus::Inactive),
        ]);

        $this->userRepo->setInstructorProfile(2, [
            'id' => 10,
            'user_id' => 2,
            'employee_number' => 'EMP-001',
            'department' => 'College of Computer Studies',
        ]);

        $this->userRepo->setStudentProfile(3, [
            'id' => 20,
            'user_id' => 3,
            'student_number' => '2026-0001',
            'section_id' => 1,
            'section_name' => 'BSIT-3A',
            'program' => 'BS Information Technology',
        ]);

        $limiter = new RateLimiter(sys_get_temp_dir() . '/kampusly_auth_test_rates_' . bin2hex(random_bytes(3)));
        $nullAudit = new NullAuditLogger();

        $container->instance(SessionInterface::class, $this->session);
        $container->instance(CsrfManager::class, $this->csrfManager);
        $container->instance(UserRepositoryInterface::class, $this->userRepo);
        $container->instance(AuditLoggerInterface::class, $nullAudit);

        $authService = new AuthenticationService(
            $this->userRepo,
            $this->hasher,
            $this->session,
            $this->csrfManager,
            $limiter,
            $nullAudit
        );

        $container->instance(AuthenticationService::class, $authService);
        $container->instance(AuthController::class, new AuthController($authService));
    }

    public function testSuccessfulLoginReturnsSafeProfileAndCsrfToken(): void
    {
        $payload = [
            'email' => 'admin@kampusly.edu.ph',
            'password' => 'AdminPassword123!',
        ];

        $request = new HttpRequest('POST', '/api/v1/auth/login', [], ['Content-Type' => 'application/json'], $payload);
        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = $response->getData();

        $this->assertArrayHasKey('data', $data);
        $userObj = $data['data'];
        $this->assertSame('admin@kampusly.edu.ph', $userObj['email']);
        $this->assertSame('admin', $userObj['role']);
        $this->assertSame('active', $userObj['status']);
        $this->assertFalse(array_key_exists('password_hash', $userObj));
        $this->assertFalse(array_key_exists('passwordHash', $userObj));
        $this->assertArrayHasKey('csrf_token', $userObj);
        $this->assertSame(64, strlen($userObj['csrf_token']));

        // Session state is set
        $this->assertSame(1, $this->session->get('auth_user_id'));
    }

    public function testLoginWithWrongPasswordFailsWith401(): void
    {
        $payload = [
            'email' => 'admin@kampusly.edu.ph',
            'password' => 'IncorrectPassword999!',
        ];

        $request = new HttpRequest('POST', '/api/v1/auth/login', [], ['Content-Type' => 'application/json'], $payload);
        $response = $this->app->handle($request);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('UNAUTHENTICATED', $response->getData()['code']);
    }

    public function testLoginWithDeactivatedAccountFailsWith403(): void
    {
        $payload = [
            'email' => 'inactive@kampusly.edu.ph',
            'password' => 'StudentSantos123!',
        ];

        $request = new HttpRequest('POST', '/api/v1/auth/login', [], ['Content-Type' => 'application/json'], $payload);
        $response = $this->app->handle($request);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('FORBIDDEN', $response->getData()['code']);
    }

    public function testGetMeReturnsCurrentAuthenticatedUser(): void
    {
        // Authenticate as student
        $this->session->set('auth_user_id', 3);

        $request = new HttpRequest('GET', '/api/v1/auth/me');
        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = $response->getData();
        $userObj = $data['data'];

        $this->assertSame('santos@kampusly.edu.ph', $userObj['email']);
        $this->assertSame('student', $userObj['role']);
        $this->assertSame('BSIT-3A', $userObj['student_profile']['section_name']);
        $this->assertSame('2026-0001', $userObj['student_profile']['student_number']);
    }

    public function testUnauthenticatedMeReturns401(): void
    {
        $request = new HttpRequest('GET', '/api/v1/auth/me');
        $response = $this->app->handle($request);

        $this->assertSame(401, $response->getStatusCode());
    }

    public function testLogoutInvalidatesSession(): void
    {
        $this->session->set('auth_user_id', 1);
        $csrfToken = $this->csrfManager->getToken();

        $request = new HttpRequest(
            'POST',
            '/api/v1/auth/logout',
            [],
            ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrfToken]
        );
        $response = $this->app->handle($request);

        $this->assertSame(204, $response->getStatusCode());
        $this->assertNull($this->session->get('auth_user_id'));
    }

    public function testChangePasswordUpdatesCredentials(): void
    {
        $this->session->set('auth_user_id', 1);
        $csrfToken = $this->csrfManager->getToken();

        $payload = [
            'current_password' => 'AdminPassword123!',
            'new_password' => 'BrandNewPassword2026!',
            'new_password_confirmation' => 'BrandNewPassword2026!',
        ];

        $request = new HttpRequest(
            'POST',
            '/api/v1/auth/change-password',
            [],
            ['Content-Type' => 'application/json', 'X-CSRF-Token' => $csrfToken],
            $payload
        );
        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());

        // Verify old password fails
        $updatedUser = $this->userRepo->findById(1);
        $this->assertNotNull($updatedUser);
        $this->assertFalse($this->hasher->verify('AdminPassword123!', $updatedUser->getPasswordHash()));
        $this->assertTrue($this->hasher->verify('BrandNewPassword2026!', $updatedUser->getPasswordHash()));
    }

    public function testLoginResolvesBothWithAndWithoutApiPrefix(): void
    {
        $payload = [
            'email' => 'admin@kampusly.edu.ph',
            'password' => 'AdminPassword123!',
        ];

        $request = new HttpRequest('POST', '/auth/login', [], ['Content-Type' => 'application/json'], $payload);
        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
    }
}
