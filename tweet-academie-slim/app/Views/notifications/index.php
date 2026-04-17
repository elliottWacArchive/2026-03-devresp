<?php use App\Core\Helpers; ?>

<section class="panel">
  <h1>Notifications</h1>

  <?php if (empty($notifications)): ?>
    <p>Aucune notification.</p>
  <?php else: ?>
    <div class="connections-list">
      <?php foreach ($notifications as $notification): ?>
        <?php
          $actorName = trim(($notification['actor_firstname'] ?? '') . ' ' . ($notification['actor_lastname'] ?? ''));
          if ($actorName === '') {
              $actorName = '@' . ($notification['actor_username'] ?? '');
          }

          $preview = trim((string) ($notification['content_preview'] ?? ''));
          $type = (string) ($notification['type'] ?? '');
          $text = '';

          if ($type === 'reply') {
    $text = $actorName . ' replied to your post';
    if ($preview !== '') {
        $text .= ': ' . $preview;
    }
} elseif ($type === 'reply_to_reply') {
    $text = $actorName . ' replied to your comment';
    if ($preview !== '') {
        $text .= ': ' . $preview;
    }
} elseif ($type === 'following_post') {
    $text = $actorName . ' posted';
    if ($preview !== '') {
        $text .= ': ' . $preview;
    }
} elseif ($type === 'repost') {
    $text = $actorName . ' reposted your post';
    if ($preview !== '') {
        $text .= ': ' . $preview;
    }
} elseif ($type === 'repost_reply') {
    $text = $actorName . ' reposted your reply';
    if ($preview !== '') {
        $text .= ': ' . $preview;
    }
} elseif ($type === 'mention') {
    $text = $actorName . ' mentioned you';
    if ($preview !== '') {
        $text .= ': ' . $preview;
    }
} elseif ($type === 'follow') {
    $text = $actorName . ' followed you.';
} elseif ($type === 'like') {
    if (!empty($notification['reply_tweet_id'])) {
        $text = $actorName . ' liked your reply';
    } else {
        $text = $actorName . ' liked your post';
    }

    if ($preview !== '') {
        $text .= ': ' . $preview;
    }
} else {
    $text = $actorName;
    if ($preview !== '') {
        $text .= ': ' . $preview;
    }
}
        ?>

        <article class="connection-card <?= empty($notification['is_read']) ? 'notification-unread' : '' ?>">
          <a class="search-result-link" href="/notifications/<?= (int) $notification['id'] ?>/open">
            <?php if (!empty($notification['actor_icon'])): ?>
              <img class="avatar search-avatar" src="<?= Helpers::e($notification['actor_icon']) ?>" alt="Avatar">
            <?php else: ?>
              <div class="avatar search-avatar avatar-fallback">
                <?= strtoupper(substr((string) ($notification['actor_username'] ?? '?'), 0, 1)) ?>
              </div>
            <?php endif; ?>

            <div class="search-user-meta">
              <strong><?= Helpers::e($text) ?></strong>
              <span><?= Helpers::formatDate((string) $notification['created_at']) ?></span>
            </div>
          </a>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>