<?php

declare(strict_types=1);

namespace Kampusly\Academic\Presentation\Http\Controller;

use Kampusly\Academic\Application\Service\AcademicCatalogService;
use Kampusly\Shared\Infrastructure\Http\HttpRequest;
use Kampusly\Shared\Infrastructure\Http\JsonResponse;

final class AcademicCatalogController
{
    private AcademicCatalogService $catalogService;

    public function __construct(AcademicCatalogService $catalogService)
    {
        $this->catalogService = $catalogService;
    }

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
}

