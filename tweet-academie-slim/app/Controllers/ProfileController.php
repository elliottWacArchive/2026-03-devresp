<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\Follow;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Tweet;
use App\Models\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class ProfileController
{
    public function __construct(
        private User $users,
        private Tweet $tweets,
        private Follow $follows,
        private Message $messages,
        private Notification $notifications
    ) {
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $user = $this->users->findByUsername((string) $args['username']);
        if (!$user) {
            $response->getBody()->write('Profil introuvable');
            return $response->withStatus(404);
        }

        $viewerId = Auth::id() ? (int) Auth::id() : 0;
        $isOwner = $viewerId > 0 && $viewerId === (int) $user['id'];
        $isFollowing = $viewerId > 0 ? $this->follows->isFollowing($viewerId, (int) $user['id']) : false;
        $hasPendingRequest = $viewerId > 0 ? $this->follows->hasPendingRequest($viewerId, (int) $user['id']) : false;
        $canViewContent = $isOwner || (int) ($user['is_private'] ?? 0) === 0 || $isFollowing;
        $isBlockedByMe = $viewerId > 0 ? $this->messages->isBlockedBy($viewerId, (int) $user['id']) : false;

        $canMessage = false;
        if ($viewerId > 0 && !$isOwner && !$isBlockedByMe) {
            $mode = $this->messages->directMessageMode($viewerId, $user, $this->follows);
            $canMessage = $mode !== 'denied';
        }

        ob_start();
        View::render('profile/show', [
            'title' => '@' . $user['username'],
            'profile' => $user,
            'tweets' => $canViewContent ? $this->tweets->profileTweets((int) $user['id'], $viewerId) : [],
            'replies' => $canViewContent ? $this->tweets->profileReplies((int) $user['id'], $viewerId) : [],
            'reposts' => $canViewContent ? $this->tweets->profileReposts((int) $user['id'], $viewerId) : [],
            'requests' => $isOwner ? $this->follows->pendingRequestsForUser($viewerId) : [],
            'isFollowing' => $isFollowing,
            'hasPendingRequest' => $hasPendingRequest,
            'isBlockedByMe' => $isBlockedByMe,
            'canMessage' => $canMessage,
            'canViewContent' => $canViewContent,
            'followersCount' => $this->users->followersCount((int) $user['id']),
            'followingCount' => $this->users->followingCount((int) $user['id']),
            'me' => Auth::user(),
            'notificationUnreadCount' => $this->notifications->unreadCountForUser($viewerId),
        ]);
        $response->getBody()->write((string) ob_get_clean());
        return $response;
    }

    public function drafts(Request $request, Response $response): Response
    {
        $viewerId = (int) Auth::id();
        $me = $this->users->findById($viewerId);
        $drafts = $this->tweets->draftsForUser($viewerId);

        ob_start();
        View::render('profile/drafts', [
            'title' => 'Drafts',
            'profile' => $me,
            'drafts' => $drafts,
            'me' => Auth::user(),
            'notificationUnreadCount' => $this->notifications->unreadCountForUser($viewerId),
        ]);
        $response->getBody()->write((string) ob_get_clean());
        return $response;
    }

    public function followers(Request $request, Response $response, array $args): Response
    {
        $user = $this->users->findByUsername((string) $args['username']);
        if (!$user) {
            $response->getBody()->write('Profil introuvable');
            return $response->withStatus(404);
        }

        $viewerId = Auth::id() ? (int) Auth::id() : 0;
        $isOwner = $viewerId > 0 && $viewerId === (int) $user['id'];
        $isFollowing = $viewerId > 0 ? $this->follows->isFollowing($viewerId, (int) $user['id']) : false;
        $canViewContent = $isOwner || (int) ($user['is_private'] ?? 0) === 0 || $isFollowing;

        ob_start();
        View::render('profile/connections', [
            'title' => '@' . $user['username'] . ' followers',
            'profile' => $user,
            'me' => Auth::user(),
            'type' => 'followers',
            'usersList' => $canViewContent ? $this->users->followersList((int) $user['id']) : [],
            'canViewContent' => $canViewContent,
            'notificationUnreadCount' => $this->notifications->unreadCountForUser($viewerId),
        ]);
        $response->getBody()->write((string) ob_get_clean());
        return $response;
    }

    public function following(Request $request, Response $response, array $args): Response
    {
        $user = $this->users->findByUsername((string) $args['username']);
        if (!$user) {
            $response->getBody()->write('Profil introuvable');
            return $response->withStatus(404);
        }

        $viewerId = Auth::id() ? (int) Auth::id() : 0;
        $isOwner = $viewerId > 0 && $viewerId === (int) $user['id'];
        $isFollowing = $viewerId > 0 ? $this->follows->isFollowing($viewerId, (int) $user['id']) : false;
        $canViewContent = $isOwner || (int) ($user['is_private'] ?? 0) === 0 || $isFollowing;

        ob_start();
        View::render('profile/connections', [
            'title' => '@' . $user['username'] . ' following',
            'profile' => $user,
            'me' => Auth::user(),
            'type' => 'following',
            'usersList' => $canViewContent ? $this->users->followingList((int) $user['id']) : [],
            'canViewContent' => $canViewContent,
            'notificationUnreadCount' => $this->notifications->unreadCountForUser($viewerId),
        ]);
        $response->getBody()->write((string) ob_get_clean());
        return $response;
    }

    public function edit(Request $request, Response $response): Response
    {
        $viewerId = (int) Auth::id();
        $me = $this->users->findById($viewerId);

        ob_start();
        View::render('profile/edit', [
            'title' => 'Éditer mon profil',
            'profile' => $me,
            'notificationUnreadCount' => $this->notifications->unreadCountForUser($viewerId),
        ]);
        $response->getBody()->write((string) ob_get_clean());
        return $response;
    }

    public function update(Request $request, Response $response): Response
    {
        $me = $this->users->findById((int) Auth::id());
        $data = (array) $request->getParsedBody();
        $files = $request->getUploadedFiles();

        $icon = $me['icon'] ?? null;
        $banner = $me['banner'] ?? null;

        foreach (['icon', 'banner'] as $field) {
    if (isset($files[$field]) && $files[$field]->getError() === UPLOAD_ERR_OK) {
        $uploaded = $files[$field];

        ${$field} = $this->storeOptimizedImage(
            $uploaded,
            $field === 'icon' ? 'icon_' : 'banner_',
            $field === 'icon' ? 128 : 1500,
            $field === 'icon' ? 128 : 500
        );
    }
}

        $this->users->updateProfile((int) Auth::id(), [
            'firstname' => trim((string) $data['firstname']),
            'lastname' => trim((string) $data['lastname']),
            'bio' => trim((string) ($data['bio'] ?? '')),
            'github' => trim((string) ($data['github'] ?? '')),
            'linkedin' => trim((string) ($data['linkedin'] ?? '')),
            'icon' => $icon,
            'banner' => $banner,
            'is_private' => !empty($data['is_private']) ? 1 : 0,
            'allow_dm_from_anyone' => !empty($data['allow_dm_from_anyone']) ? 1 : 0,
        ]);

        $_SESSION['user']['firstname'] = trim((string) $data['firstname']);
        $_SESSION['user']['lastname'] = trim((string) $data['lastname']);
        $_SESSION['user']['icon'] = $icon;
        $_SESSION['user']['is_private'] = !empty($data['is_private']) ? 1 : 0;
        $_SESSION['user']['allow_dm_from_anyone'] = !empty($data['allow_dm_from_anyone']) ? 1 : 0;

        return $response->withHeader('Location', '/profile/' . $_SESSION['user']['username'])->withStatus(302);
    }

    public function search(Request $request, Response $response): Response
    {
        $query = trim((string) ($request->getQueryParams()['q'] ?? ''));

        if ($query === '' || mb_strlen($query) < 2) {
            $response->getBody()->write('[]');
            return $response->withHeader('Content-Type', 'application/json');
        }

        $results = $this->users->search($query);
        $payload = json_encode($results, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $response->getBody()->write($payload ?: '[]');
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function storeOptimizedImage(object $uploaded, string $prefix, int $maxWidth, int $maxHeight): ?string
{
    $clientName = method_exists($uploaded, 'getClientFilename') ? (string) $uploaded->getClientFilename() : '';
    $ext = strtolower((string) pathinfo($clientName, PATHINFO_EXTENSION));

    $tmpPath = sys_get_temp_dir() . '/' . uniqid('upload_', true) . '.' . ($ext ?: 'jpg');
    $uploaded->moveTo($tmpPath);

    $image = $this->createImageResource($tmpPath, $ext);
    if (!$image) {
        @unlink($tmpPath);
        return null;
    }

    [$newWidth, $newHeight] = $this->calculateFitDimensions(
        imagesx($image),
        imagesy($image),
        $maxWidth,
        $maxHeight
    );

    $resized = imagecreatetruecolor($newWidth, $newHeight);

    imagealphablending($resized, false);
    imagesavealpha($resized, true);

    $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
    imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);

    imagecopyresampled(
        $resized,
        $image,
        0,
        0,
        0,
        0,
        $newWidth,
        $newHeight,
        imagesx($image),
        imagesy($image)
    );

    $filename = uniqid($prefix, true) . '.jpg';
    $targetDir = __DIR__ . '/../../public/assets/uploads/';

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $targetPath = $targetDir . $filename;

    imagejpeg($resized, $targetPath, 82);

    imagedestroy($image);
    imagedestroy($resized);
    @unlink($tmpPath);

    return '/assets/uploads/' . $filename;
}

private function createImageResource(string $path, string $ext)
{
    return match ($ext) {
        'jpg', 'jpeg' => @imagecreatefromjpeg($path),
        'png' => @imagecreatefrompng($path),
        'gif' => @imagecreatefromgif($path),
        'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null,
        'avif' => function_exists('imagecreatefromavif') ? @imagecreatefromavif($path) : null,
        default => @imagecreatefromstring((string) @file_get_contents($path)),
    };
}

private function calculateFitDimensions(int $width, int $height, int $maxWidth, int $maxHeight): array
{
    if ($width <= 0 || $height <= 0) {
        return [$maxWidth, $maxHeight];
    }

    $ratio = min($maxWidth / $width, $maxHeight / $height, 1);

    return [
        max(1, (int) round($width * $ratio)),
        max(1, (int) round($height * $ratio)),
    ];
}
}