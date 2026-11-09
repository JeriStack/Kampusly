<?php

declare(strict_types=1);

namespace Kampusly\Announcements\Presentation\Http\Controller;

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
        $user = $request->getAttribute('user');
        if (!$user instanceof User || $user->getId() === null) {
            throw new ResourceNotFoundException('Student account was not found.');
        }
        $student = $this->students->findByUserId($user->getId());
        if ($student === null) {
            throw new ResourceNotFoundException('Student profile was not found.');
        }

        $result = $this->service->listForStudent(
            $student->getSectionId(),
            $request->getQueryParams()
        );

        return new JsonResponse($result->toArray(), 200, [], $request->getRequestId());
    }
}
