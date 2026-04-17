<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Models\ShortUrl;
use App\Core\View;
use App\Models\Notification;
use App\Models\Tweet;
use App\Models\Message;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class TweetController
{
    public function __construct(
    private Tweet $tweets,
    private Notification $notifications,
    private Message $messages,
    private ShortUrl $shortUrls
) {
}

    public function store(Request $request, Response $response): Response
{
    $data = (array) $request->getParsedBody();
    $files = $request->getUploadedFiles();

    $text = trim((string) ($data['tweet'] ?? ''));
    $type = trim((string) ($data['type'] ?? 'original'));
    $parentId = !empty($data['parent_id']) ? (int) $data['parent_id'] : null;
    $userId = (int) Auth::id();
    $replyPermission = trim((string) ($data['reply_permission'] ?? 'everyone'));

    $allowedPermissions = ['everyone', 'following', 'mentioned', 'disabled'];
    if (!in_array($replyPermission, $allowedPermissions, true)) {
        $replyPermission = 'everyone';
    }

    $imagePath = null;
    $imageShortCode = null;

    if (isset($files['image']) && $files['image']->getError() === UPLOAD_ERR_OK) {
        $imagePath = $this->storeUploadedImage($files['image']);

        if ($imagePath !== null) {
            $imageShortCode = $this->shortUrls->create($imagePath);
        }
    }

    if ($text === '' || mb_strlen($text) > 140) {
        $_SESSION['flash_error'] = 'Tweet vide ou trop long.';
        return $this->redirectBack($request, $response);
    }

    if ($type === 'reply' && $parentId) {
        if ($this->tweets->canUserReplyToTweet($parentId, $userId)) {
            $replyId = $this->tweets->createReply(
                $userId,
                $parentId,
                $text,
                $imagePath,
                $imageShortCode
            );

            $parentTweet = $this->tweets->findTweetById($parentId);
            $rootTweetId = $this->tweets->rootTweetIdForThread($parentId);

            if ($parentTweet && (int) $parentTweet['user_id'] !== $userId) {
                $this->notifications->create(
                    (int) $parentTweet['user_id'],
                    $userId,
                    (($parentTweet['type'] ?? '') === 'reply') ? 'reply_to_reply' : 'reply',
                    $rootTweetId,
                    $replyId,
                    null,
                    mb_substr($text, 0, 120)
                );
            }

            foreach ($this->tweets->mentionedUserIdsInTweet($text) as $mentionedUserId) {
                if (
                    $mentionedUserId !== $userId &&
                    (!$parentTweet || $mentionedUserId !== (int) $parentTweet['user_id'])
                ) {
                    $this->notifications->create(
                        $mentionedUserId,
                        $userId,
                        'mention',
                        $rootTweetId,
                        $replyId,
                        null,
                        mb_substr($text, 0, 120)
                    );
                }
            }
        }
    } else {
        $tweetId = $this->tweets->createOriginal(
            $userId,
            $text,
            $imagePath,
            $replyPermission,
            $imageShortCode
        );

        foreach ($this->tweets->followersOfUser($userId) as $followerId) {
            $this->notifications->create(
                $followerId,
                $userId,
                'following_post',
                $tweetId,
                null,
                null,
                mb_substr($text, 0, 120)
            );
        }

        foreach ($this->tweets->mentionedUserIdsInTweet($text) as $mentionedUserId) {
            if ($mentionedUserId !== $userId) {
                $this->notifications->create(
                    $mentionedUserId,
                    $userId,
                    'mention',
                    $tweetId,
                    null,
                    null,
                    mb_substr($text, 0, 120)
                );
            }
        }
    }

    return $this->redirectBack($request, $response);
}

    public function saveDraft(Request $request, Response $response): Response
{
    $data = (array) $request->getParsedBody();
    $files = $request->getUploadedFiles();

    $userId = (int) Auth::id();
    $body = trim((string) ($data['tweet'] ?? ''));
    $replyPermission = trim((string) ($data['reply_permission'] ?? 'everyone'));

    $allowedPermissions = ['everyone', 'following', 'mentioned', 'disabled'];
    if (!in_array($replyPermission, $allowedPermissions, true)) {
        $replyPermission = 'everyone';
    }

    $imagePath = null;

    if (isset($files['image']) && $files['image']->getError() === UPLOAD_ERR_OK) {
        $imagePath = $this->storeUploadedImage($files['image']);
    }

    if ($body === '' && !$imagePath) {
        return $this->redirectBack($request, $response);
    }

    if (mb_strlen($body) > 140) {
        $_SESSION['flash_error'] = 'Draft trop long.';
        return $this->redirectBack($request, $response);
    }

    $this->tweets->createDraft($userId, $body, $imagePath, $replyPermission);

    $_SESSION['flash_success'] = 'Draft saved.';

    return $this->redirectBack($request, $response);
}

    public function publishDraft(Request $request, Response $response, array $args): Response
{
    $draftId = (int) $args['id'];
    $userId = (int) Auth::id();

    $draft = $this->tweets->findDraftById($draftId, $userId);
    if (!$draft) {
        return $response->withHeader('Location', '/profile/drafts')->withStatus(302);
    }

    $body = trim((string) ($draft['body'] ?? ''));
    $image = $draft['image'] ?? null;
    $replyPermission = trim((string) ($draft['reply_permission'] ?? 'everyone'));

    if ($body === '' && !$image) {
        return $response->withHeader('Location', '/profile/drafts')->withStatus(302);
    }

    $imageShortCode = null;
    if ($image) {
        $imageShortCode = $this->shortUrls->create((string) $image);
    }

    $this->tweets->createOriginal(
        $userId,
        $body,
        $image,
        $replyPermission,
        $imageShortCode
    );

    $this->tweets->deleteDraft($draftId, $userId);

    return $response->withHeader('Location', '/')->withStatus(302);
}

    public function deleteDraft(Request $request, Response $response, array $args): Response
    {
        $draftId = (int) $args['id'];
        $userId = (int) Auth::id();

        $this->tweets->deleteDraft($draftId, $userId);

        return $response->withHeader('Location', '/profile/drafts')->withStatus(302);
    }

    public function like(Request $request, Response $response, array $args): Response
{
    $tweetId = (int) $args['id'];
    $userId = (int) Auth::id();

    $liked = $this->tweets->toggleLike($tweetId, $userId);
    $count = $this->tweets->likesCount($tweetId);

    if ($liked) {
        $tweet = $this->tweets->findTweetById($tweetId);

        if ($tweet && (int) $tweet['user_id'] !== $userId) {
            $preview = mb_substr((string) ($tweet['tweet'] ?? ''), 0, 120);
            $isReply = (($tweet['type'] ?? '') === 'reply');

            $this->notifications->create(
                (int) $tweet['user_id'],
                $userId,
                'like',
                $isReply ? $this->tweets->rootTweetIdForThread($tweetId) : $tweetId,
                $isReply ? $tweetId : null,
                null,
                $preview
            );
        }
    }

    $payload = json_encode([
        'liked' => $liked,
        'count' => $count,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $response->getBody()->write($payload ?: '{}');
    return $response->withHeader('Content-Type', 'application/json');
}

    public function repost(Request $request, Response $response, array $args): Response
{
    $tweetId = (int) $args['id'];
    $userId = (int) Auth::id();

    $reposted = $this->tweets->toggleRepost($userId, $tweetId);

    if ($reposted) {
        $tweet = $this->tweets->findTweetById($tweetId);

        if ($tweet && (int) $tweet['user_id'] !== $userId) {
            $preview = mb_substr((string) ($tweet['tweet'] ?? ''), 0, 120);
            $isReply = (($tweet['type'] ?? '') === 'reply');
            $rootTweetId = $isReply
                ? $this->tweets->rootTweetIdForThread($tweetId)
                : $tweetId;

            $this->notifications->create(
                (int) $tweet['user_id'],
                $userId,
                $isReply ? 'repost_reply' : 'repost',
                $rootTweetId,
                $isReply ? $tweetId : null,
                null,
                $preview
            );
        }
    }

    return $this->redirectBack($request, $response);
}

    public function delete(Request $request, Response $response, array $args): Response
    {
        $tweetId = (int) $args['id'];
        $userId = (int) Auth::id();

        $this->tweets->deleteOwned($tweetId, $userId);
        return $this->redirectBack($request, $response);
    }

    public function deleteOriginal(Request $request, Response $response, array $args): Response
    {
        $tweetId = (int) $args['id'];
        $userId = (int) Auth::id();

        $this->tweets->deleteOriginalOwned($tweetId, $userId);
        return $this->redirectBack($request, $response);
    }

    public function edit(Request $request, Response $response, array $args): Response
    {
        $tweetId = (int) $args['id'];
        $userId = (int) Auth::id();
        $data = (array) $request->getParsedBody();

        $text = trim((string) ($data['tweet'] ?? ''));

        if ($text === '' || mb_strlen($text) > 140) {
            return $this->redirectBack($request, $response);
        }

        $this->tweets->editOwned($tweetId, $userId, $text);
        return $this->redirectBack($request, $response);
    }

    public function timelineJson(Request $request, Response $response): Response
    {
        $me = Auth::user();
        $timeline = $this->tweets->timelineForUser((int) Auth::id());
        $feedContext = 'timeline';

        ob_start();
        foreach ($timeline as $tweet) {
            include __DIR__ . '/../Views/partials/tweet-card.php';
        }

        $response->getBody()->write((string) ob_get_clean());
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    private function storeUploadedImage(object $uploaded): ?string
{
    $clientName = method_exists($uploaded, 'getClientFilename') ? (string) $uploaded->getClientFilename() : '';
    $ext = strtolower((string) pathinfo($clientName, PATHINFO_EXTENSION));

    $tmpPath = sys_get_temp_dir() . '/' . uniqid('tweet_upload_', true) . '.' . ($ext ?: 'jpg');
    $uploaded->moveTo($tmpPath);

    $image = $this->createImageResource($tmpPath, $ext);
    if (!$image) {
        @unlink($tmpPath);
        return null;
    }

    [$newWidth, $newHeight] = $this->calculateFitDimensions(
        imagesx($image),
        imagesy($image),
        800,
        800
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

    $filename = uniqid('tweet_', true) . '.jpg';
    $targetDir = __DIR__ . '/../../public/assets/uploads/';

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $targetPath = $targetDir . $filename;

    imagejpeg($resized, $targetPath, 84);

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

    private function redirectBack(Request $request, Response $response): Response
    {
        $referer = $request->getHeaderLine('Referer') ?: '/';
        return $response->withHeader('Location', $referer)->withStatus(302);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $tweetId = (int) $args['id'];
        $viewerId = (int) Auth::id();

        $tweet = $this->tweets->tweetByIdForViewer($tweetId, $viewerId);

        if (!$tweet) {
            $response->getBody()->write('Tweet introuvable');
            return $response->withStatus(404);
        }

        ob_start();
        View::render('tweets/show', [
            'title' => 'Tweet',
            'tweet' => $tweet,
            'me' => Auth::user(),
            'feedContext' => 'tweet',
            'notificationUnreadCount' => $this->notifications->unreadCountForUser($viewerId),
            'messageUnreadCount' => $this->messages->unreadCountForUser($viewerId),
        ]);
        $response->getBody()->write((string) ob_get_clean());

        return $response;
    }
}