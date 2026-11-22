<?php

declare(strict_types=1);

namespace Kampusly\IdentityAccess\Infrastructure\Persistence;

use Kampusly\IdentityAccess\Domain\Entity\User;
use Kampusly\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use Kampusly\IdentityAccess\Domain\ValueObject\AccountStatus;
use Kampusly\Shared\Application\DTO\PaginatedResult;
use Kampusly\Shared\Domain\ValueObject\PaginationQuery;
use PDO;

final class PdoUserRepository implements UserRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(int $id): ?User
    {
        $stmt = $this->pdo->prepare('SELECT * FROM `users` WHERE `id` = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? User::fromDatabaseRow($row) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->pdo->prepare('SELECT * FROM `users` WHERE `email` = :email LIMIT 1');
        $stmt->execute([':email' => strtolower(trim($email))]);
        $row = $stmt->fetch();

        return is_array($row) ? User::fromDatabaseRow($row) : null;
    }

    public function paginate(
        PaginationQuery $pagination,
        ?string $role = null,
        ?string $status = null,
        ?string $search = null
    ): PaginatedResult {
        $conditions = ['`archived_at` IS NULL'];
        $params = [];

        if ($role !== null && $role !== '') {
            $conditions[] = '`role` = :role';
            $params[':role'] = $role;
        }

        if ($status !== null && $status !== '') {
            $conditions[] = '`status` = :status';
            $params[':status'] = $status;
        }

        if ($search !== null && trim($search) !== '') {
            $conditions[] = '(`full_name` LIKE :search OR `email` LIKE :search_email)';
            $wildcard = '%' . trim($search) . '%';
            $params[':search'] = $wildcard;
            $params[':search_email'] = $wildcard;
        }

        $whereSql = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM `users` {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT * FROM `users` {$whereSql} ORDER BY `full_name` ASC LIMIT :limit OFFSET :offset";
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
                $items[] = User::fromDatabaseRow($row)->toArray();
            }
        }

        return new PaginatedResult(
            $items,
            $pagination->getPage(),
            $pagination->getPerPage(),
            $total
        );
    }

    public function create(User $user): User
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO `users` (`full_name`, `email`, `password_hash`, `role`, `status`)
             VALUES (:full_name, :email, :password_hash, :role, :status)'
        );
        $stmt->execute([
            ':full_name' => $user->getFullName(),
            ':email' => $user->getEmail(),
            ':password_hash' => $user->getPasswordHash(),
            ':role' => $user->getRole()->value,
            ':status' => $user->getStatus()->value,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        return $this->findById($id) ?? $user;
    }

    public function updatePassword(int $userId, string $newPasswordHash): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE `users` SET `password_hash` = :hash, `updated_at` = CURRENT_TIMESTAMP WHERE `id` = :id'
        );
        $stmt->execute([
            ':hash' => $newPasswordHash,
            ':id' => $userId,
        ]);
    }

    public function updateStatus(int $userId, AccountStatus $status): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE `users` SET `status` = :status, `updated_at` = CURRENT_TIMESTAMP WHERE `id` = :id'
        );
        $stmt->execute([
            ':status' => $status->value,
            ':id' => $userId,
        ]);
    }

    public function updateProfile(int $userId, string $fullName, string $email): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE `users` SET `full_name` = :name, `email` = :email, `updated_at` = CURRENT_TIMESTAMP WHERE `id` = :id'
        );
        $stmt->execute([
            ':name' => trim($fullName),
            ':email' => strtolower(trim($email)),
            ':id' => $userId,
        ]);
    }

    public function updateLastLogin(int $userId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE `users` SET `last_login_at` = CURRENT_TIMESTAMP WHERE `id` = :id'
        );
        $stmt->execute([':id' => $userId]);
    }

    public function findStudentProfile(int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.`id`, s.`student_number`, s.`section_id`, sec.`section_name`, s.`program`
             FROM `students` s
             LEFT JOIN `sections` sec ON sec.`id` = s.`section_id`
             WHERE s.`user_id` = :user_id
             LIMIT 1'
        );
        $stmt->execute([':user_id' => $userId]);
        $row = $stmt->fetch();

        if (!is_array($row)) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'student_number' => (string) $row['student_number'],
            'section_id' => (int) $row['section_id'],
            'section_name' => (string) ($row['section_name'] ?? ''),
            'program' => (string) $row['program'],
        ];
    }

    public function findInstructorProfile(int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT `id`, `employee_number`, `department`
             FROM `instructors`
             WHERE `user_id` = :user_id
             LIMIT 1'
        );
        $stmt->execute([':user_id' => $userId]);
        $row = $stmt->fetch();

        if (!is_array($row)) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'employee_number' => (string) $row['employee_number'],
            'department' => (string) $row['department'],
        ];
    }
}
