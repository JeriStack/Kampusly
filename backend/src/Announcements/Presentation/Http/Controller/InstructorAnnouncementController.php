<?php

declare(strict_types=1);

namespace Kampusly\Announcements\Presentation\Http\Controller;

use Kampusly\Announcements\Application\Service\InstructorAnnouncementService;
use Kampusly\IdentityAccess\Domain\Entity\User;
use Kampusly\Shared\Domain\Exception\ResourceNotFoundException;
use Kampusly\Shared\Infrastructure\Http\HttpRequest;
use Kampusly\Shared\Infrastructure\Http\JsonResponse;

final class InstructorAnnouncementController
{
    private InstructorAnnouncementService $service;

    public function __construct(InstructorAnnouncementService $service)
    {
        $this->service = $service;
    }

    public function listMine(HttpRequest $request): JsonResponse
    {
        $result = $this->service->listForInstructor(
            $this->requireUser($request),
            $request->getQueryParams()
        );

        return new JsonResponse($result->toArray(), 200, [], $request->getRequestId());
    }

    public function create(HttpRequest $request): JsonResponse
    {
        $announcement = $this->service->create(
            $this->requireUser($request),
            $request->getParsedBody(),
            $request->getRequestId()
        );

        return new JsonResponse(
            ['message' => 'Announcement published.', 'data' => $announcement->toArray()],
            201,
            [],
            $request->getRequestId()
        );
    }

    /** @param array<string, string> $params */
    public function update(HttpRequest $request, array $params): JsonResponse
    {
        $announcement = $this->service->update(
            (int) ($params['id'] ?? 0),
            $this->requireUser($request),
            $request->getParsedBody(),
            $request->getRequestId()
        );

        return new JsonResponse(
            ['message' => 'Announcement updated.', 'data' => $announcement->toArray()],
            200,
            [],
            $request->getRequestId()
        );
    }

    /** @param array<string, string> $params */
    public function delete(HttpRequest $request, array $params): JsonResponse
    {
        $this->service->withdraw(
            (int) ($params['id'] ?? 0),
            $this->requireUser($request),
            $request->getRequestId()
        );

        return new JsonResponse(['message' => 'Announcement withdrawn.'], 200, [], $request->getRequestId());
    }

    private function requireUser(HttpRequest $request): User
    {
        $user = $request->getAttribute('user');
        if (!$user instanceof User || $user->getId() === null) {
            throw new ResourceNotFoundException('User account was not found.');
        }

        return $user;
    }
}
