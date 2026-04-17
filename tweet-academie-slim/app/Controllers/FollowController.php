<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Models\Follow;
use App\Models\User;
use App\Models\Notification;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class FollowController
{
    public function __construct(
    private Follow $follows,
    private User $users,
    private Notification $notifications
) {
}

    public function toggle(Request $request, Response $response, array $args): Response
    {
        $viewerId = (int) Auth::id();
        $targetId = (int) $args['id'];

        $target = $this->users->findById($targetId);
        if (!$target) {
            $response->getBody()->write(json_encode(['error' => 'User not found']) ?: '{}');
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        if ($viewerId === $targetId) {
            $response->getBody()->write(json_encode(['error' => 'You cannot follow yourself']) ?: '{}');
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        if ((int) ($target['is_private'] ?? 0) === 1) {
            if ($this->follows->isFollowing($viewerId, $targetId)) {
                $following = $this->follows->togglePublicFollow($viewerId, $targetId);

                $payload = json_encode([
                    'mode' => 'following',
                    'following' => $following,
                    'pending' => false,
                    'followers' => $this->users->followersCount($targetId),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                $response->getBody()->write($payload ?: '{}');
                return $response->withHeader('Content-Type', 'application/json');
            }

            if ($this->follows->hasPendingRequest($viewerId, $targetId)) {
                $this->follows->removePendingRequest($viewerId, $targetId);

                $payload = json_encode([
                    'mode' => 'request',
                    'following' => false,
                    'pending' => false,
                    'followers' => $this->users->followersCount($targetId),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                $response->getBody()->write($payload ?: '{}');
                return $response->withHeader('Content-Type', 'application/json');
            }

            $this->follows->createFollowRequest($viewerId, $targetId);

            $payload = json_encode([
                'mode' => 'request',
                'following' => false,
                'pending' => true,
                'followers' => $this->users->followersCount($targetId),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $response->getBody()->write($payload ?: '{}');
            return $response->withHeader('Content-Type', 'application/json');
        }

        $following = $this->follows->togglePublicFollow($viewerId, $targetId);

        if ($following) {
    $this->notifications->create(
        $targetId,
        $viewerId,
        'follow'
    );
}

        $payload = json_encode([
            'mode' => 'following',
            'following' => $following,
            'pending' => false,
            'followers' => $this->users->followersCount($targetId),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $response->getBody()->write($payload ?: '{}');
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function requests(Request $request, Response $response): Response
    {
        $payload = json_encode(
            $this->follows->pendingRequestsForUser((int) Auth::id()),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        $response->getBody()->write($payload ?: '[]');
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function accept(Request $request, Response $response, array $args): Response
    {
        $accepted = $this->follows->acceptRequest((int) $args['id'], (int) Auth::id());

        if ($accepted) {
    $requestData = $this->follows->findRequestByIdForOwner((int) $args['id'], (int) Auth::id());
    if ($requestData) {
        $this->notifications->create(
            (int) Auth::id(),
            (int) $requestData['requester_user_id'],
            'follow'
        );
    }
}

        $payload = json_encode([
            'accepted' => $accepted,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $response->getBody()->write($payload ?: '{}');
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function reject(Request $request, Response $response, array $args): Response
    {
        $rejected = $this->follows->rejectRequest((int) $args['id'], (int) Auth::id());

        $payload = json_encode([
            'rejected' => $rejected,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $response->getBody()->write($payload ?: '{}');
        return $response->withHeader('Content-Type', 'application/json');
    }
}