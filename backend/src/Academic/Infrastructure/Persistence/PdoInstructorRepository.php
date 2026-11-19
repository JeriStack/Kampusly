<?php

declare(strict_types=1);

namespace Kampusly\Academic\Infrastructure\Persistence;

use Kampusly\Academic\Domain\Entity\InstructorProfile;
use Kampusly\Academic\Domain\Repository\InstructorRepositoryInterface;
use Kampusly\Shared\Application\DTO\PaginatedResult;
use Kampusly\Shared\Domain\ValueObject\PaginationQuery;
use PDO;

final class PdoInstructorRepository implements InstructorRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(int $id): ?InstructorProfile
    {
        $sql = 'SELECT i.*, u.email, u.status
                FROM `instructors` i
                INNER JOIN `users` u ON u.id = i.user_id
                WHERE i.id = :id
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? InstructorProfile::fromDatabaseRow($row) : null;
    }

    public function findByUserId(int $userId): ?InstructorProfile
    {
        $sql = 'SELECT i.*, u.email, u.status
                FROM `instructors` i
                INNER JOIN `users` u ON u.id = i.user_id
                WHERE i.user_id = :user_id
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $row = $stmt->fetch();

        return is_array($row) ? InstructorProfile::fromDatabaseRow($row) : null;
    }

    public function findByEmployeeNumber(string $employeeNumber): ?InstructorProfile
    {
        $sql = 'SELECT i.*, u.email, u.status
                FROM `instructors` i
                INNER JOIN `users` u ON u.id = i.user_id
                WHERE i.employee_number = :employee_number
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':employee_number' => strtoupper(trim($employeeNumber))]);
        $row = $stmt->fetch();

        return is_array($row) ? InstructorProfile::fromDatabaseRow($row) : null;
    }

    public function paginate(
        PaginationQuery $pagination,
        ?string $department = null,
        ?string $status = null,
        ?string $search = null
    ): PaginatedResult {
        $conditions = [];
        $params = [];

        if ($department !== null && trim($department) !== '') {
            $conditions[] = 'i.`department` = :department';
            $params[':department'] = trim($department);
        }

        if ($status !== null && $status !== '') {
            $conditions[] = 'u.`status` = :status';
            $params[':status'] = $status;
        }

        if ($search !== null && trim($search) !== '') {
            $wildcard = '%' . trim($search) . '%';
            $conditions[] = '(i.`employee_number` LIKE :search_emp '
                . 'OR i.`first_name` LIKE :search_fn '
                . 'OR i.`last_name` LIKE :search_ln '
                . 'OR u.`email` LIKE :search_email)';
            $params[':search_emp'] = $wildcard;
            $params[':search_fn'] = $wildcard;
            $params[':search_ln'] = $wildcard;
            $params[':search_email'] = $wildcard;
        }

        $whereSql = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $countSql = "SELECT COUNT(*) FROM `instructors` i INNER JOIN `users` u ON u.id = i.user_id {$whereSql}";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT i.*, u.email, u.status
                FROM `instructors` i
                INNER JOIN `users` u ON u.id = i.user_id
                {$whereSql}
                ORDER BY i.`employee_number` ASC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $pagination->getPerPage(), PDO::PARAM_INT);
        $stmt->bindValue(':offset', $pagination->getOffset(), PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        $items = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $items[] = InstructorProfile::fromDatabaseRow($row)->toArray();
            }
        }

        return new PaginatedResult(
            $items,
            $pagination->getPage(),
            $pagination->getPerPage(),
            $total
        );
    }

    public function create(InstructorProfile $instructor): InstructorProfile
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO `instructors` (`user_id`, `employee_number`, `first_name`, `last_name`, `department`)
             VALUES (:user_id, :employee_number, :first_name, :last_name, :department)'
        );
        $stmt->execute([
            ':user_id' => $instructor->getUserId(),
            ':employee_number' => $instructor->getEmployeeNumber(),
            ':first_name' => $instructor->getFirstName(),
            ':last_name' => $instructor->getLastName(),
            ':department' => $instructor->getDepartment(),
        ]);

        $id = (int) $this->pdo->lastInsertId();
        return $this->findById($id) ?? $instructor;
    }

    public function update(InstructorProfile $instructor): InstructorProfile
    {
        $stmt = $this->pdo->prepare(
            'UPDATE `instructors`
             SET `employee_number` = :employee_number,
                 `first_name` = :first_name,
                 `last_name` = :last_name,
                 `department` = :department
             WHERE `id` = :id'
        );
        $stmt->execute([
            ':id' => $instructor->getId(),
            ':employee_number' => $instructor->getEmployeeNumber(),
            ':first_name' => $instructor->getFirstName(),
            ':last_name' => $instructor->getLastName(),
            ':department' => $instructor->getDepartment(),
        ]);

        return $this->findById((int) $instructor->getId()) ?? $instructor;
    }

    public function hasSchedules(int $instructorId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM `schedules` WHERE `instructor_id` = :id'
        );
        $stmt->execute([':id' => $instructorId]);
        return ((int) $stmt->fetchColumn()) > 0;
    }
}
