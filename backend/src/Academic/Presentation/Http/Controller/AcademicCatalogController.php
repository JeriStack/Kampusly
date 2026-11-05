<?php

declare(strict_types=1);

namespace Kampusly\Academic\Presentation\Http\Controller;

use Kampusly\Academic\Application\Service\AcademicCatalogService;
use Kampusly\IdentityAccess\Domain\Entity\User;
use Kampusly\Shared\Infrastructure\Http\HttpRequest;
use Kampusly\Shared\Infrastructure\Http\JsonResponse;

final class AcademicCatalogController
{
    private AcademicCatalogService $catalogService;

    public function __construct(AcademicCatalogService $catalogService)
    {
        $this->catalogService = $catalogService;
    }

    // -------------------------------------------------------------------------
    // Subjects
    // -------------------------------------------------------------------------

    public function listSubjects(HttpRequest $request): JsonResponse
    {
        $result = $this->catalogService->listSubjects($request->getQueryParams());

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
    public function getSubject(HttpRequest $request, array $params): JsonResponse
    {
        $id = (int) ($params['id'] ?? 0);
        $subject = $this->catalogService->getSubject($id);

        return new JsonResponse(
            ['data' => $subject],
            200,
            [],
            $request->getRequestId()
        );
    }

    public function createSubject(HttpRequest $request): JsonResponse
    {
        /** @var User|null $actor */
        $actor = $request->getAttribute('user');
        $created = $this->catalogService->createSubject(
            $request->getParsedBody(),
            $actor?->getId(),
            $request->getRequestId()
        );

        return new JsonResponse(
            [
                'message' => 'Subject created successfully.',
                'data' => $created->toArray(),
            ],
            201,
            [],
            $request->getRequestId()
        );
    }

    /**
     * @param array<string, string> $params
     */
    public function updateSubject(HttpRequest $request, array $params): JsonResponse
    {
        /** @var User|null $actor */
        $actor = $request->getAttribute('user');
        $id = (int) ($params['id'] ?? 0);
        $updated = $this->catalogService->updateSubject(
            $id,
            $request->getParsedBody(),
            $actor?->getId(),
            $request->getRequestId()
        );

        return new JsonResponse(
            [
                'message' => 'Subject updated successfully.',
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
    public function deleteSubject(HttpRequest $request, array $params): JsonResponse
    {
        /** @var User|null $actor */
        $actor = $request->getAttribute('user');
        $id = (int) ($params['id'] ?? 0);
        $this->catalogService->deleteSubject(
            $id,
            $actor?->getId(),
            $request->getRequestId()
        );

        return new JsonResponse(
            ['message' => 'Subject deleted successfully.'],
            200,
            [],
            $request->getRequestId()
        );
    }

    // -------------------------------------------------------------------------
    // Rooms
    // -------------------------------------------------------------------------

    public function listRooms(HttpRequest $request): JsonResponse
    {
        $result = $this->catalogService->listRooms($request->getQueryParams());

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
    public function getRoom(HttpRequest $request, array $params): JsonResponse
    {
        $id = (int) ($params['id'] ?? 0);
        $room = $this->catalogService->getRoom($id);

        return new JsonResponse(
            ['data' => $room],
            200,
            [],
            $request->getRequestId()
        );
    }

    public function createRoom(HttpRequest $request): JsonResponse
    {
        /** @var User|null $actor */
        $actor = $request->getAttribute('user');
        $created = $this->catalogService->createRoom(
            $request->getParsedBody(),
            $actor?->getId(),
            $request->getRequestId()
        );

        return new JsonResponse(
            [
                'message' => 'Room created successfully.',
                'data' => $created->toArray(),
            ],
            201,
            [],
            $request->getRequestId()
        );
    }

    /**
     * @param array<string, string> $params
     */
    public function updateRoom(HttpRequest $request, array $params): JsonResponse
    {
        /** @var User|null $actor */
        $actor = $request->getAttribute('user');
        $id = (int) ($params['id'] ?? 0);
        $updated = $this->catalogService->updateRoom(
            $id,
            $request->getParsedBody(),
            $actor?->getId(),
            $request->getRequestId()
        );

        return new JsonResponse(
            [
                'message' => 'Room updated successfully.',
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
    public function deleteRoom(HttpRequest $request, array $params): JsonResponse
    {
        /** @var User|null $actor */
        $actor = $request->getAttribute('user');
        $id = (int) ($params['id'] ?? 0);
        $this->catalogService->deleteRoom(
            $id,
            $actor?->getId(),
            $request->getRequestId()
        );

        return new JsonResponse(
            ['message' => 'Room deleted successfully.'],
            200,
            [],
            $request->getRequestId()
        );
    }

    // -------------------------------------------------------------------------
    // Sections
    // -------------------------------------------------------------------------

    public function listSections(HttpRequest $request): JsonResponse
    {
        $result = $this->catalogService->listSections($request->getQueryParams());

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
    public function getSection(HttpRequest $request, array $params): JsonResponse
    {
        $id = (int) ($params['id'] ?? 0);
        $section = $this->catalogService->getSection($id);

        return new JsonResponse(
            ['data' => $section],
            200,
            [],
            $request->getRequestId()
        );
    }

    public function createSection(HttpRequest $request): JsonResponse
    {
        /** @var User|null $actor */
        $actor = $request->getAttribute('user');
        $created = $this->catalogService->createSection(
            $request->getParsedBody(),
            $actor?->getId(),
            $request->getRequestId()
        );

        return new JsonResponse(
            [
                'message' => 'Section created successfully.',
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
    public function updateSection(HttpRequest $request, array $params): JsonResponse
    {
        /** @var User|null $actor */
        $actor = $request->getAttribute('user');
        $id = (int) ($params['id'] ?? 0);
        $updated = $this->catalogService->updateSection(
            $id,
            $request->getParsedBody(),
            $actor?->getId(),
            $request->getRequestId()
        );

        return new JsonResponse(
            [
                'message' => 'Section updated successfully.',
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
    public function deleteSection(HttpRequest $request, array $params): JsonResponse
    {
        /** @var User|null $actor */
        $actor = $request->getAttribute('user');
        $id = (int) ($params['id'] ?? 0);
        $this->catalogService->deleteSection(
            $id,
            $actor?->getId(),
            $request->getRequestId()
        );

        return new JsonResponse(
            ['message' => 'Section deleted successfully.'],
            200,
            [],
            $request->getRequestId()
        );
    }

    /**
     * @param array<string, string> $params
     */
    public function listSectionStudents(HttpRequest $request, array $params): JsonResponse
    {
        $id = (int) ($params['id'] ?? 0);
        $students = $this->catalogService->listSectionStudents($id);

        return new JsonResponse(
            [
                'data' => $students,
                'total' => count($students),
            ],
            200,
            [],
            $request->getRequestId()
        );
    }
}
