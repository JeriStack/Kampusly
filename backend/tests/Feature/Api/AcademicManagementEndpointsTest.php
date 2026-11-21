<?php

declare(strict_types=1);

namespace Kampusly\Tests\Feature\Api;

use Kampusly\Academic\Application\Service\AcademicCatalogService;
use Kampusly\Academic\Application\Service\InstructorManagementService;
use Kampusly\Academic\Application\Service\StudentManagementService;
use Kampusly\Academic\Domain\Entity\InstructorProfile;
use Kampusly\Academic\Domain\Entity\Room;
use Kampusly\Academic\Domain\Entity\Section;
use Kampusly\Academic\Domain\Entity\StudentProfile;
use Kampusly\Academic\Domain\Entity\Subject;
use Kampusly\Academic\Domain\Repository\InstructorRepositoryInterface;
use Kampusly\Academic\Domain\Repository\RoomRepositoryInterface;
use Kampusly\Academic\Domain\Repository\SectionRepositoryInterface;
use Kampusly\Academic\Domain\Repository\StudentRepositoryInterface;
use Kampusly\Academic\Domain\Repository\SubjectRepositoryInterface;
use Kampusly\Academic\Domain\ValueObject\ReferenceStatus;
use Kampusly\Academic\Domain\ValueObject\Semester;
use Kampusly\Academic\Presentation\Http\Controller\AcademicCatalogController;
use Kampusly\Academic\Presentation\Http\Controller\InstructorController;
use Kampusly\Academic\Presentation\Http\Controller\StudentController;
use Kampusly\Bootstrap\ApplicationFactory;
use Kampusly\IdentityAccess\Domain\Entity\User;
use Kampusly\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use Kampusly\IdentityAccess\Domain\Service\PasswordHasher;
use Kampusly\IdentityAccess\Domain\ValueObject\AccountStatus;
use Kampusly\IdentityAccess\Domain\ValueObject\UserRole;
use Kampusly\Shared\Application\Contract\AuditLoggerInterface;
use Kampusly\Shared\Application\Contract\TransactionManagerInterface;
use Kampusly\Shared\Infrastructure\Http\ArraySessionManager;
use Kampusly\Shared\Infrastructure\Http\CsrfManager;
use Kampusly\Shared\Infrastructure\Http\HttpRequest;
use Kampusly\Shared\Infrastructure\Http\SessionInterface;
use Kampusly\Tests\Support\InMemoryInstructorRepository;
use Kampusly\Tests\Support\InMemoryRoomRepository;
use Kampusly\Tests\Support\InMemorySectionRepository;
use Kampusly\Tests\Support\InMemoryStudentRepository;
use Kampusly\Tests\Support\InMemorySubjectRepository;
use Kampusly\Tests\Support\InMemoryUserRepository;
use Kampusly\Tests\Support\NullAuditLogger;
use Kampusly\Tests\Support\NullTransactionManager;
use Kampusly\Tests\Support\TestCase;

final class AcademicManagementEndpointsTest extends TestCase
{
    private ApplicationFactory $app;
    private InMemoryUserRepository $userRepo;
    private InMemorySubjectRepository $subjectRepo;
    private InMemoryRoomRepository $roomRepo;
    private InMemorySectionRepository $sectionRepo;
    private InMemoryStudentRepository $studentRepo;
    private InMemoryInstructorRepository $instructorRepo;
    private ArraySessionManager $session;
    private CsrfManager $csrfManager;

    protected function setUp(): void
    {
        $this->app = ApplicationFactory::create(dirname(__DIR__, 3));
        $container = $this->app->getContainer();

        $hasher = new PasswordHasher();
        $this->session = new ArraySessionManager();
        $this->csrfManager = new CsrfManager($this->session);

        $adminHash = $hasher->hash('AdminPassword123!');
        $studentHash = $hasher->hash('StudentSantos123!');

        $this->userRepo = new InMemoryUserRepository([
            new User(1, 'Administrator', 'admin@kampusly.edu.ph', $adminHash, UserRole::Admin, AccountStatus::Active),
            new User(2, 'Maria Santos', 'santos@kampusly.edu.ph', $studentHash, UserRole::Student, AccountStatus::Active),
        ]);

        $this->subjectRepo = new InMemorySubjectRepository([
            new Subject(1, 'CS 201', 'Data Structures and Algorithms', 'Linear and non-linear structures', 3, ReferenceStatus::Active),
            new Subject(2, 'CS 202', 'Database Systems', 'Relational design and SQL', 3, ReferenceStatus::Active),
        ]);

        $this->roomRepo = new InMemoryRoomRepository([
            new Room(1, 'RM-301', 'Room 301 Lecture Hall', 'Main Academic Building', 40, ReferenceStatus::Active),
            new Room(2, 'LAB-201', 'Computer Lab 201', 'Tech Wing', 35, ReferenceStatus::Active),
        ]);

        $this->sectionRepo = new InMemorySectionRepository([
            new Section(1, 'BSCS 2A', 'BS Computer Science', 2, '2026-2027', Semester::First, ReferenceStatus::Active),
            new Section(2, 'BSIT 3B', 'BS Information Technology', 3, '2026-2027', Semester::First, ReferenceStatus::Active),
        ]);

        $this->studentRepo = new InMemoryStudentRepository([
            new StudentProfile(
                1,
                2,
                1,
                '2026-0001',
                'Maria',
                'Santos',
                'BS Computer Science',
                'santos@kampusly.edu.ph',
                'active',
                'BSCS 2A'
            ),
        ]);

        $this->instructorRepo = new InMemoryInstructorRepository();

        $nullAudit = new NullAuditLogger();
        $nullTx = new NullTransactionManager();

        $container->instance(SessionInterface::class, $this->session);
        $container->instance(CsrfManager::class, $this->csrfManager);
        $container->instance(UserRepositoryInterface::class, $this->userRepo);
        $container->instance(SubjectRepositoryInterface::class, $this->subjectRepo);
        $container->instance(RoomRepositoryInterface::class, $this->roomRepo);
        $container->instance(SectionRepositoryInterface::class, $this->sectionRepo);
        $container->instance(StudentRepositoryInterface::class, $this->studentRepo);
        $container->instance(InstructorRepositoryInterface::class, $this->instructorRepo);
        $container->instance(AuditLoggerInterface::class, $nullAudit);
        $container->instance(TransactionManagerInterface::class, $nullTx);

        $catalogService = new AcademicCatalogService(
            $this->subjectRepo,
            $this->roomRepo,
            $this->sectionRepo,
            $nullTx,
            $nullAudit
        );

        $studentService = new StudentManagementService(
            $this->studentRepo,
            $this->sectionRepo,
            $this->userRepo,
            $hasher,
            $nullTx,
            $nullAudit
        );

        $instructorService = new InstructorManagementService(
            $this->instructorRepo,
            $this->userRepo,
            $hasher,
            $nullTx,
            $nullAudit
        );

        $container->instance(AcademicCatalogService::class, $catalogService);
        $container->instance(AcademicCatalogController::class, new AcademicCatalogController($catalogService));
        $container->instance(StudentManagementService::class, $studentService);
        $container->instance(StudentController::class, new StudentController($studentService));
        $container->instance(InstructorManagementService::class, $instructorService);
        $container->instance(InstructorController::class, new InstructorController($instructorService));
    }

    public function testListSubjectsReturns200WithPagination(): void
    {
        $this->session->set('auth_user_id', 1);

        $request = new HttpRequest('GET', '/api/v1/subjects', ['per_page' => 10]);
        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = $response->getData();
        $this->assertArrayHasKey('data', $data);
        $this->assertSame(2, count($data['data']));
        $this->assertSame(2, $data['meta']['total']);
    }

    public function testAcademicCatalogRequiresAuthentication(): void
    {
        foreach (['/api/v1/subjects', '/api/v1/rooms', '/api/v1/sections'] as $path) {
            $response = $this->app->handle(new HttpRequest('GET', $path));
            $this->assertSame(401, $response->getStatusCode(), "Expected 401 for unauthenticated GET {$path}");
        }
    }

    public function testStudentCannotEnumerateStudentsInstructorsOrSectionRosters(): void
    {
        $this->session->set('auth_user_id', 2); // Student role

        foreach (['/api/v1/students', '/api/v1/instructors', '/api/v1/sections/1/students'] as $path) {
            $response = $this->app->handle(new HttpRequest('GET', $path));
            $this->assertSame(403, $response->getStatusCode(), "Expected 403 for student GET {$path}");
        }
    }

    public function testAdminCanCreateSubject(): void
    {
        $this->session->set('auth_user_id', 1);
        $token = $this->csrfManager->getToken();

        $request = new HttpRequest(
            'POST',
            '/api/v1/subjects',
            [],
            ['X-CSRF-Token' => $token],
            [
                'subject_code' => 'CS 301',
                'subject_name' => 'Software Engineering',
                'units' => 3,
                'description' => 'Agile and modular development',
            ]
        );

        $response = $this->app->handle($request);
        $this->assertSame(201, $response->getStatusCode());
        $data = $response->getData();
        $this->assertSame('CS 301', $data['data']['subject_code']);
    }

    public function testDuplicateSubjectCodeReturns409(): void
    {
        $this->session->set('auth_user_id', 1);
        $token = $this->csrfManager->getToken();

        $request = new HttpRequest(
            'POST',
            '/api/v1/subjects',
            [],
            ['X-CSRF-Token' => $token],
            [
                'subject_code' => 'CS 201', // already exists
                'subject_name' => 'Duplicate Subject',
                'units' => 3,
            ]
        );

        $response = $this->app->handle($request);
        $this->assertSame(409, $response->getStatusCode());
    }

    public function testAdminCanUpdateSubject(): void
    {
        $this->session->set('auth_user_id', 1);
        $token = $this->csrfManager->getToken();

        $request = new HttpRequest(
            'PUT',
            '/api/v1/subjects/1',
            [],
            ['X-CSRF-Token' => $token],
            [
                'subject_code' => 'CS 201-ADV',
                'subject_name' => 'Advanced Data Structures',
                'units' => 4,
            ]
        );

        $response = $this->app->handle($request);
        $this->assertSame(200, $response->getStatusCode());
        $data = $response->getData();
        $this->assertSame('CS 201-ADV', $data['data']['subject_code']);
    }

    public function testCannotDeleteSubjectReferencedBySchedules(): void
    {
        $this->session->set('auth_user_id', 1);
        $token = $this->csrfManager->getToken();
        $this->subjectRepo->setHasSchedules(1, true);

        $request = new HttpRequest('DELETE', '/api/v1/subjects/1', [], ['X-CSRF-Token' => $token]);
        $response = $this->app->handle($request);

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('SUBJECT_HAS_SCHEDULES', $response->getData()['code'] ?? '');
    }

    public function testAdminCanDeleteSubjectWithoutSchedules(): void
    {
        $this->session->set('auth_user_id', 1);
        $token = $this->csrfManager->getToken();

        $request = new HttpRequest('DELETE', '/api/v1/subjects/2', [], ['X-CSRF-Token' => $token]);
        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNull($this->subjectRepo->findById(2));
    }

    public function testRoomsCrudAndReferentialIntegrity(): void
    {
        $this->session->set('auth_user_id', 1);
        $token = $this->csrfManager->getToken();

        // Create Room
        $createReq = new HttpRequest(
            'POST',
            '/api/v1/rooms',
            [],
            ['X-CSRF-Token' => $token],
            [
                'room_code' => 'RM-401',
                'room_name' => 'Room 401 Studio',
                'building' => 'Arts Hall',
                'capacity' => 50,
            ]
        );
        $createRes = $this->app->handle($createReq);
        $this->assertSame(201, $createRes->getStatusCode());

        // Update Room
        $updateReq = new HttpRequest(
            'PUT',
            '/api/v1/rooms/1',
            [],
            ['X-CSRF-Token' => $token],
            [
                'room_code' => 'RM-301',
                'room_name' => 'Room 301 Main Amphitheater',
                'building' => 'Main Academic Building',
                'capacity' => 75,
            ]
        );
        $updateRes = $this->app->handle($updateReq);
        $this->assertSame(200, $updateRes->getStatusCode());
        $this->assertSame(75, $updateRes->getData()['data']['capacity']);

        // Delete Room with schedules -> 409
        $this->roomRepo->setHasSchedules(1, true);
        $deleteReq = new HttpRequest('DELETE', '/api/v1/rooms/1', [], ['X-CSRF-Token' => $token]);
        $deleteRes = $this->app->handle($deleteReq);
        $this->assertSame(409, $deleteRes->getStatusCode());
    }

    public function testSectionsCrudAndStudentEnrollment(): void
    {
        $this->session->set('auth_user_id', 1);
        $token = $this->csrfManager->getToken();

        // Create Section
        $createReq = new HttpRequest(
            'POST',
            '/api/v1/sections',
            [],
            ['X-CSRF-Token' => $token],
            [
                'section_name' => 'BSCS 3A',
                'program' => 'BS Computer Science',
                'year_level' => 3,
                'school_year' => '2026-2027',
                'semester' => '1st',
            ]
        );
        $createRes = $this->app->handle($createReq);
        $this->assertSame(201, $createRes->getStatusCode());

        // Section Details & Students
        $this->sectionRepo->addEnrolledStudent(1, [
            'id' => 1,
            'student_number' => '2026-0001',
            'full_name' => 'Maria Santos',
            'email' => 'santos@kampusly.edu.ph',
        ]);

        $getReq = new HttpRequest('GET', '/api/v1/sections/1/students');
        $getRes = $this->app->handle($getReq);
        $this->assertSame(200, $getRes->getStatusCode());
        $this->assertSame(1, $getRes->getData()['total']);
    }

    public function testAdminCanCreateAndListStudents(): void
    {
        $this->session->set('auth_user_id', 1);
        $token = $this->csrfManager->getToken();

        $createReq = new HttpRequest(
            'POST',
            '/api/v1/students',
            [],
            ['X-CSRF-Token' => $token],
            [
                'student_number' => '2026-0002',
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'email' => 'juan.delacruz@kampusly.edu.ph',
                'password' => 'StudentPassword123!',
                'program' => 'BS Computer Science',
                'section_id' => 1,
            ]
        );

        $createRes = $this->app->handle($createReq);
        $this->assertSame(201, $createRes->getStatusCode());
        $this->assertSame('2026-0002', $createRes->getData()['data']['student_number']);

        // List Students
        $listReq = new HttpRequest('GET', '/api/v1/students', ['q' => 'Dela Cruz']);
        $listRes = $this->app->handle($listReq);
        $this->assertSame(200, $listRes->getStatusCode());
        $this->assertSame(1, $listRes->getData()['meta']['total']);

        // Status update
        $patchReq = new HttpRequest(
            'PATCH',
            '/api/v1/students/1/status',
            [],
            ['X-CSRF-Token' => $token],
            ['status' => 'inactive']
        );
        $patchRes = $this->app->handle($patchReq);
        $this->assertSame(200, $patchRes->getStatusCode());
    }

    public function testAdminCanCreateAndListInstructors(): void
    {
        $this->session->set('auth_user_id', 1);
        $token = $this->csrfManager->getToken();

        $createReq = new HttpRequest(
            'POST',
            '/api/v1/instructors',
            [],
            ['X-CSRF-Token' => $token],
            [
                'employee_number' => 'EMP-2026-005',
                'first_name' => 'Elena',
                'last_name' => 'Reyes',
                'email' => 'elena.reyes@kampusly.edu.ph',
                'password' => 'InstructorPassword123!',
                'department' => 'College of Computer Studies',
            ]
        );

        $createRes = $this->app->handle($createReq);
        $this->assertSame(201, $createRes->getStatusCode());
        $this->assertSame('EMP-2026-005', $createRes->getData()['data']['employee_number']);

        // Update Instructor
        $updateReq = new HttpRequest(
            'PUT',
            '/api/v1/instructors/1',
            [],
            ['X-CSRF-Token' => $token],
            [
                'employee_number' => 'EMP-2026-005',
                'first_name' => 'Dr. Elena',
                'last_name' => 'Reyes-Santos',
                'email' => 'elena.reyes@kampusly.edu.ph',
                'department' => 'College of Engineering',
            ]
        );
        $updateRes = $this->app->handle($updateReq);
        $this->assertSame(200, $updateRes->getStatusCode());
        $this->assertSame('College of Engineering', $updateRes->getData()['data']['department']);
    }

    public function testNonAdminCannotMutateAcademicEntities(): void
    {
        $this->session->set('auth_user_id', 2); // Student role
        $token = $this->csrfManager->getToken();

        $createReq = new HttpRequest(
            'POST',
            '/api/v1/subjects',
            [],
            ['X-CSRF-Token' => $token],
            ['subject_code' => 'CS 999', 'subject_name' => 'Hacking 101', 'units' => 3]
        );

        $response = $this->app->handle($createReq);
        $this->assertSame(403, $response->getStatusCode());
    }
}
