<?php

use App\Core\Helpers;

if (!function_exists('countReplyTree')) {
    function countReplyTree(array $replies): int
    {
        $count = 0;

        foreach ($replies as $reply) {
            $count++;
            $count += countReplyTree($reply['children'] ?? []);
        }

        return $count;
    }
}

$viewerId = isset($me['id']) ? (int) $me['id'] : 0;
$feedContext = $feedContext ?? 'timeline';

$isRetweet = ($tweet['type'] ?? '') === 'retweet';
$isReply = ($tweet['type'] ?? '') === 'reply';

if ($isRetweet && empty($tweet['original_id'])) {
    return;
}

$targetId = (int) ($tweet['target_tweet_id'] ?? $tweet['id']);

$displayUsername = $isRetweet ? ($tweet['original_username'] ?? '') : ($tweet['username'] ?? '');
$displayFirstname = $isRetweet ? ($tweet['original_firstname'] ?? '') : ($tweet['firstname'] ?? '');
$displayLastname = $isRetweet ? ($tweet['original_lastname'] ?? '') : ($tweet['lastname'] ?? '');
$displayIcon = $isRetweet ? ($tweet['original_icon'] ?? null) : ($tweet['icon'] ?? null);
$displayText = $isRetweet ? ($tweet['original_tweet'] ?? '') : ($tweet['tweet'] ?? '');
$displayImage = $isRetweet ? ($tweet['original_image'] ?? null) : ($tweet['image'] ?? null);

$showRepostLabel = !empty($tweet['reposters']) && in_array($feedContext, ['timeline', 'profile'], true);
$repostLabel = '';

if ($showRepostLabel) {
    $reposters = $tweet['reposters'] ?? [];

    usort($reposters, function (array $a, array $b) use ($viewerId): int {
        $aIsViewer = $viewerId > 0 && (int) $a['user_id'] === $viewerId;
        $bIsViewer = $viewerId > 0 && (int) $b['user_id'] === $viewerId;

        if ($aIsViewer && !$bIsViewer) {
            return -1;
        }

        if (!$aIsViewer && $bIsViewer) {
            return 1;
        }

        $aDate = strtotime((string) ($a['created_at'] ?? '')) ?: 0;
        $bDate = strtotime((string) ($b['created_at'] ?? '')) ?: 0;

        return $bDate <=> $aDate;
    });

    $names = [];
    foreach ($reposters as $reposter) {
        if ($viewerId > 0 && (int) $reposter['user_id'] === $viewerId) {
            $names[] = 'You';
        } else {
            $fullName = trim(($reposter['firstname'] ?? '') . ' ' . ($reposter['lastname'] ?? ''));
            $names[] = $fullName !== '' ? $fullName : ('@' . ($reposter['username'] ?? ''));
        }
    }

    $names = array_values(array_unique($names));

    $repostedThing = 'tweet';
    if ($isRetweet && (($tweet['original_type'] ?? '') === 'reply')) {
        $repostedThing = 'comment';
    }

    if (count($names) === 1) {
        $repostLabel = $names[0] . ' reposted this ' . $repostedThing;
    } elseif (count($names) === 2) {
        $repostLabel = $names[0] . ' and ' . $names[1] . ' reposted this ' . $repostedThing;
    } elseif (count($names) === 3) {
        $repostLabel = $names[0] . ', ' . $names[1] . ' and ' . $names[2] . ' reposted this ' . $repostedThing;
    } else {
        $remaining = count($names) - 2;
        $repostLabel = $names[0] . ', ' . $names[1] . ' and ' . $remaining . ' others reposted this ' . $repostedThing;
    }
}

$canDeleteRenderedRow = $viewerId > 0 && (int) $tweet['user_id'] === $viewerId && !$isRetweet;
$canEditRenderedRow = $viewerId > 0 && (int) $tweet['user_id'] === $viewerId && !$isRetweet;
$canDeleteOriginalFromRetweetView = $isRetweet && $viewerId > 0 && !empty($tweet['original_user_id']) && (int) $tweet['original_user_id'] === $viewerId;
$canEditOriginalFromRetweetView = $isRetweet && $viewerId > 0 && !empty($tweet['original_user_id']) && (int) $tweet['original_user_id'] === $viewerId;

$editableId = $isRetweet ? (int) ($tweet['original_id'] ?? 0) : (int) $tweet['id'];

$linkTarget = '/tweet/' . $targetId;

if ($isReply) {
    $linkTarget = '/tweet/' . (int) ($tweet['parent_id'] ?? $tweet['id']) . '#reply-' . (int) $tweet['id'];
} elseif ($isRetweet && !empty($tweet['original_id'])) {
    $linkTarget = '/tweet/' . (int) $tweet['original_id'];
}

$imageShortCode = $isRetweet
    ? ($tweet['original_image_short_code'] ?? null)
    : ($tweet['image_short_code'] ?? null);

$renderedTweetId = (int) $tweet['id'];
$editTextareaId = 'edit-tweet-' . $renderedTweetId;
$replyTextareaId = 'reply-text-' . $renderedTweetId;
$replyImageId = 'reply-image-' . $renderedTweetId;
?>

<article class="tweet-card" data-tweet-id="<?= (int) $tweet['id'] ?>">

  <?php if ($showRepostLabel): ?>
    <p class="repost-label">📣 <strong><?= Helpers::e($repostLabel) ?></strong></p>
  <?php endif; ?>

  <div class="tweet-layout">

    <a href="/profile/<?= Helpers::e($displayUsername) ?>" class="tweet-avatar-link">
      <?php if (!empty($displayIcon)): ?>
        <img
          class="avatar tweet-avatar"
          src="<?= Helpers::e($displayIcon) ?>"
          alt="Avatar de <?= Helpers::e($displayUsername) ?>"
          width="52"
          height="52"
          loading="lazy"
          decoding="async"
        >
      <?php else: ?>
        <div class="avatar tweet-avatar avatar-fallback">
          <?= strtoupper(substr((string) $displayUsername, 0, 1)) ?>
        </div>
      <?php endif; ?>
    </a>

    <div class="tweet-body">

      <a href="<?= Helpers::e($linkTarget) ?>" class="tweet-content-link">
        <div class="tweet-top">
          <div class="tweet-user">
            <strong><?= Helpers::e(trim($displayFirstname . ' ' . $displayLastname)) ?></strong>
            <span class="muted-line">@<?= Helpers::e($displayUsername) ?></span>
          </div>
          <small class="muted-line"><?= Helpers::formatDate($tweet['created_at']) ?></small>
        </div>

        <?php if ($isReply && !empty($tweet['parent_username'])): ?>
          <p class="reply-context">
            Replying to
            <a href="/tweet/<?= (int) ($tweet['root_tweet_id'] ?? $tweet['parent_tweet_id'] ?? $tweet['id']) ?>#reply-<?= (int) $tweet['id'] ?>">
              @<?= Helpers::e($tweet['parent_username']) ?>
            </a>
          </p>
        <?php endif; ?>

        <p class="tweet-text">
          <?= preg_replace_callback(
            '/(#([A-Za-z0-9_]+))/',
            fn($m) => '<span class="hashtag">#' . Helpers::e($m[2]) . '</span>',
            preg_replace_callback(
              '/@([A-Za-z0-9_]+)/',
              fn($m) => '<span class="mention">@' . Helpers::e($m[1]) . '</span>',
              nl2br(Helpers::e($displayText))
            )
          ) ?>
        </p>
      </a>

      <?php if (!empty($displayImage)): ?>
        <?php if (!empty($imageShortCode)): ?>
          <a href="/img/<?= Helpers::e($imageShortCode) ?>" target="_blank" rel="noopener noreferrer">
        <?php endif; ?>

        <img
          class="tweet-image"
          src="<?= Helpers::e($displayImage) ?>"
          alt="Image du tweet"
          width="400"
          height="400"
          loading="lazy"
          decoding="async"
        >

        <?php if (!empty($imageShortCode)): ?>
          </a>
        <?php endif; ?>
      <?php endif; ?>

      <div class="tweet-actions">

  <div class="like-group">
  <button
    type="button"
    class="button button-outline action-pill button-like <?= !empty($tweet['liked_by_me']) ? 'is-liked' : '' ?>"
    data-like-button
    data-like-target="<?= $targetId ?>"
    data-liked="<?= !empty($tweet['liked_by_me']) ? '1' : '0' ?>"
    aria-label="Like"
  >
    <span class="like-icon" aria-hidden="true"><?= !empty($tweet['liked_by_me']) ? '♥' : '♡' ?></span>
  </button>

  <span class="muted-line likes-count">
    <span data-like-count><?= (int) $tweet['likes_count'] ?></span>&nbsp;likes
  </span>
</div>

  <form action="/tweets/<?= $targetId ?>/repost" method="POST" class="inline-form">
    <button
      type="submit"
      class="button button-outline action-pill button-repost <?= !empty($tweet['is_reposted_by_me']) ? 'is-reposted' : '' ?>"
      aria-label="Reposter ce post"
    >
      <?= !empty($tweet['is_reposted_by_me']) ? '🔁 Annuler' : '🔁' ?>
    </button>
  </form>

        <?php if ($canDeleteRenderedRow): ?>
          <form
            action="/tweets/<?= (int) $tweet['id'] ?>/delete"
            method="POST"
            class="inline-form"
            data-confirm="Delete this <?= $isReply ? 'reply' : 'tweet' ?>?"
          >
            <button type="submit" class="button button-outline danger-outline action-pill">
              Delete
            </button>
          </form>
        <?php endif; ?>

        <?php if ($canDeleteOriginalFromRetweetView): ?>
          <form
            action="/tweets/<?= (int) ($tweet['original_id'] ?? 0) ?>/delete-original"
            method="POST"
            class="inline-form"
            data-confirm="Delete this original tweet?"
          >
            <button type="submit" class="button button-outline danger-outline action-pill">
              🗑️
            </button>
          </form>
        <?php endif; ?>

      </div>

      <?php if ($canEditRenderedRow || $canEditOriginalFromRetweetView): ?>
        <details class="reply-box">
          <summary>✏️</summary>

          <form action="/tweets/<?= $editableId ?>/edit" method="POST" class="reply-form">
            <label for="<?= Helpers::e($editTextareaId) ?>" class="sr-only">Edit your post</label>
            <textarea
              id="<?= Helpers::e($editTextareaId) ?>"
              name="tweet"
              class="u-full-width input-dark"
              maxlength="140"
              required
            ><?= Helpers::e($displayText) ?></textarea>

            <button type="submit" class="button-primary action-pill">💾</button>
          </form>
        </details>
      <?php endif; ?>

      <?php if (!empty($tweet['can_reply'])): ?>
        <details class="reply-box">
          <summary>📝</summary>

          <form action="/tweets" method="POST" enctype="multipart/form-data" class="reply-form">
            <input type="hidden" name="type" value="reply">
            <input type="hidden" name="parent_id" value="<?= $targetId ?>">

            <label for="<?= Helpers::e($replyTextareaId) ?>" class="sr-only">Write your comment</label>
            <textarea
              id="<?= Helpers::e($replyTextareaId) ?>"
              name="tweet"
              class="u-full-width input-dark"
              maxlength="140"
              placeholder="Écris ton commentaire"
              required
            ></textarea>

            <label for="<?= Helpers::e($replyImageId) ?>" class="sr-only">Add an image to your comment</label>
            <input
              id="<?= Helpers::e($replyImageId) ?>"
              type="file"
              name="image"
              accept="image/*"
              class="input-file-dark"
            >

            <button type="submit" class="button-primary action-pill">📩</button>
          </form>
        </details>
      <?php endif; ?>

      <?php if (!empty($tweet['replies'])): ?>
        <?php if (($feedContext ?? 'timeline') === 'tweet'): ?>
          <div class="replies">
            <?php foreach ($tweet['replies'] as $reply): ?>
              <?php include __DIR__ . '/reply-thread.php'; ?>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <details class="reply-box">
            <summary>💬 (<?= countReplyTree($tweet['replies']) ?>)</summary>

            <div class="replies">
              <?php foreach ($tweet['replies'] as $reply): ?>
                <?php include __DIR__ . '/reply-thread.php'; ?>
              <?php endforeach; ?>
            </div>
          </details>
        <?php endif; ?>
      <?php endif; ?>

    </div>
  </div>
</article>