<?php use App\Core\Helpers; ?>

<section class="panel profile-banner" style="<?= !empty($profile['banner']) ? 'background-image:url(' . Helpers::e($profile['banner']) . '); padding: 3rem 1.6rem;' : '' ?>">
  <div class="profile-head">
    <?php if (!empty($profile['icon'])): ?>
      <img class="avatar avatar-large" src="<?= Helpers::e($profile['icon']) ?>" alt="Avatar de <?= Helpers::e($profile['username']) ?>">
    <?php else: ?>
      <div class="avatar avatar-large avatar-fallback">
        <?= strtoupper(substr((string) $profile['username'], 0, 1)) ?>
      </div>
    <?php endif; ?>

    <div class="profile-meta">
      <h1><?= Helpers::e(trim(($profile['firstname'] ?? '') . ' ' . ($profile['lastname'] ?? ''))) ?></h1>
      <p><strong>@<?= Helpers::e($profile['username']) ?></strong></p>

      <?php if (!empty($profile['bio'])): ?>
        <p><?= nl2br(Helpers::e($profile['bio'])) ?></p>
      <?php endif; ?>

      <?php if (!empty($profile['github'])): ?>
  <p class="profile-link">
    🔗 <a href="<?= Helpers::e($profile['github']) ?>" target="_blank">
      <?= Helpers::e($profile['github']) ?>
    </a>
  </p>
<?php endif; ?>

        <?php if (!empty($profile['linkedin'])): ?>
  <p class="profile-link">
    🔗 <a href="<?= Helpers::e($profile['linkedin']) ?>" target="_blank">
      <?= Helpers::e($profile['linkedin']) ?>
    </a>
  </p>
<?php endif; ?>

      <p class="muted-line">
        <a href="/profile/<?= Helpers::e($profile['username']) ?>/followers">
          <strong><?= (int) $followersCount ?></strong> followers
        </a>
        ·
        <a href="/profile/<?= Helpers::e($profile['username']) ?>/following">
          <strong><?= (int) $followingCount ?></strong> following
        </a>
        ·
        <strong><?= !empty($profile['is_private']) ? 'Private' : 'Public' ?></strong>
      </p>

      <div class="profile-actions">
  <?php if (!empty($me) && (int) $me['id'] === (int) $profile['id']): ?>
    <a href="/profile/edit" class="button button-primary">✎ Edit profile</a>
<a href="/profile/drafts" class="button button-outline">📝 Drafts</a>
  <?php else: ?>
    <form action="/follow/<?= (int) $profile['id'] ?>" method="POST" class="inline-form">
      <button
        type="submit"
        class="button button-outline"
        data-follow-button
        data-user-id="<?= (int) $profile['id'] ?>"
        data-following="<?= !empty($isFollowing) ? '1' : '0' ?>"
        data-pending="<?= !empty($hasPendingRequest) ? '1' : '0' ?>"
        data-private="<?= !empty($profile['is_private']) ? '1' : '0' ?>"
      >
        <?php if (!empty($isFollowing)): ?>
          ➖ Unfollow
        <?php elseif (!empty($hasPendingRequest)): ?>
          🕓 Requested
        <?php else: ?>
          ➕ Follow
        <?php endif; ?>
      </button>
    </form>

    <?php if (!empty($canMessage) && empty($isBlockedByMe)): ?>
  <a href="/messages?with=<?= Helpers::e($profile['username']) ?>" class="button button-outline">
  ✉️ Message
</a>
<?php endif; ?>

    <?php if (!empty($isBlockedByMe)): ?>
      <form action="/messages/unblock/<?= Helpers::e($profile['username']) ?>" method="POST" class="inline-form">
        <button type="submit" class="button button-outline">⭕️ Unblock</button>
      </form>
    <?php else: ?>
      <form
        action="/messages/block/<?= Helpers::e($profile['username']) ?>"
        method="POST"
        class="inline-form"
        data-confirm="Block this user and remove follow links?"
      >
        <button type="submit" class="button button-outline danger-outline">🚫 Block</button>
      </form>
    <?php endif; ?>
  <?php endif; ?>
</div>
</section>

<?php if (!empty($requests)): ?>
  <section class="panel">
    <h2>Follow requests</h2>

    <div class="requests-list">
      <?php foreach ($requests as $request): ?>
        <article class="request-card" data-request-id="<?= (int) $request['id'] ?>">
          <div class="request-head">
            <div class="request-user">
              <?php if (!empty($request['icon'])): ?>
                <img class="avatar request-avatar" src="<?= Helpers::e($request['icon']) ?>" alt="Avatar de <?= Helpers::e($request['username']) ?>">
              <?php else: ?>
                <div class="avatar request-avatar avatar-fallback">
                  <?= strtoupper(substr((string) $request['username'], 0, 1)) ?>
                </div>
              <?php endif; ?>

              <div>
                <strong><?= Helpers::e(trim(($request['firstname'] ?? '') . ' ' . ($request['lastname'] ?? ''))) ?></strong>
                <div><a href="/profile/<?= Helpers::e($request['username']) ?>">@<?= Helpers::e($request['username']) ?></a></div>
              </div>
            </div>

            <div class="request-actions">
              <button type="button" class="button button-primary" data-request-accept="<?= (int) $request['id'] ?>">Accept ✅</button>
              <button type="button" class="button button-outline" data-request-reject="<?= (int) $request['id'] ?>">Reject ❌</button>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<?php if (!$canViewContent): ?>
  <section class="panel">
    <h2>This account is private</h2>
    <p>Follow this user and wait for approval to see Tweets, Replies and Reposts.</p>
  </section>
<?php else: ?>
  <section class="panel">
    <h2>Tweets</h2>
    <?php if (!empty($tweets)): ?>
      <?php $feedContext = 'profile'; ?>
      <?php foreach ($tweets as $tweet): ?>
        <?php include __DIR__ . '/../partials/tweet-card.php'; ?>
      <?php endforeach; ?>
    <?php else: ?>
      <p>Aucun tweet pour le moment.</p>
    <?php endif; ?>
  </section>

  <section class="panel">
    <h2>Replies</h2>
    <?php if (!empty($replies)): ?>
      <?php $feedContext = 'profile'; ?>
      <?php foreach ($replies as $tweet): ?>
        <?php include __DIR__ . '/../partials/tweet-card.php'; ?>
      <?php endforeach; ?>
    <?php else: ?>
      <p>Aucune reply pour le moment.</p>
    <?php endif; ?>
  </section>

  <section class="panel">
    <h2>Reposts</h2>
    <?php if (!empty($reposts)): ?>
      <?php $feedContext = 'profile'; ?>
      <?php foreach ($reposts as $tweet): ?>
        <?php include __DIR__ . '/../partials/tweet-card.php'; ?>
      <?php endforeach; ?>
    <?php else: ?>
      <p>Aucun repost pour le moment.</p>
    <?php endif; ?>
  </section>
<?php endif; ?>