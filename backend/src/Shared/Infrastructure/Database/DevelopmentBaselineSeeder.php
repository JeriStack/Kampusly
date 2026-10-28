<?php

declare(strict_types=1);

namespace Kampusly\Shared\Infrastructure\Database;

use Kampusly\Bootstrap\EnvLoader;
use Kampusly\Shared\Domain\Exception\ConfigurationException;
use PDO;
use Throwable;

final class DevelopmentBaselineSeeder
{
    private PDO $pdo;
    private string $environment;

    public function __construct(PDO $pdo, string $environment = 'local')
    {
        $this->pdo = $pdo;
        $this->environment = strtolower(trim($environment));
    }

    /**
     * Seed deterministic, idempotent fictional academic data for local development and tests.
     *
     * @return array<string, int> Summary of seeded entity counts
     */
    public function run(): array
    {
        if ($this->environment === 'production') {
            throw new ConfigurationException(
                'Development baseline seeder is strictly prohibited in the production environment.'
            );
        }

        $configuredSeedSecret = EnvLoader::getString('DEV_SEED_PASSWORD', '');
        $effectiveSecret = $configuredSeedSecret !== ''
            ? $configuredSeedSecret
            : bin2hex(random_bytes(16));
        $passwordHash = password_hash($effectiveSecret, PASSWORD_BCRYPT);

        $startedTransaction = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $startedTransaction = true;
        }

        try {
            $adminUserId = $this->upsertUser(
                'Clarisse Velasco',
                'admin.dev@kampusly.local',
                $passwordHash,
                'admin'
            );
            $inst1UserId = $this->upsertUser(
                'Dr. Elena Santos',
                'elena.santos@kampusly.local',
                $passwordHash,
                'instructor'
            );
            $inst2UserId = $this->upsertUser(
                'Prof. Marco Reyes',
                'marco.reyes@kampusly.local',
                $passwordHash,
                'instructor'
            );
            $stud1UserId = $this->upsertUser(
                'Aira Mendoza',
                'aira.mendoza@kampusly.local',
                $passwordHash,
                'student'
            );
            $stud2UserId = $this->upsertUser(
                'Liam Villanueva',
                'liam.villanueva@kampusly.local',
                $passwordHash,
                'student'
            );

            $secCs2AId = $this->upsertSection(
                'BSCS 2A',
                'BS Computer Science',
                2,
                '2026-2027',
                '1st'
            );
            $secIt3BId = $this->upsertSection(
                'BSIT 3B',
                'BS Information Technology',
                3,
                '2026-2027',
                '1st'
            );

            $subCs201Id = $this->upsertSubject(
                'CS 201',
                'Data Structures and Algorithms',
                'Fundamental linear and non-linear data structures and asymptotic complexity analysis.',
                3
            );
            $subCs202Id = $this->upsertSubject(
                'CS 202',
                'Database Management Systems',
                'Relational algebra, 3NF normalization, SQL, indexing, and ACID transactions.',
                3
            );
            $subIt301Id = $this->upsertSubject(
                'IT 301',
                'Systems Integration and Architecture',
                'Modular monoliths, RESTful API design, OpenAPI contracts, and enterprise integration.',
                3
            );

            $room301Id = $this->upsertRoom('RM-301', 'Room 301 Lecture Hall', 'Main Academic Building', 40);
            $room304Id = $this->upsertRoom('RM-304', 'Room 304 Lecture Hall', 'Main Academic Building', 40);
            $lab201Id = $this->upsertRoom('LAB-201', 'Computer Laboratory 201', 'Science and Technology Wing', 35);

            $inst1Id = $this->upsertInstructor(
                $inst1UserId,
                'EMP-2026-001',
                'Elena',
                'Santos',
                'Department of Computer Science'
            );
            $inst2Id = $this->upsertInstructor(
                $inst2UserId,
                'EMP-2026-002',
                'Marco',
                'Reyes',
                'Department of Information Technology'
            );

            $this->upsertStudent(
                $stud1UserId,
                $secCs2AId,
                '2025-00101',
                'Aira',
                'Mendoza',
                'BS Computer Science'
            );
            $this->upsertStudent(
                $stud2UserId,
                $secIt3BId,
                '2024-00215',
                'Liam',
                'Villanueva',
                'BS Information Technology'
            );

            $schedule1Id = $this->upsertSchedule(
                $subCs201Id,
                $inst1Id,
                $room301Id,
                $secCs2AId,
                '2026-2027',
                '1st',
                'Monday',
                '08:00:00',
                '10:00:00'
            );
            $this->upsertSchedule(
                $subCs202Id,
                $inst2Id,
                $lab201Id,
                $secCs2AId,
                '2026-2027',
                '1st',
                'Monday',
                '10:00:00',
                '12:00:00'
            );

            $this->upsertAnnouncement(
                $inst1UserId,
                $inst1Id,
                $subCs201Id,
                $secCs2AId,
                'CS 201 Week 1 Orientation & Syllabus',
                'Welcome to CS 201! Please review the course syllabus before Monday lecture.'
            );

            $noticeId = $this->upsertClassNotice(
                $schedule1Id,
                $inst1Id,
                'room_change',
                '2026-11-09',
                'Temporary Room Change to RM-304',
                'Main projector maintenance in RM-301; Monday session will meet in RM-304.',
                $room304Id,
                $adminUserId
            );

            $this->upsertNotification(
                $stud1UserId,
                'Room Change Approved: CS 201',
                'Your CS 201 class on 2026-11-09 will meet in RM-304.',
                'class_notice',
                'class_notice',
                $noticeId,
                'seed:class_notice:cs201:student1'
            );

            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }

            return [
                'users' => 5,
                'sections' => 2,
                'subjects' => 3,
                'rooms' => 3,
                'instructors' => 2,
                'students' => 2,
                'schedules' => 2,
                'announcements' => 1,
                'class_notices' => 1,
                'notifications' => 1,
            ];
        } catch (Throwable $exception) {
            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function upsertUser(string $fullName, string $email, string $passwordHash, string $role): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO `users` (`full_name`, `email`, `password_hash`, `role`, `status`, `email_verified_at`)
             VALUES (:full_name, :email, :password_hash, :role, :status, CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE
                `full_name` = VALUES(`full_name`),
                `role` = VALUES(`role`),
                `status` = VALUES(`status`)'
        );
        $stmt->execute([
            ':full_name' => $fullName,
            ':email' => $email,
            ':password_hash' => $passwordHash,
            ':role' => $role,
            ':status' => 'active',
        ]);

        return $this->fetchIdByColumn('users', 'email', $email);
    }

    private function upsertSection(
        string $sectionName,
        string $program,
        int $yearLevel,
        string $schoolYear,
        string $semester
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO `sections` (`section_name`, `program`, `year_level`, `school_year`, `semester`, `status`)
             VALUES (:section_name, :program, :year_level, :school_year, :semester, :status)
             ON DUPLICATE KEY UPDATE
                `program` = VALUES(`program`),
                `year_level` = VALUES(`year_level`),
                `status` = VALUES(`status`)'
        );
        $stmt->execute([
            ':section_name' => $sectionName,
            ':program' => $program,
            ':year_level' => $yearLevel,
            ':school_year' => $schoolYear,
            ':semester' => $semester,
            ':status' => 'active',
        ]);

        $select = $this->pdo->prepare(
            'SELECT `id` FROM `sections`
             WHERE `school_year` = :school_year AND `semester` = :semester AND `section_name` = :section_name
             LIMIT 1'
        );
        $select->execute([
            ':school_year' => $schoolYear,
            ':semester' => $semester,
            ':section_name' => $sectionName,
        ]);

        return (int) $select->fetchColumn();
    }

    private function upsertSubject(string $code, string $name, string $description, int $units): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO `subjects` (`subject_code`, `subject_name`, `description`, `units`, `status`)
             VALUES (:code, :name, :description, :units, :status)
             ON DUPLICATE KEY UPDATE
                `subject_name` = VALUES(`subject_name`),
                `description` = VALUES(`description`),
                `units` = VALUES(`units`),
                `status` = VALUES(`status`)'
        );
        $stmt->execute([
            ':code' => $code,
            ':name' => $name,
            ':description' => $description,
            ':units' => $units,
            ':status' => 'active',
        ]);

        return $this->fetchIdByColumn('subjects', 'subject_code', $code);
    }

    private function upsertRoom(string $code, string $name, string $building, int $capacity): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO `rooms` (`room_code`, `room_name`, `building`, `capacity`, `status`)
             VALUES (:code, :name, :building, :capacity, :status)
             ON DUPLICATE KEY UPDATE
                `room_name` = VALUES(`room_name`),
                `building` = VALUES(`building`),
                `capacity` = VALUES(`capacity`),
                `status` = VALUES(`status`)'
        );
        $stmt->execute([
            ':code' => $code,
            ':name' => $name,
            ':building' => $building,
            ':capacity' => $capacity,
            ':status' => 'active',
        ]);

        return $this->fetchIdByColumn('rooms', 'room_code', $code);
    }

    private function upsertInstructor(
        int $userId,
        string $employeeNumber,
        string $firstName,
        string $lastName,
        string $department
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO `instructors` (`user_id`, `employee_number`, `first_name`, `last_name`, `department`)
             VALUES (:user_id, :employee_number, :first_name, :last_name, :department)
             ON DUPLICATE KEY UPDATE
                `first_name` = VALUES(`first_name`),
                `last_name` = VALUES(`last_name`),
                `department` = VALUES(`department`)'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':employee_number' => $employeeNumber,
            ':first_name' => $firstName,
            ':last_name' => $lastName,
            ':department' => $department,
        ]);

        return $this->fetchIdByColumn('instructors', 'employee_number', $employeeNumber);
    }

    private function upsertStudent(
        int $userId,
        int $sectionId,
        string $studentNumber,
        string $firstName,
        string $lastName,
        string $program
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO `students` (`user_id`, `section_id`, `student_number`, `first_name`, `last_name`, `program`)
             VALUES (:user_id, :section_id, :student_number, :first_name, :last_name, :program)
             ON DUPLICATE KEY UPDATE
                `section_id` = VALUES(`section_id`),
                `first_name` = VALUES(`first_name`),
                `last_name` = VALUES(`last_name`),
                `program` = VALUES(`program`)'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':section_id' => $sectionId,
            ':student_number' => $studentNumber,
            ':first_name' => $firstName,
            ':last_name' => $lastName,
            ':program' => $program,
        ]);

        return $this->fetchIdByColumn('students', 'student_number', $studentNumber);
    }

    private function upsertSchedule(
        int $subjectId,
        int $instructorId,
        int $roomId,
        int $sectionId,
        string $schoolYear,
        string $semester,
        string $dayOfWeek,
        string $startTime,
        string $endTime
    ): int {
        $select = $this->pdo->prepare(
            'SELECT `id` FROM `schedules`
             WHERE `school_year` = :school_year
               AND `semester` = :semester
               AND `section_id` = :section_id
               AND `day_of_week` = :day_of_week
               AND `start_time` = :start_time
             LIMIT 1'
        );
        $select->execute([
            ':school_year' => $schoolYear,
            ':semester' => $semester,
            ':section_id' => $sectionId,
            ':day_of_week' => $dayOfWeek,
            ':start_time' => $startTime,
        ]);
        $existingId = $select->fetchColumn();
        if ($existingId !== false) {
            return (int) $existingId;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO `schedules` (
                `subject_id`, `instructor_id`, `room_id`, `section_id`,
                `school_year`, `semester`, `day_of_week`, `start_time`, `end_time`, `status`
             ) VALUES (
                :subject_id, :instructor_id, :room_id, :section_id,
                :school_year, :semester, :day_of_week, :start_time, :end_time, :status
             )'
        );
        $insert->execute([
            ':subject_id' => $subjectId,
            ':instructor_id' => $instructorId,
            ':room_id' => $roomId,
            ':section_id' => $sectionId,
            ':school_year' => $schoolYear,
            ':semester' => $semester,
            ':day_of_week' => $dayOfWeek,
            ':start_time' => $startTime,
            ':end_time' => $endTime,
            ':status' => 'published',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function upsertAnnouncement(
        int $authorUserId,
        int $instructorId,
        int $subjectId,
        int $sectionId,
        string $title,
        string $message
    ): int {
        $select = $this->pdo->prepare(
            'SELECT `id` FROM `announcements`
             WHERE `subject_id` = :subject_id AND `section_id` = :section_id AND `title` = :title
             LIMIT 1'
        );
        $select->execute([
            ':subject_id' => $subjectId,
            ':section_id' => $sectionId,
            ':title' => $title,
        ]);
        $existingId = $select->fetchColumn();
        if ($existingId !== false) {
            return (int) $existingId;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO `announcements` (
                `author_user_id`, `instructor_id`, `subject_id`, `section_id`,
                `title`, `message`, `status`, `published_at`
             ) VALUES (
                :author_user_id, :instructor_id, :subject_id, :section_id,
                :title, :message, :status, CURRENT_TIMESTAMP
             )'
        );
        $insert->execute([
            ':author_user_id' => $authorUserId,
            ':instructor_id' => $instructorId,
            ':subject_id' => $subjectId,
            ':section_id' => $sectionId,
            ':title' => $title,
            ':message' => $message,
            ':status' => 'published',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function upsertClassNotice(
        int $scheduleId,
        int $instructorId,
        string $noticeType,
        string $affectedDate,
        string $title,
        string $message,
        int $newRoomId,
        int $reviewedByUserId
    ): int {
        $select = $this->pdo->prepare(
            'SELECT `id` FROM `class_notices`
             WHERE `schedule_id` = :schedule_id AND `affected_date` = :affected_date AND `title` = :title
             LIMIT 1'
        );
        $select->execute([
            ':schedule_id' => $scheduleId,
            ':affected_date' => $affectedDate,
            ':title' => $title,
        ]);
        $existingId = $select->fetchColumn();
        if ($existingId !== false) {
            return (int) $existingId;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO `class_notices` (
                `schedule_id`, `instructor_id`, `notice_type`, `affected_date`,
                `title`, `message`, `new_room_id`, `status`, `reviewed_by_user_id`, `reviewed_at`
             ) VALUES (
                :schedule_id, :instructor_id, :notice_type, :affected_date,
                :title, :message, :new_room_id, :status, :reviewed_by_user_id, CURRENT_TIMESTAMP
             )'
        );
        $insert->execute([
            ':schedule_id' => $scheduleId,
            ':instructor_id' => $instructorId,
            ':notice_type' => $noticeType,
            ':affected_date' => $affectedDate,
            ':title' => $title,
            ':message' => $message,
            ':new_room_id' => $newRoomId,
            ':status' => 'published',
            ':reviewed_by_user_id' => $reviewedByUserId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function upsertNotification(
        int $userId,
        string $title,
        string $message,
        string $type,
        string $referenceType,
        int $referenceId,
        string $deduplicationKey
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO `notifications` (
                `user_id`, `title`, `message`, `type`,
                `reference_type`, `reference_id`, `deduplication_key`
             ) VALUES (
                :user_id, :title, :message, :type,
                :reference_type, :reference_id, :deduplication_key
             )
             ON DUPLICATE KEY UPDATE
                `title` = VALUES(`title`),
                `message` = VALUES(`message`)'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':title' => $title,
            ':message' => $message,
            ':type' => $type,
            ':reference_type' => $referenceType,
            ':reference_id' => $referenceId,
            ':deduplication_key' => $deduplicationKey,
        ]);

        $notificationId = $this->fetchIdByColumn('notifications', 'deduplication_key', $deduplicationKey);

        $deliveryStmt = $this->pdo->prepare(
            'INSERT INTO `notification_deliveries` (`notification_id`, `channel`, `status`, `attempt_count`)
             VALUES (:notification_id, :channel, :status, 0)
             ON DUPLICATE KEY UPDATE `status` = VALUES(`status`)'
        );
        $deliveryStmt->execute([
            ':notification_id' => $notificationId,
            ':channel' => 'email',
            ':status' => 'pending',
        ]);

        return $notificationId;
    }

    private function fetchIdByColumn(string $table, string $column, string $value): int
    {
        $sql = sprintf('SELECT `id` FROM `%s` WHERE `%s` = :val LIMIT 1', $table, $column);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':val' => $value]);

        return (int) $stmt->fetchColumn();
    }
}

