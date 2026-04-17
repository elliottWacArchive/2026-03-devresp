<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\Notification;
use App\Models\Tweet;
use App\Models\User;
use App\Models\Message;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class HomeController
{
    public function __construct(
    private Tweet $tweets,
    private User $users,
    private Notification $notifications,
    private Message $messages
) {
}

    public function index(Request $request, Response $response): Response
    {
        $userId = (int) Auth::id();
        $timeline = $userId ? $this->tweets->timelineForUser($userId) : [];

        ob_start();
        View::render('home/index', [
            'title' => 'Accueil',
            'timeline' => $timeline,
            'me' => Auth::user(),
            'notificationUnreadCount' => $this->notifications->unreadCountForUser($userId),
            'messageUnreadCount' => $this->messages->unreadCountForUser($userId),
        ]);
        $response->getBody()->write((string) ob_get_clean());
        return $response;
    }

    public function hashtag(Request $request, Response $response, array $args): Response
    {
        $userId = (int) Auth::id();
        $tag = $args['tag'] ?? '';
        $tweets = $this->tweets->byHashtag((string) $tag, $userId);

        ob_start();
        View::render('home/hashtag', [
            'title' => '#' . $tag,
            'tag' => $tag,
            'tweets' => $tweets,
            'me' => Auth::user(),
            'notificationUnreadCount' => $this->notifications->unreadCountForUser($userId),
            'messageUnreadCount' => $this->messages->unreadCountForUser((int) Auth::id()),
        ]);
        $response->getBody()->write((string) ob_get_clean());
        return $response;
    }

    public function searchHashtags(Request $request, Response $response): Response
    {
        $q = ltrim(trim((string) ($request->getQueryParams()['q'] ?? '')), '#');

        if ($q === '') {
            $response->getBody()->write('[]');
            return $response->withHeader('Content-Type', 'application/json');
        }

        $hashtags = $this->tweets->searchHashtagsByPrefix($q);

        $payload = json_encode($hashtags, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $response->getBody()->write($payload ?: '[]');

        return $response->withHeader('Content-Type', 'application/json');
    }
}