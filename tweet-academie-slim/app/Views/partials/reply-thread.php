<?php

use App\Core\Helpers;

/** @var array $reply */
/** @var int $viewerId */

$replyId = (int) $reply['id'];
$replyChildren = $reply['children'] ?? [];
$replyRootTweetId = (int) ($reply['root_tweet_id'] ?? 0);
$replyImageShortCode = $reply['image_short_code'] ?? null;

$replyEditTextareaId = 'edit-reply-' . $replyId;
$nestedReplyTextareaId = 'reply-thread-text-' . $replyId;
$nestedReplyImageId = 'reply-thread-image-' . $replyId;
?>

<article class="reply-card" id="reply-<?= (int) $reply['id'] ?>">
  <div class="tweet-layout">
    <a href="/profile/<?= Helpers::e($reply['username']) ?>" class="tweet-avatar-link">
      <?php if (!empty($reply['icon'])): ?>
        <img
          class="avatar reply-avatar"
          src="<?= Helpers::e($reply['icon']) ?>"
          alt="Avatar de <?= Helpers::e($reply['username']) ?>"
          width="42"
          height="42"
          loading="lazy"
          decoding="async"
        >
      <?php else: ?>
        <div class="avatar reply-avatar avatar-fallback" aria-hidden="true">
          <?= strtoupper(substr((string) $reply['username'], 0, 1)) ?>
        </div>
      <?php endif; ?>
    </a>

    <div class="tweet-body">
      <a href="/tweet/<?= $replyRootTweetId ?>#reply-<?= (int) $reply['id'] ?>" class="tweet-content-link">
        <div class="tweet-top">
          <div class="tweet-user">
            <strong><?= Helpers::e(trim(($reply['firstname'] ?? '') . ' ' . ($reply['lastname'] ?? ''))) ?></strong>
            <span class="muted-line">@<?= Helpers::e($reply['username']) ?></span>
          </div>
          <small class="muted-line"><?= Helpers::formatDate($reply['created_at']) ?></small>
        </div>

        <?php if (!empty($reply['parent_username'])): ?>
          <p class="reply-context">
            Replying to
            <span class="mention">@<?= Helpers::e($reply['parent_username']) ?></span>
          </p>
        <?php endif; ?>

        <p class="tweet-text">
          <?= preg_replace_callback(
            '/(#([A-Za-z0-9_]+))/',
            fn($m) => '<span class="hashtag">#' . Helpers::e($m[2]) . '</span>',
            preg_replace_callback(
              '/@([A-Za-z0-9_]+)/',
              fn($m) => '<span class="mention">@' . Helpers::e($m[1]) . '</span>',
              nl2br(Helpers::e($reply['tweet']))
            )
          ) ?>
        </p>
      </a>

      <?php if (!empty($reply['image'])): ?>
        <?php if (!empty($replyImageShortCode)): ?>
          <a href="/img/<?= Helpers::e($replyImageShortCode) ?>" target="_blank" rel="noopener noreferrer">
        <?php endif; ?>

        <img
          class="tweet-image"
          src="<?= Helpers::e($reply['image']) ?>"
          alt="Image du commentaire"
          width="400"
          height="400"
          loading="lazy"
          decoding="async"
        >

        <?php if (!empty($replyImageShortCode)): ?>
          </a>
        <?php endif; ?>
      <?php endif; ?>

<div class="tweet-actions">
  <div class="like-group">
    <button
      type="button"
      class="button button-outline action-pill button-like <?= !empty($reply['liked_by_me']) ? 'is-liked' : '' ?>"
      data-like-button
      data-like-target="<?= (int) $reply['id'] ?>"
      data-liked="<?= !empty($reply['liked_by_me']) ? '1' : '0' ?>"
      aria-label="Like"
    >
      <span class="like-icon" aria-hidden="true"><?= !empty($reply['liked_by_me']) ? '♥' : '♡' ?></span>
    </button>

    <span class="muted-line likes-count">
      <span data-like-count><?= (int) $reply['likes_count'] ?></span>&nbsp;likes
    </span>
  </div>

  <form action="/tweets/<?= (int) $reply['id'] ?>/repost" method="POST" class="inline-form">
    <button type="submit" class="button button-outline action-pill">🔁</button>
  </form>

  <?php if ($viewerId > 0 && (int) $reply['user_id'] === $viewerId): ?>
    <form
      action="/tweets/<?= (int) $reply['id'] ?>/delete"
      method="POST"
      class="inline-form"
      data-confirm="Delete this reply?"
    >
      <button type="submit" class="button button-outline danger-outline action-pill">🗑️</button>
    </form>
  <?php endif; ?>
</div>

<?php if ($viewerId > 0 && (int) $reply['user_id'] === $viewerId): ?>
  <details class="reply-box">
    <summary>✏️</summary>
    <form action="/tweets/<?= (int) $reply['id'] ?>/edit" method="POST" class="reply-form">
      <label for="<?= Helpers::e($replyEditTextareaId) ?>" class="sr-only">Edit your reply</label>
      <textarea
        id="<?= Helpers::e($replyEditTextareaId) ?>"
        name="tweet"
        class="u-full-width input-dark"
        maxlength="140"
        required
      ><?= Helpers::e($reply['tweet']) ?></textarea>

      <button type="submit" class="button-primary action-pill">💾</button>
    </form>
  </details>
<?php endif; ?>

<?php if (!empty($reply['can_reply'])): ?>
  <details class="reply-box">
    <summary>💬</summary>

    <form action="/tweets" method="POST" enctype="multipart/form-data" class="reply-form">
      <input type="hidden" name="type" value="reply">
      <input type="hidden" name="parent_id" value="<?= (int) $reply['id'] ?>">

      <label for="<?= Helpers::e($nestedReplyTextareaId) ?>" class="sr-only">Reply to this comment</label>
      <textarea
        id="<?= Helpers::e($nestedReplyTextareaId) ?>"
        name="tweet"
        class="u-full-width input-dark"
        maxlength="140"
        placeholder="Écris ton commentaire"
        required
      ></textarea>

      <label for="<?= Helpers::e($nestedReplyImageId) ?>" class="sr-only">Add an image to this reply</label>
      <input
        id="<?= Helpers::e($nestedReplyImageId) ?>"
        type="file"
        name="image"
        accept="image/*"
        class="input-file-dark"
      >

      <button type="submit" class="button-primary action-pill">📩</button>
    </form>
  </details>
<?php endif; ?>

      <?php if (!empty($replyChildren)): ?>
        <div class="replies nested-replies">
          <?php foreach ($replyChildren as $childReply): ?>
            <?php $reply = $childReply; ?>
            <?php include __DIR__ . '/reply-thread.php'; ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</article>