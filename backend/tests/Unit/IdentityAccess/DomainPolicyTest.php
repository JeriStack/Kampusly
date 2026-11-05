<?php

declare(strict_types=1);

namespace Kampusly\Tests\Unit\IdentityAccess;

use Kampusly\IdentityAccess\Domain\Entity\User;
use Kampusly\IdentityAccess\Domain\Policy\AdminPolicy;
use Kampusly\IdentityAccess\Domain\Policy\InstructorPolicy;
use Kampusly\IdentityAccess\Domain\Policy\StudentPolicy;
use Kampusly\IdentityAccess\Domain\ValueObject\AccountStatus;
use Kampusly\IdentityAccess\Domain\ValueObject\UserRole;
use Kampusly\Tests\Support\TestCase;

final class DomainPolicyTest extends TestCase
{
    private AdminPolicy $adminPolicy;
    private InstructorPolicy $instructorPolicy;
    private StudentPolicy $studentPolicy;

    protected function setUp(): void
    {
        $this->adminPolicy = new AdminPolicy();
        $this->instructorPolicy = new InstructorPolicy();
        $this->studentPolicy = new StudentPolicy();
    }

    public function testAdminPolicyGrantsAdminFullAccess(): void
    {
        $admin = new User(1, 'Admin', 'admin@kampusly.edu.ph', 'hash', UserRole::Admin, AccountStatus::Active);
        $student = new User(2, 'Student', 'student@kampusly.edu.ph', 'hash', UserRole::Student, AccountStatus::Active);

        $this->assertTrue($this->adminPolicy->canManageUsers($admin));
        $this->assertTrue($this->adminPolicy->canManageAcademicData($admin));
        $this->assertTrue($this->adminPolicy->canViewDashboardSummary($admin));

        $this->assertFalse($this->adminPolicy->canManageUsers($student));
        $this->assertFalse($this->adminPolicy->canManageAcademicData($student));
        $this->assertFalse($this->adminPolicy->canViewDashboardSummary($student));
    }

    public function testAdminPolicyPreventsSelfDeactivationLockout(): void
    {
        $admin = new User(1, 'Admin', 'admin@kampusly.edu.ph', 'hash', UserRole::Admin, AccountStatus::Active);
        $targetOther = new User(2, 'Other Admin', 'other@kampusly.edu.ph', 'hash', UserRole::Admin, AccountStatus::Active);

        // Cannot deactivate oneself
        $this->assertFalse($this->adminPolicy->canChangeUserStatus($admin, $admin));

        // Can change another user's status
        $this->assertTrue($this->adminPolicy->canChangeUserStatus($admin, $targetOther));
    }

    public function testInstructorPolicyAuthorizesOnlyAssignedTeachingSchedule(): void
    {
        $adminUser = new User(1, 'Admin', 'admin@kampusly.edu.ph', 'hash', UserRole::Admin, AccountStatus::Active);
        $instructorUser = new User(3, 'Prof. Cruz', 'cruz@kampusly.edu.ph', 'hash', UserRole::Instructor, AccountStatus::Active);
        $studentUser = new User(4, 'Student Santos', 'santos@kampusly.edu.ph', 'hash', UserRole::Student, AccountStatus::Active);

        $assignedInstructorId = 10;
        $actorInstructorId = 10;
        $otherInstructorId = 999;

        // Admin can access any instructor schedule
        $this->assertTrue($this->instructorPolicy->canAccessInstructorSchedule($adminUser, $assignedInstructorId, null));

        // Instructor can access their own schedule
        $this->assertTrue($this->instructorPolicy->canAccessInstructorSchedule($instructorUser, $assignedInstructorId, $actorInstructorId));

        // Instructor cannot access another instructor's schedule
        $this->assertFalse($this->instructorPolicy->canAccessInstructorSchedule($instructorUser, $otherInstructorId, $actorInstructorId));

        // Student cannot access instructor schedules
        $this->assertFalse($this->instructorPolicy->canAccessInstructorSchedule($studentUser, $assignedInstructorId, null));
    }

    public function testStudentPolicyAuthorizesOnlyEnrolledSectionSchedule(): void
    {
        $adminUser = new User(1, 'Admin', 'admin@kampusly.edu.ph', 'hash', UserRole::Admin, AccountStatus::Active);
        $studentUser = new User(5, 'Student Santos', 'santos@kampusly.edu.ph', 'hash', UserRole::Student, AccountStatus::Active);

        $targetSectionId = 1;
        $enrolledSectionId = 1;
        $otherSectionId = 2;

        // Admin can access any section timetable
        $this->assertTrue($this->studentPolicy->canAccessStudentTimetable($adminUser, $targetSectionId, null));

        // Student can access their own section timetable
        $this->assertTrue($this->studentPolicy->canAccessStudentTimetable($studentUser, $targetSectionId, $enrolledSectionId));

        // Student cannot access other sections
        $this->assertFalse($this->studentPolicy->canAccessStudentTimetable($studentUser, $otherSectionId, $enrolledSectionId));

        // Accessing own profile and notifications
        $this->assertTrue($this->studentPolicy->canAccessOwnProfile($studentUser, 5));
        $this->assertFalse($this->studentPolicy->canAccessOwnProfile($studentUser, 99));
        $this->assertTrue($this->studentPolicy->canAccessOwnNotifications($studentUser, 5));
        $this->assertFalse($this->studentPolicy->canAccessOwnNotifications($studentUser, 99));
    }
}
