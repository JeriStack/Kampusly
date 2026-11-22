<?php

declare(strict_types=1);

namespace Kampusly\Tests\Feature\Api;

use Kampusly\Bootstrap\ApplicationFactory;
use Kampusly\IdentityAccess\Application\Service\AdminArchiveService;
use Kampusly\IdentityAccess\Domain\Entity\User;
use Kampusly\IdentityAccess\Domain\Policy\AdminPolicy;
use Kampusly\IdentityAccess\Domain\Repository\AdminArchiveRepositoryInterface;
use Kampusly\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use Kampusly\IdentityAccess\Domain\Service\PasswordHasher;
use Kampusly\IdentityAccess\Domain\ValueObject\AccountStatus;
use Kampusly\IdentityAccess\Domain\ValueObject\UserRole;
use Kampusly\IdentityAccess\Presentation\Http\Controller\AdminArchiveController;
use Kampusly\Shared\Infrastructure\Http\ArraySessionManager;
use Kampusly\Shared\Infrastructure\Http\CsrfManager;
use Kampusly\Shared\Infrastructure\Http\HttpRequest;
use Kampusly\Shared\Infrastructure\Http\SessionInterface;
use Kampusly\Tests\Support\InMemoryAdminArchiveRepository;
use Kampusly\Tests\Support\InMemoryUserRepository;
use Kampusly\Tests\Support\NullAuditLogger;
use Kampusly\Tests\Support\NullTransactionManager;
use Kampusly\Tests\Support\TestCase;

final class AdminArchiveEndpointsTest extends TestCase
{
    private ApplicationFactory $app;
    private ArraySessionManager $session;
    private CsrfManager $csrfManager;
    private InMemoryAdminArchiveRepository $archiveRepo;

    protected function setUp(): void
    {
        $rootDir = dirname(__DIR__, 3);
        $this->app = ApplicationFactory::create($rootDir);
        $container = $this->app->getContainer();

        $this->session = new ArraySessionManager();
        $this->csrfManager = new CsrfManager($this->session);

        $hasher = new PasswordHasher();
        $userRepo = new InMemoryUserRepository([
            new User(
                1,
                'Admin User',
                'admin@kampusly.edu.ph',
                $hasher->hash('AdminPass123!'),
                UserRole::Admin,
                AccountStatus::Active
            ),
            new User(
                2,
                'Student User',
                'student@kampusly.edu.ph',
                $hasher->hash('StudentPass123!'),
                UserRole::Student,
                AccountStatus::Active
            ),
        ]);

        $this->archiveRepo = new InMemoryAdminArchiveRepository();
        $this->archiveRepo->seed('users', 2, [
            'title' => 'Student User',
            'subtitle' => 'student@kampusly.edu.ph',
            'badge' => 'Student',
        ]);
        $this->archiveRepo->seed('subjects', 10, [
            'title' => 'CS 201 — Data Structures',
            'subtitle' => '3 units',
            'badge' => 'CS 201',
        ]);
        $this->archiveRepo->seed('schedules', 20, [
            'title' => 'CS 201 (BSCS 2A)',
            'subtitle' => 'Monday 08:00–10:00',
            'badge' => 'RM-301',
        ]);

        $service = new AdminArchiveService(
            $this->archiveRepo,
            new AdminPolicy(),
            new NullTransactionManager(),
            new NullAuditLogger()
        );

        $container->instance(SessionInterface::class, $this->session);
        $container->instance(CsrfManager::class, $this->csrfManager);
        $container->instance(UserRepositoryInterface::class, $userRepo);
        $container->instance(AdminArchiveRepositoryInterface::class, $this->archiveRepo);
        $container->instance(AdminArchiveService::class, $service);
        $container->instance(AdminArchiveController::class, new AdminArchiveController($service));
    }

    public function testArchiveListRequiresAdminAuthentication(): void
    {
        $unauth = $this->app->handle(new HttpRequest('GET', '/api/v1/admin/archives'));
        $this->assertSame(401, $unauth->getStatusCode());

        $this->session->set('auth_user_id', 2);
        $studentRes = $this->app->handle(new HttpRequest('GET', '/api/v1/admin/archives'));
        $this->assertSame(403, $studentRes->getStatusCode());
    }

    public function testAdminCanArchiveListRestoreAndPermanentlyDeleteRecord(): void
    {
        $this->session->set('auth_user_id', 1);
        $token = $this->csrfManager->getToken();

        // Archive subject 10
        $archiveRes = $this->app->handle(
            new HttpRequest('POST', '/api/v1/admin/archives/subjects/10', [], ['X-CSRF-Token' => $token])
        );
        $this->assertSame(200, $archiveRes->getStatusCode());

        // List archives returns count = 1
        $listRes = $this->app->handle(new HttpRequest('GET', '/api/v1/admin/archives', ['type' => 'subjects']));
        $this->assertSame(200, $listRes->getStatusCode());
        $listData = $listRes->getData();
        $this->assertSame(1, $listData['meta']['total']);
        $this->assertSame(1, $listData['counts']['subjects']);

        // Restore subject 10
        $restoreRes = $this->app->handle(
            new HttpRequest('POST', '/api/v1/admin/archives/subjects/10/restore', [], ['X-CSRF-Token' => $token])
        );
        $this->assertSame(200, $restoreRes->getStatusCode());

        // Cannot permanently delete active (non-archived) record
        $deleteActiveRes = $this->app->handle(
            new HttpRequest('DELETE', '/api/v1/admin/archives/subjects/10', [], ['X-CSRF-Token' => $token])
        );
        $this->assertSame(409, $deleteActiveRes->getStatusCode());

        // Archive again, then permanently delete
        $this->app->handle(
            new HttpRequest('POST', '/api/v1/admin/archives/subjects/10', [], ['X-CSRF-Token' => $token])
        );
        $deleteRes = $this->app->handle(
            new HttpRequest('DELETE', '/api/v1/admin/archives/subjects/10', [], ['X-CSRF-Token' => $token])
        );
        $this->assertSame(200, $deleteRes->getStatusCode());
        $this->assertNull($this->archiveRepo->findRecordSummary('subjects', 10));
    }

    public function testAdminCannotArchiveSelfOrDeleteReferencedRecord(): void
    {
        $this->session->set('auth_user_id', 1);
        $token = $this->csrfManager->getToken();

        $selfArchive = $this->app->handle(
            new HttpRequest('POST', '/api/v1/admin/archives/users/1', [], ['X-CSRF-Token' => $token])
        );
        $this->assertSame(403, $selfArchive->getStatusCode());

        $this->archiveRepo->markArchived('subjects', 10);
        $this->archiveRepo->setDeleteBlockReason('subjects', 10, 'Referenced by existing class schedules.');

        $blockedDelete = $this->app->handle(
            new HttpRequest('DELETE', '/api/v1/admin/archives/subjects/10', [], ['X-CSRF-Token' => $token])
        );
        $this->assertSame(409, $blockedDelete->getStatusCode());
    }

    public function testBlockedScheduleRestoreReturns409(): void
    {
        $this->session->set('auth_user_id', 1);
        $token = $this->csrfManager->getToken();

        $this->archiveRepo->markArchived('schedules', 20);
        $this->archiveRepo->setRestoreBlockReason('schedules', 20, 'Restore subject CS 201 before restoring this schedule.');

        $res = $this->app->handle(
            new HttpRequest('POST', '/api/v1/admin/archives/schedules/20/restore', [], ['X-CSRF-Token' => $token])
        );
        $this->assertSame(409, $res->getStatusCode());
    }
}
