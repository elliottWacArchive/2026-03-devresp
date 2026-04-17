<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\Follow;
use App\Models\Message;
use App\Models\Notification;
use App\Models\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class MessageController
{
    public function __construct(
        private Message $messages,
        private User $users,
        private Follow $follows,
        private Notification $notifications
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        $viewerId = (int) Auth::id();
        $conversationId = (int) ($request->getQueryParams()['conversation'] ?? 0);
        $withUsername = trim((string) ($request->getQueryParams()['with'] ?? ''));

        $conversations = $this->messages->conversationListForUser($viewerId);
        $requests = $this->messages->requestConversationsForUser($viewerId);

        $activeConversation = null;
        $messages = [];
        $canMessage = false;
        $isBlocked = false;
        $directTarget = null;
        $participants = [];

        if ($conversationId > 0) {
            $activeConversation = $this->messages->findConversationById($conversationId, $viewerId);

            if ($activeConversation) {
    $this->messages->markConversationAsReadForUser($conversationId, $viewerId);
    $messages = $this->messages->messagesForConversation($conversationId, $viewerId);
    $participants = $this->messages->getConversationParticipantsIncludingViewer((int) $activeConversation['id']);

    if ((int) $activeConversation['is_group'] === 0 && !empty($activeConversation['direct_username'])) {
        $directTarget = $this->users->findByUsername((string) $activeConversation['direct_username']);

        if ($directTarget) {
            $isBlocked = $this->messages->isBlockedEitherWay($viewerId, (int) $directTarget['id']);

            if ((string) ($activeConversation['status'] ?? 'accepted') === 'accepted') {
                $canMessage = !$isBlocked;
            } else {
                $mode = $this->messages->directMessageMode($viewerId, $directTarget, $this->follows);
                $canMessage = !$isBlocked && $mode !== 'denied';
            }
        }
    } else {
        $canMessage = true;
    }
}
        } elseif ($withUsername !== '') {
            $directTarget = $this->users->findByUsername(ltrim($withUsername, '@'));

            if ($directTarget) {
                $isBlocked = $this->messages->isBlockedEitherWay($viewerId, (int) $directTarget['id']);
                $mode = $this->messages->directMessageMode($viewerId, $directTarget, $this->follows);
                $canMessage = !$isBlocked && $mode !== 'denied';

                $existingConversation = $this->messages->findExistingDirectConversation($viewerId, (int) $directTarget['id']);
                if ($existingConversation) {
                    return $response
                        ->withHeader('Location', '/messages?conversation=' . (int) $existingConversation['id'])
                        ->withStatus(302);
                }
            }
        }

        ob_start();
        View::render('messages/index', [
            'title' => 'Messages',
            'me' => Auth::user(),
            'conversations' => $conversations,
            'requests' => $requests,
            'activeConversation' => $activeConversation,
            'messagesList' => $messages,
            'canMessage' => $canMessage,
            'isBlocked' => $isBlocked,
            'directTarget' => $directTarget,
            'participants' => $participants,
            'notificationUnreadCount' => $this->notifications->unreadCountForUser($viewerId),
            'messageUnreadCount' => $this->messages->unreadCountForUser($viewerId),
        ]);
        $response->getBody()->write((string) ob_get_clean());
        return $response;
    }

    public function store(Request $request, Response $response): Response
    {
        $viewerId = (int) Auth::id();
        $data = (array) $request->getParsedBody();
        $files = $request->getUploadedFiles();

        $conversationId = (int) ($data['conversation_id'] ?? 0);
        $toUsername = ltrim(trim((string) ($data['to_username'] ?? '')), '@');
        $text = trim((string) ($data['message'] ?? ''));

        $imagePath = null;
        if (isset($files['image']) && $files['image']->getError() === UPLOAD_ERR_OK) {
            $imagePath = $this->storeUploadedImage($files['image'], 'dm_');
        }

        if ($text === '' && !$imagePath) {
            return $response->withHeader('Location', $request->getHeaderLine('Referer') ?: '/messages')->withStatus(302);
        }

        if ($conversationId > 0) {
            if (!$this->messages->userIsConversationParticipant($viewerId, $conversationId)) {
                return $response->withHeader('Location', '/messages')->withStatus(302);
            }

            $conversation = $this->messages->findConversationById($conversationId, $viewerId);
            if (!$conversation) {
                return $response->withHeader('Location', '/messages')->withStatus(302);
            }

            if (($conversation['status'] ?? 'accepted') !== 'accepted') {
                return $response->withHeader('Location', '/messages?conversation=' . $conversationId)->withStatus(302);
            }

            if ((int) $conversation['is_group'] === 0 && !empty($conversation['direct_username'])) {
                $other = $this->users->findByUsername((string) $conversation['direct_username']);
                if (!$other || $this->messages->isBlockedEitherWay($viewerId, (int) $other['id'])) {
                    return $response->withHeader('Location', '/messages?conversation=' . $conversationId)->withStatus(302);
                }
            }

            $this->messages->sendToConversation($conversationId, $viewerId, $text, $imagePath);

            return $response->withHeader('Location', '/messages?conversation=' . $conversationId)->withStatus(302);
        }

        if ($toUsername === '') {
            return $response->withHeader('Location', '/messages')->withStatus(302);
        }

        $to = $this->users->findByUsername($toUsername);
        if (!$to) {
            $_SESSION['flash_error'] = 'Destinataire introuvable.';
            return $response->withHeader('Location', '/messages')->withStatus(302);
        }

        $mode = $this->messages->directMessageMode($viewerId, $to, $this->follows);

        if ($mode === 'denied') {
            $_SESSION['flash_error'] = 'This user does not allow direct messages from you.';
            return $response->withHeader('Location', '/messages')->withStatus(302);
        }

        $existingConversation = $this->messages->findExistingDirectConversation($viewerId, (int) $to['id']);

        if ($existingConversation) {
            $conversationId = (int) $existingConversation['id'];
        } else {
            $conversationId = $this->messages->createDirectConversationWithStatus(
                $viewerId,
                (int) $to['id'],
                $mode
            );
        }

        $this->messages->sendToConversation($conversationId, $viewerId, $text, $imagePath);

        return $response->withHeader('Location', '/messages?conversation=' . $conversationId)->withStatus(302);
    }

    public function createGroup(Request $request, Response $response): Response
    {
        $viewerId = (int) Auth::id();
        $data = (array) $request->getParsedBody();

        $groupName = trim((string) ($data['group_name'] ?? ''));
        $usernamesRaw = trim((string) ($data['participant_usernames'] ?? ''));

        if ($usernamesRaw === '') {
            return $response->withHeader('Location', '/messages')->withStatus(302);
        }

        $usernames = array_map('trim', explode(',', $usernamesRaw));

        foreach ($usernames as &$username) {
            $username = ltrim($username, '@');
        }
        unset($username);

        $usernames = array_values(array_unique(array_filter($usernames)));

        $participantIds = [];

        foreach ($usernames as $username) {
            $target = $this->users->findByUsername($username);
            if (!$target) {
                continue;
            }

            $mode = $this->messages->directMessageMode($viewerId, $target, $this->follows);
            if ($mode === 'denied') {
                continue;
            }

            $participantIds[] = (int) $target['id'];
        }

        if (!$participantIds) {
            return $response->withHeader('Location', '/messages')->withStatus(302);
        }

        $conversationId = $this->messages->createGroupConversation(
            $viewerId,
            $participantIds,
            $groupName !== '' ? $groupName : null
        );

        return $response->withHeader('Location', '/messages?conversation=' . $conversationId)->withStatus(302);
    }

    public function edit(Request $request, Response $response, array $args): Response
    {
        $data = (array) $request->getParsedBody();
        $text = trim((string) ($data['message'] ?? ''));

        if ($text !== '') {
            $this->messages->editOwned((int) $args['id'], (int) Auth::id(), $text);
        }

        return $response->withHeader('Location', $request->getHeaderLine('Referer') ?: '/messages')->withStatus(302);
    }

    public function deleteMessage(Request $request, Response $response, array $args): Response
    {
        $viewerId = (int) Auth::id();
        $messageId = (int) $args['id'];

        $this->messages->deleteOwnedMessage($messageId, $viewerId);

        return $response->withHeader('Location', $request->getHeaderLine('Referer') ?: '/messages')->withStatus(302);
    }

    public function deleteForEveryone(Request $request, Response $response, array $args): Response
    {
        $viewerId = (int) Auth::id();
        $messageId = (int) $args['id'];

        $this->messages->deleteForEveryone($messageId, $viewerId);

        return $response->withHeader('Location', $request->getHeaderLine('Referer') ?: '/messages')->withStatus(302);
    }

    public function updateConversationImage(Request $request, Response $response, array $args): Response
    {
        $viewerId = (int) Auth::id();
        $conversationId = (int) $args['id'];
        $files = $request->getUploadedFiles();

        if (isset($files['image']) && $files['image']->getError() === UPLOAD_ERR_OK) {
            $imagePath = $this->storeUploadedImage($files['image'], 'conversation_');
            $this->messages->updateConversationImage($conversationId, $viewerId, $imagePath);
        }

        return $response->withHeader('Location', '/messages?conversation=' . $conversationId)->withStatus(302);
    }

    public function renameConversation(Request $request, Response $response, array $args): Response
    {
        $viewerId = (int) Auth::id();
        $conversationId = (int) $args['id'];
        $data = (array) $request->getParsedBody();
        $name = trim((string) ($data['name'] ?? ''));

        if ($name !== '') {
            $this->messages->renameConversation($conversationId, $viewerId, $name);
        }

        return $response->withHeader('Location', '/messages?conversation=' . $conversationId)->withStatus(302);
    }

    public function deleteConversationForEveryone(Request $request, Response $response, array $args): Response
    {
        $viewerId = (int) Auth::id();
        $conversationId = (int) $args['id'];

        $this->messages->deleteConversationForEveryone($conversationId, $viewerId);

        return $response->withHeader('Location', '/messages')->withStatus(302);
    }

    public function block(Request $request, Response $response, array $args): Response
    {
        $viewerId = (int) Auth::id();
        $other = $this->users->findByUsername((string) $args['username']);

        if ($other) {
            $otherId = (int) $other['id'];
            $this->messages->blockUser($viewerId, $otherId);
            $this->follows->removeBothDirections($viewerId, $otherId);
        }

        return $response->withHeader('Location', '/messages')->withStatus(302);
    }

    public function unblock(Request $request, Response $response, array $args): Response
    {
        $viewerId = (int) Auth::id();
        $other = $this->users->findByUsername((string) $args['username']);

        if ($other) {
            $this->messages->unblockUser($viewerId, (int) $other['id']);
        }

        return $response->withHeader('Location', '/messages')->withStatus(302);
    }

    private function storeUploadedImage(object $uploaded, string $prefix): string
    {
        $ext = pathinfo((string) $uploaded->getClientFilename(), PATHINFO_EXTENSION);
        $filename = uniqid($prefix, true) . '.' . strtolower($ext ?: 'jpg');
        $target = __DIR__ . '/../../public/assets/uploads/' . $filename;

        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0777, true);
        }

        $uploaded->moveTo($target);

        return '/assets/uploads/' . $filename;
    }

    public function deleteConversationForMe(Request $request, Response $response, array $args): Response
    {
        $viewerId = (int) Auth::id();
        $conversationId = (int) $args['id'];

        $this->messages->deleteConversationForMe($conversationId, $viewerId);

        return $response->withHeader('Location', '/messages')->withStatus(302);
    }

    public function addMember(Request $request, Response $response, array $args): Response
    {
        $viewerId = (int) Auth::id();
        $conversationId = (int) $args['id'];
        $data = (array) $request->getParsedBody();

        $username = ltrim(trim((string) ($data['username'] ?? '')), '@');
        if ($username === '') {
            return $response->withHeader('Location', '/messages?conversation=' . $conversationId)->withStatus(302);
        }

        $user = $this->users->findByUsername($username);
        if ($user) {
            $mode = $this->messages->directMessageMode($viewerId, $user, $this->follows);
            if ($mode !== 'denied') {
                $this->messages->addMemberToConversation($conversationId, $viewerId, (int) $user['id']);
            }
        }

        return $response->withHeader('Location', '/messages?conversation=' . $conversationId)->withStatus(302);
    }

    public function removeMember(Request $request, Response $response, array $args): Response
    {
        $viewerId = (int) Auth::id();
        $conversationId = (int) $args['id'];
        $targetUserId = (int) $args['userId'];

        $this->messages->removeMemberFromConversation($conversationId, $viewerId, $targetUserId);

        return $response->withHeader('Location', '/messages?conversation=' . $conversationId)->withStatus(302);
    }

    public function acceptRequest(Request $request, Response $response, array $args): Response
    {
        $viewerId = (int) Auth::id();
        $conversationId = (int) $args['id'];

        $this->messages->acceptConversationRequest($conversationId, $viewerId);

        return $response->withHeader('Location', '/messages?conversation=' . $conversationId)->withStatus(302);
    }

    public function rejectRequest(Request $request, Response $response, array $args): Response
    {
        $viewerId = (int) Auth::id();
        $conversationId = (int) $args['id'];

        $this->messages->deletePendingConversationRequest($conversationId, $viewerId);

        return $response->withHeader('Location', '/messages')->withStatus(302);
    }

    public function searchUsers(Request $request, Response $response): Response
    {
        $q = ltrim(trim((string) ($request->getQueryParams()['q'] ?? '')), '@');

        if ($q === '') {
            $response->getBody()->write('[]');
            return $response->withHeader('Content-Type', 'application/json');
        }

        $users = $this->users->searchByUsername($q);

        $payload = json_encode($users, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $response->getBody()->write($payload ?: '[]');

        return $response->withHeader('Content-Type', 'application/json');
    }
}