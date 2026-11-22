<?php

declare(strict_types=1);

namespace Kampusly\IdentityAccess\Presentation\Http\Controller;

use Kampusly\IdentityAccess\Application\Service\AdminArchiveService;
use Kampusly\IdentityAccess\Domain\Entity\User;
use Kampusly\Shared\Domain\Exception\UnauthorizedException;
use Kampusly\Shared\Infrastructure\Http\HttpRequest;
use Kampusly\Shared\Infrastructure\Http\JsonResponse;

final class AdminArchiveController
{
    private AdminArchiveService $archiveService;

    public function __construct(AdminArchiveService $archiveService)
    {
        $this->archiveService = $archiveService;
    }

    public function listArchived(HttpRequest $request): JsonResponse
    {
        $user = $this->requireUser($request);
        $payload = $this->archiveService->listArchived($request->getQueryParams(), $user);

        return new JsonResponse($payload, 200, [], $request->getRequestId());
    }

    /**
     * @param array<string, string> $params
     */
    public function archive(HttpRequest $request, array $params): JsonResponse
    {
        $user = $this->requireUser($request);
        $type = (string) ($params['type'] ?? '');
        $id = (int) ($params['id'] ?? 0);

        $record = $this->archiveService->archiveRecord($type, $id, $user, $request->getRequestId());

        return new JsonResponse(
            [
                'message' => 'Record moved to archives.',
                'data' => $record,
            ],
            200,
            [],
            $request->getRequestId()
        );
    }

    /**
     * @param array<string, string> $params
     */
    public function restore(HttpRequest $request, array $params): JsonResponse
    {
        $user = $this->requireUser($request);
        $type = (string) ($params['type'] ?? '');
        $id = (int) ($params['id'] ?? 0);

        $record = $this->archiveService->restoreRecord($type, $id, $user, $request->getRequestId());

        return new JsonResponse(
            [
                'message' => 'Record restored from archives.',
                'data' => $record,
            ],
            200,
            [],
            $request->getRequestId()
        );
    }

    /**
     * @param array<string, string> $params
     */
    public function permanentlyDelete(HttpRequest $request, array $params): JsonResponse
    {
        $user = $this->requireUser($request);
        $type = (string) ($params['type'] ?? '');
        $id = (int) ($params['id'] ?? 0);

        $this->archiveService->permanentlyDeleteRecord($type, $id, $user, $request->getRequestId());

        return new JsonResponse(
            ['message' => 'Archived record permanently deleted.'],
            200,
            [],
            $request->getRequestId()
        );
    }

    private function requireUser(HttpRequest $request): User
    {
        $user = $request->getAttribute('user');
        if (!$user instanceof User) {
            throw new UnauthorizedException();
        }

        return $user;
    }
}
