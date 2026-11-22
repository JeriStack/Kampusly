<?php

declare(strict_types=1);

namespace Kampusly\IdentityAccess\Presentation\Http\Controller;

use Kampusly\IdentityAccess\Domain\Entity\User;
use Kampusly\IdentityAccess\Domain\Policy\AdminPolicy;
use Kampusly\Shared\Domain\Exception\ForbiddenException;
use Kampusly\Shared\Domain\Exception\UnauthorizedException;
use Kampusly\Shared\Infrastructure\Http\HttpRequest;
use Kampusly\Shared\Infrastructure\Http\JsonResponse;
use PDO;

final class AdminDashboardController
{
    private PDO $pdo;
    private AdminPolicy $adminPolicy;

    public function __construct(PDO $pdo, AdminPolicy $adminPolicy)
    {
        $this->pdo = $pdo;
        $this->adminPolicy = $adminPolicy;
    }

    public function dashboardSummary(HttpRequest $request): JsonResponse
    {
        $user = $request->getAttribute('user');
        if (!$user instanceof User) {
            throw new UnauthorizedException();
        }

        if (!$this->adminPolicy->canViewDashboardSummary($user)) {
            throw new ForbiddenException();
        }

        $totalStudents = $this->fetchSqlCount(
            'SELECT COUNT(*) FROM `students` s INNER JOIN `users` u ON u.`id` = s.`user_id` WHERE u.`archived_at` IS NULL'
        );
        $totalInstructors = $this->fetchSqlCount(
            'SELECT COUNT(*) FROM `instructors` i INNER JOIN `users` u ON u.`id` = i.`user_id` WHERE u.`archived_at` IS NULL'
        );
        $totalSubjects = $this->fetchCount('subjects', "`status` = 'active' AND `archived_at` IS NULL");
        $totalRooms = $this->fetchCount('rooms', "`status` = 'active' AND `archived_at` IS NULL");

        $dayOfWeek = date('l');
        $todaysClasses = $this->fetchCount(
            'schedules',
            "`status` = 'published' AND `archived_at` IS NULL AND `day_of_week` = " . $this->pdo->quote($dayOfWeek)
        );

        $upcomingClasses = $this->fetchCount(
            'schedules',
            "`status` = 'published' AND `archived_at` IS NULL"
        );

        $activeAnnouncements = (int) $this->fetchCount(
            'announcements',
            "`status` = 'published'"
        );

        $notices = (int) $this->fetchCount(
            'class_notices',
            "`status` = 'pending_approval' OR `notice_type` = 'cancelled'"
        );

        return new JsonResponse(
            [
                'data' => [
                    'total_students' => $totalStudents,
                    'total_instructors' => $totalInstructors,
                    'total_subjects' => $totalSubjects,
                    'total_rooms' => $totalRooms,
                    'todays_classes' => $todaysClasses,
                    'upcoming_classes' => $upcomingClasses,
                    'active_announcements' => $activeAnnouncements,
                    'cancelled_or_pending_notices' => $notices,
                ],
            ],
            200,
            [],
            $request->getRequestId()
        );
    }

    private function fetchCount(string $table, string $where = '1=1'): int
    {
        return $this->fetchSqlCount("SELECT COUNT(*) FROM `{$table}` WHERE {$where}");
    }

    private function fetchSqlCount(string $sql): int
    {
        try {
            $stmt = $this->pdo->query($sql);
            return $stmt !== false ? (int) $stmt->fetchColumn() : 0;
        } catch (\Throwable) {
            return 0;
        }
    }
}

