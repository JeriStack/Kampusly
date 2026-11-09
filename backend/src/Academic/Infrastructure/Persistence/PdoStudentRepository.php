<?php

declare(strict_types=1);

namespace Kampusly\Academic\Infrastructure\Persistence;

use Kampusly\Academic\Domain\Entity\StudentProfile;
use Kampusly\Academic\Domain\Repository\StudentRepositoryInterface;
use Kampusly\Shared\Application\DTO\PaginatedResult;
use Kampusly\Shared\Domain\ValueObject\PaginationQuery;
use PDO;

final class PdoStudentRepository implements StudentRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(int $id): ?StudentProfile
    {
        $sql = 'SELECT s.*, u.email, u.status, sec.section_name
                FROM `students` s
                INNER JOIN `users` u ON u.id = s.user_id
                LEFT JOIN `sections` sec ON sec.id = s.section_id
                WHERE s.id = :id
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? StudentProfile::fromDatabaseRow($row) : null;
    }

    public function findByUserId(int $userId): ?StudentProfile
    {
        $sql = 'SELECT s.*, u.email, u.status, sec.section_name
                FROM `students` s
                INNER JOIN `users` u ON u.id = s.user_id
                LEFT JOIN `sections` sec ON sec.id = s.section_id
                WHERE s.user_id = :user_id
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $row = $stmt->fetch();

        return is_array($row) ? StudentProfile::fromDatabaseRow($row) : null;
    }

    public function findByStudentNumber(string $studentNumber): ?StudentProfile
    {
        $sql = 'SELECT s.*, u.email, u.status, sec.section_name
                FROM `students` s
                INNER JOIN `users` u ON u.id = s.user_id
                LEFT JOIN `sections` sec ON sec.id = s.section_id
                WHERE s.student_number = :student_number
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':student_number' => strtoupper(trim($studentNumber))]);
        $row = $stmt->fetch();

        return is_array($row) ? StudentProfile::fromDatabaseRow($row) : null;
    }

    public function paginate(
        PaginationQuery $pagination,
        ?int $sectionId = null,
        ?string $status = null,
        ?string $search = null
    ): PaginatedResult {
        $conditions = [];
        $params = [];

        if ($sectionId !== null && $sectionId > 0) {
            $conditions[] = 's.`section_id` = :section_id';
            $params[':section_id'] = $sectionId;
        }

        if ($status !== null && $status !== '') {
            $conditions[] = 'u.`status` = :status';
            $params[':status'] = $status;
        }

        if ($search !== null && trim($search) !== '') {
            $wildcard = '%' . trim($search) . '%';
            $conditions[] = '(s.`student_number` LIKE :search_num '
                . 'OR s.`first_name` LIKE :search_fn '
                . 'OR s.`last_name` LIKE :search_ln '
                . 'OR u.`email` LIKE :search_email)';
            $params[':search_num'] = $wildcard;
            $params[':search_fn'] = $wildcard;
            $params[':search_ln'] = $wildcard;
            $params[':search_email'] = $wildcard;
        }

        $whereSql = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $countSql = "SELECT COUNT(*) FROM `students` s INNER JOIN `users` u ON u.id = s.user_id {$whereSql}";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT s.*, u.email, u.status, sec.section_name
                FROM `students` s
                INNER JOIN `users` u ON u.id = s.user_id
                LEFT JOIN `sections` sec ON sec.id = s.section_id
                {$whereSql}
                ORDER BY s.`student_number` ASC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $pagination->getPerPage(), PDO::PARAM_INT);
        $stmt->bindValue(':offset', $pagination->getOffset(), PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        $items = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $items[] = StudentProfile::fromDatabaseRow($row)->toArray();
            }
        }

        return new PaginatedResult(
            $items,
            $pagination->getPage(),
            $pagination->getPerPage(),
            $total
        );
    }

    public function create(StudentProfile $student): StudentProfile
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO `students` (`user_id`, `section_id`, `student_number`, `first_name`, `last_name`, `program`)
             VALUES (:user_id, :section_id, :student_number, :first_name, :last_name, :program)'
        );
        $stmt->execute([
            ':user_id' => $student->getUserId(),
            ':section_id' => $student->getSectionId(),
            ':student_number' => $student->getStudentNumber(),
            ':first_name' => $student->getFirstName(),
            ':last_name' => $student->getLastName(),
            ':program' => $student->getProgram(),
        ]);

        $id = (int) $this->pdo->lastInsertId();
        return $this->findById($id) ?? $student;
    }

    public function update(StudentProfile $student): StudentProfile
    {
        $stmt = $this->pdo->prepare(
            'UPDATE `students`
             SET `section_id` = :section_id,
                 `student_number` = :student_number,
                 `first_name` = :first_name,
                 `last_name` = :last_name,
                 `program` = :program
             WHERE `id` = :id'
        );
        $stmt->execute([
            ':id' => $student->getId(),
            ':section_id' => $student->getSectionId(),
            ':student_number' => $student->getStudentNumber(),
            ':first_name' => $student->getFirstName(),
            ':last_name' => $student->getLastName(),
            ':program' => $student->getProgram(),
        ]);

        return $this->findById((int) $student->getId()) ?? $student;
    }

    public function hasSchedules(int $studentId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM `schedules` sch
             INNER JOIN `students` s ON s.section_id = sch.section_id
             WHERE s.id = :id'
        );
        $stmt->execute([':id' => $studentId]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function findActiveUserIdsBySection(int $sectionId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.`user_id`
             FROM `students` s
             INNER JOIN `users` u ON u.`id` = s.`user_id`
             WHERE s.`section_id` = :section_id AND u.`status` = :active_status
             ORDER BY s.`id` ASC'
        );
        $stmt->execute([':section_id' => $sectionId, ':active_status' => 'active']);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
}
