<?php

declare(strict_types=1);

namespace Kampusly\Academic\Infrastructure\Persistence;

use Kampusly\Academic\Domain\Entity\Subject;
use Kampusly\Academic\Domain\Repository\SubjectRepositoryInterface;
use Kampusly\Shared\Application\DTO\PaginatedResult;
use Kampusly\Shared\Domain\ValueObject\PaginationQuery;
use PDO;

final class PdoSubjectRepository implements SubjectRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(int $id): ?Subject
    {
        $stmt = $this->pdo->prepare('SELECT * FROM `subjects` WHERE `id` = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? Subject::fromDatabaseRow($row) : null;
    }

    public function findByCode(string $subjectCode): ?Subject
    {
        $stmt = $this->pdo->prepare('SELECT * FROM `subjects` WHERE `subject_code` = :code LIMIT 1');
        $stmt->execute([':code' => strtoupper(trim($subjectCode))]);
        $row = $stmt->fetch();

        return is_array($row) ? Subject::fromDatabaseRow($row) : null;
    }

    public function paginate(
        PaginationQuery $pagination,
        ?string $status = null,
        ?string $search = null
    ): PaginatedResult {
        $conditions = ['`archived_at` IS NULL'];
        $params = [];

        if ($status !== null && $status !== '') {
            $conditions[] = '`status` = :status';
            $params[':status'] = $status;
        }

        if ($search !== null && trim($search) !== '') {
            $conditions[] = '(`subject_code` LIKE :search OR `subject_name` LIKE :search_name)';
            $wildcard = '%' . trim($search) . '%';
            $params[':search'] = $wildcard;
            $params[':search_name'] = $wildcard;
        }

        $whereSql = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM `subjects` {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT * FROM `subjects` {$whereSql} ORDER BY `subject_code` ASC LIMIT :limit OFFSET :offset";
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
                $items[] = Subject::fromDatabaseRow($row)->toArray();
            }
        }

        return new PaginatedResult(
            $items,
            $pagination->getPage(),
            $pagination->getPerPage(),
            $total
        );
    }

    public function create(Subject $subject): Subject
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO `subjects` (`subject_code`, `subject_name`, `description`, `units`, `status`)
             VALUES (:subject_code, :subject_name, :description, :units, :status)'
        );
        $stmt->execute([
            ':subject_code' => $subject->getSubjectCode(),
            ':subject_name' => $subject->getSubjectName(),
            ':description' => $subject->getDescription(),
            ':units' => $subject->getUnits(),
            ':status' => $subject->getStatus()->value,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        return $this->findById($id) ?? $subject;
    }

    public function update(Subject $subject): Subject
    {
        $stmt = $this->pdo->prepare(
            'UPDATE `subjects`
             SET `subject_code` = :subject_code,
                 `subject_name` = :subject_name,
                 `description` = :description,
                 `units` = :units,
                 `status` = :status
             WHERE `id` = :id'
        );
        $stmt->execute([
            ':id' => $subject->getId(),
            ':subject_code' => $subject->getSubjectCode(),
            ':subject_name' => $subject->getSubjectName(),
            ':description' => $subject->getDescription(),
            ':units' => $subject->getUnits(),
            ':status' => $subject->getStatus()->value,
        ]);

        return $this->findById((int) $subject->getId()) ?? $subject;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM `subjects` WHERE `id` = :id');
        return $stmt->execute([':id' => $id]);
    }

    public function hasSchedules(int $subjectId): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM `schedules` WHERE `subject_id` = :id');
        $stmt->execute([':id' => $subjectId]);
        return ((int) $stmt->fetchColumn()) > 0;
    }
}
