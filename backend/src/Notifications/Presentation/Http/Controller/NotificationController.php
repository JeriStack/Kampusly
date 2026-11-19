<?php

declare(strict_types=1);

namespace Kampusly\Notifications\Presentation\Http\Controller;

use Kampusly\IdentityAccess\Domain\Entity\User;
use Kampusly\Notifications\Domain\Repository\NotificationRepositoryInterface;
use Kampusly\Shared\Domain\Exception\ResourceNotFoundException;
use Kampusly\Shared\Domain\ValueObject\PaginationQuery;
use Kampusly\Shared\Infrastructure\Http\HttpRequest;
use Kampusly\Shared\Infrastructure\Http\JsonResponse;
use Kampusly\Shared\Infrastructure\Http\RequestValidator;

final class NotificationController
{
    private NotificationRepositoryInterface $notifications;

    public function __construct(NotificationRepositoryInterface $notifications)
    {
        $this->notifications = $notifications;
    }

    public function listMine(HttpRequest $request): JsonResponse
    {
        $user = $this->requireUser($request);
        $query = $request->getQueryParams();
        $validator = new RequestValidator($query);
        $validator->optionalIn('unread_only', ['true', 'false'])->validateOrThrow();
        $unreadOnly = isset($query['unread_only']) && $query['unread_only'] !== ''
            ? $query['unread_only'] === 'true'
            : null;
        $result = $this->notifications->paginateForUser(
            (int) $user->getId(),
            PaginationQuery::fromQuery($query),
            $unreadOnly
        );

        $response = $result->toArray();
        $response['unread_count'] = $this->notifications->countUnreadForUser((int) $user->getId());

        return new JsonResponse($response, 200, [], $request->getRequestId());
    }

    public function markRead(HttpRequest $request, array $params): JsonResponse
    {
        $user = $this->requireUser($request);
        $id = (int) ($params['id'] ?? 0);
        if (!$this->notifications->markAsRead((int) $user->getId(), $id)) {
            throw new ResourceNotFoundException('Notification not found.');
        }

        return new JsonResponse(['message' => 'Notification marked as read.'], 200, [], $request->getRequestId());
    }

    public function markAllRead(HttpRequest $request): JsonResponse
    {
        $user = $this->requireUser($request);
        $updated = $this->notifications->markAllAsRead((int) $user->getId());

        return new JsonResponse(
            ['message' => 'Notifications marked as read.', 'updated_count' => $updated],
            200,
            [],
            $request->getRequestId()
        );
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
