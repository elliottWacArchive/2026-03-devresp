<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\Notification;
use App\Models\Message;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class NotificationController
{
    public function __construct(
    private Notification $notifications,
    private Message $messages
) {
}

    public function index(Request $request, Response $response): Response
    {
        $userId = (int) Auth::id();

        $items = $this->notifications->listForUser($userId);
        $this->notifications->markAllAsReadForUser($userId);

        ob_start();
        View::render('notifications/index', [
            'title' => 'Notifications',
            'me' => Auth::user(),
            'notifications' => $items,
            'notificationUnreadCount' => 0,
            'messageUnreadCount' => $this->messages->unreadCountForUser($userId),
        ]);
        $response->getBody()->write((string) ob_get_clean());

        return $response;
    }

    public function open(Request $request, Response $response, array $args): Response
    {
        $userId = (int) Auth::id();
        $notificationId = (int) $args['id'];

        $notification = $this->notifications->findByIdForUser($notificationId, $userId);
        if (!$notification) {
            return $response->withHeader('Location', '/notifications')->withStatus(302);
        }

        $this->notifications->markAsReadForUser($notificationId, $userId);

        return $response->withHeader('Location', (string) $notification['link'])->withStatus(302);
    }
}