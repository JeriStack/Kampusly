<?php

declare(strict_types=1);

namespace Kampusly\Academic\Presentation\Http\Controller;

use Kampusly\Academic\Application\Service\StudentManagementService;
use Kampusly\IdentityAccess\Domain\Entity\User;
use Kampusly\Shared\Infrastructure\Http\HttpRequest;
use Kampusly\Shared\Infrastructure\Http\JsonResponse;

final class StudentController
{
    private StudentManagementService $studentService;

    public function __construct(StudentManagementService $studentService)
    {
        $this->studentService = $studentService;
    }

    public function listStudents(HttpRequest $request): JsonResponse
    {
        $result = $this->studentService->listStudents($request->getQueryParams());

        return new JsonResponse(
            $result->toArray(),
            200,
            [],
            $request->getRequestId()
        );
    }

    /**
     * @param array<string, string> $params
     */
    public function getStudent(HttpRequest $request, array $params): JsonResponse
    {
        $id = (int) ($params['id'] ?? 0);
        $student = $this->studentService->getStudent($id);

        return new JsonResponse(
            ['data' => $student],
            200,
            [],
            $request->getRequestId()
        );
    }

    public function createStudent(HttpRequest $request): JsonResponse
    {
        /** @var User|null $actor */
        $actor = $request->getAttribute('user');
        $created = $this->studentService->createStudent(
            $request->getParsedBody(),
            $actor?->getId(),
            $request->getRequestId()
        );

        return new JsonResponse(
            [
                'message' => 'Student enrolled successfully.',
                'data' => $created,
            ],
            201,
            [],
            $request->getRequestId()
        );
    }

    /**
     * @param array<string, string> $params
     */
    public function updateStudent(HttpRequest $request, array $params): JsonResponse
    {
        /** @var User|null $actor */
        $actor = $request->getAttribute('user');
        $id = (int) ($params['id'] ?? 0);
        $updated = $this->studentService->updateStudent(
            $id,
            $request->getParsedBody(),
            $actor?->getId(),
            $request->getRequestId()
        );

        return new JsonResponse(
            [
                'message' => 'Student record updated successfully.',
                'data' => $updated,
            ],
            200,
            [],
            $request->getRequestId()
        );
    }

    /**
     * @param array<string, string> $params
     */
    public function updateStatus(HttpRequest $request, array $params): JsonResponse
    {
        /** @var User|null $actor */
        $actor = $request->getAttribute('user');
        $id = (int) ($params['id'] ?? 0);
        $body = $request->getParsedBody();
        $status = (string) ($body['status'] ?? '');

        $updated = $this->studentService->updateStatus(
            $id,
            $status,
            $actor?->getId(),
            $request->getRequestId()
        );

        return new JsonResponse(
            [
                'message' => 'Student status updated successfully.',
                'data' => $updated,
            ],
            200,
            [],
            $request->getRequestId()
        );
    }
}
