<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\FollowController;
use App\Controllers\HomeController;
use App\Controllers\MessageController;
use App\Controllers\NotificationController;
use App\Controllers\ProfileController;
use App\Controllers\ShortUrlController;
use App\Controllers\TweetController;
use App\Middleware\AuthMiddleware;
use Psr\Container\ContainerInterface;
use Slim\App;

return function (App $app, ContainerInterface $container): void {
    $app->get('/login', [AuthController::class, 'showLogin']);
    $app->post('/login', [AuthController::class, 'login']);
    $app->get('/register', [AuthController::class, 'showRegister']);
    $app->post('/register', [AuthController::class, 'register']);

    $app->get('/img/{code}', [ShortUrlController::class, 'openImage']);

    $app->group('', function ($group) {
        $group->get('/', [HomeController::class, 'index']);
        $group->post('/logout', [AuthController::class, 'logout']);

        $group->get('/notifications', [NotificationController::class, 'index']);
        $group->get('/notifications/{id}/open', [NotificationController::class, 'open']);
        $group->get('/tweet/{id}', [TweetController::class, 'show']);

        $group->post('/tweets', [TweetController::class, 'store']);
        $group->post('/tweets/drafts', [TweetController::class, 'saveDraft']);
        $group->post('/tweets/drafts/{id}/publish', [TweetController::class, 'publishDraft']);
        $group->post('/tweets/drafts/{id}/delete', [TweetController::class, 'deleteDraft']);

        $group->post('/tweets/{id}/like', [TweetController::class, 'like']);
        $group->post('/tweets/{id}/repost', [TweetController::class, 'repost']);
        $group->post('/tweets/{id}/delete', [TweetController::class, 'delete']);
        $group->post('/tweets/{id}/edit', [TweetController::class, 'edit']);
        $group->post('/tweets/{id}/delete-original', [TweetController::class, 'deleteOriginal']);

        $group->get('/api/timeline', [TweetController::class, 'timelineJson']);
        $group->get('/api/users/search', [MessageController::class, 'searchUsers']);
        $group->get('/api/hashtags/search', [HomeController::class, 'searchHashtags']);

        $group->get('/profile/edit', [ProfileController::class, 'edit']);
        $group->post('/profile/edit', [ProfileController::class, 'update']);
        $group->get('/profile/drafts', [ProfileController::class, 'drafts']);
        $group->get('/profile/search', [ProfileController::class, 'search']);
        $group->get('/profile/{username}/followers', [ProfileController::class, 'followers']);
        $group->get('/profile/{username}/following', [ProfileController::class, 'following']);
        $group->get('/profile/{username}', [ProfileController::class, 'show']);

        $group->post('/follow/{id}', [FollowController::class, 'toggle']);
        $group->get('/follow/requests', [FollowController::class, 'requests']);
        $group->post('/follow/requests/{id}/accept', [FollowController::class, 'accept']);
        $group->post('/follow/requests/{id}/reject', [FollowController::class, 'reject']);

        $group->get('/messages', [MessageController::class, 'index']);
        $group->post('/messages', [MessageController::class, 'store']);
        $group->post('/messages/group/create', [MessageController::class, 'createGroup']);

        $group->post('/messages/{id}/edit', [MessageController::class, 'edit']);
        $group->post('/messages/{id}/delete', [MessageController::class, 'deleteMessage']);
        $group->post('/messages/{id}/delete-all', [MessageController::class, 'deleteForEveryone']);

        $group->post('/messages/conversation/{id}/image', [MessageController::class, 'updateConversationImage']);
        $group->post('/messages/conversation/{id}/rename', [MessageController::class, 'renameConversation']);
        $group->post('/messages/conversation/{id}/delete', [MessageController::class, 'deleteConversationForMe']);
        $group->post('/messages/conversation/{id}/delete-all', [MessageController::class, 'deleteConversationForEveryone']);
        $group->post('/messages/conversation/{id}/add-member', [MessageController::class, 'addMember']);
        $group->post('/messages/conversation/{id}/remove-member/{userId}', [MessageController::class, 'removeMember']);

        $group->post('/messages/requests/{id}/accept', [MessageController::class, 'acceptRequest']);
        $group->post('/messages/requests/{id}/reject', [MessageController::class, 'rejectRequest']);

        $group->post('/messages/block/{username}', [MessageController::class, 'block']);
        $group->post('/messages/unblock/{username}', [MessageController::class, 'unblock']);

        $group->get('/hashtag/{tag}', [HomeController::class, 'hashtag']);
    })->add(AuthMiddleware::class);
};