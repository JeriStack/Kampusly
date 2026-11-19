<?php

declare(strict_types=1);

namespace Kampusly\Announcements\Presentation\Http\Controller;

use Kampusly\Academic\Domain\Entity\StudentProfile;
use Kampusly\Academic\Domain\Repository\StudentRepositoryInterface;
use Kampusly\Announcements\Application\Service\InstructorAnnouncementService;
use Kampusly\IdentityAccess\Domain\Entity\User;
use Kampusly\Shared\Domain\Exception\ResourceNotFoundException;
use Kampusly\Shared\Infrastructure\Http\HttpRequest;
use Kampusly\Shared\Infrastructure\Http\JsonResponse;

final class StudentAnnouncementController
{
    private InstructorAnnouncementService $service;
    private StudentRepositoryInterface $students;

    public function __construct(
        InstructorAnnouncementService $service,
        StudentRepositoryInterface $students
    ) {
        $this->service = $service;
        $this->students = $students;
    }

    public function listMine(HttpRequest $request): JsonResponse
    {
        $student = $this->requireStudent($request);

        $result = $this->service->listForStudent(
            (int) $student->getId(),
            $student->getSectionId(),
            $request->getQueryParams()
        );

        return new JsonResponse($result->toArray(), 200, [], $request->getRequestId());
    }

    /** @param array<string, string> $params */
    public function archive(HttpRequest $request, array $params): JsonResponse
    {
        $student = $this->requireStudent($request);
        $this->service->archiveForStudent(
            (int) $student->getId(),
            $student->getSectionId(),
            (int) ($params['id'] ?? 0)
        );

        return new JsonResponse(['message' => 'Announcement archived.'], 200, [], $request->getRequestId());
    }

    /** @param array<string, string> $params */
    public function restore(HttpRequest $request, array $params): JsonResponse
    {
        $student = $this->requireStudent($request);
        $this->service->restoreForStudent(
            (int) $student->getId(),
            $student->getSectionId(),
            (int) ($params['id'] ?? 0)
        );

        return new JsonResponse(['message' => 'Announcement restored.'], 200, [], $request->getRequestId());
    }

    private function requireStudent(HttpRequest $request): StudentProfile
    {
        $user = $request->getAttribute('user');
        if (!$user instanceof User || $user->getId() === null) {
            throw new ResourceNotFoundException('Student account was not found.');
        }
        $student = $this->students->findByUserId($user->getId());
        if ($student === null || $student->getId() === null) {
            throw new ResourceNotFoundException('Student profile was not found.');
        }

        return $student;
    }
}
