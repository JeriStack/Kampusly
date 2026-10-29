<?php

declare(strict_types=1);

namespace Kampusly\Academic\Presentation\Http\Controller;

use Kampusly\Academic\Application\Service\InstructorManagementService;
use Kampusly\IdentityAccess\Domain\Entity\User;
use Kampusly\Shared\Infrastructure\Http\HttpRequest;
use Kampusly\Shared\Infrastructure\Http\JsonResponse;

final class InstructorController
{
    private InstructorManagementService $instructorService;

    public function __construct(InstructorManagementService $instructorService)
    {
        $this->instructorService = $instructorService;
    }

    public function listInstructors(HttpRequest $request): JsonResponse
    {
        $result = $this->instructorService->listInstructors($request->getQueryParams());

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
    public function getInstructor(HttpRequest $request, array $params): JsonResponse
    {
        $id = (int) ($params['id'] ?? 0);
        $instructor = $this->instructorService->getInstructor($id);

        return new JsonResponse(
            ['data' => $instructor],
            200,
            [],
            $request->getRequestId()
        );
    }

    public function createInstructor(HttpRequest $request): JsonResponse
    {
        /** @var User|null $actor */
        $actor = $request->getAttribute('user');
        $created = $this->instructorService->createInstructor(
            $request->getParsedBody(),
            $actor?->getId(),
            $request->getRequestId()
        );

        return new JsonResponse(
            [
                'message' => 'Instructor registered successfully.',
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
    public function updateInstructor(HttpRequest $request, array $params): JsonResponse
    {
        /** @var User|null $actor */
        $actor = $request->getAttribute('user');
        $id = (int) ($params['id'] ?? 0);
        $updated = $this->instructorService->updateInstructor(
            $id,
            $request->getParsedBody(),
            $actor?->getId(),
            $request->getRequestId()
        );

        return new JsonResponse(
            [
                'message' => 'Instructor record updated successfully.',
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

        $updated = $this->instructorService->updateStatus(
            $id,
            $status,
            $actor?->getId(),
            $request->getRequestId()
        );

        return new JsonResponse(
            [
                'message' => 'Instructor status updated successfully.',
                'data' => $updated,
            ],
            200,
            [],
            $request->getRequestId()
        );
    }
}
