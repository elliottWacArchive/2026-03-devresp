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
      <p class="muted-line">
        <?= $type === 'followers' ? 'Followers' : 'Following' ?>
      </p>
    </div>
  </div>
</section>

<?php if (!$canViewContent): ?>
  <section class="panel">
    <h2>This account is private</h2>
    <p>Follow this user and wait for approval to see this list.</p>
  </section>
<?php else: ?>
  <section class="panel">
    <h2><?= $type === 'followers' ? 'Followers' : 'Following' ?></h2>

    <?php if (!empty($usersList)): ?>
      <div class="connections-list">
        <?php foreach ($usersList as $item): ?>
          <article class="connection-card">
            <a class="search-result-link" href="/profile/<?= Helpers::e($item['username']) ?>">
              <?php if (!empty($item['icon'])): ?>
                <img class="avatar search-avatar" src="<?= Helpers::e($item['icon']) ?>" alt="Avatar de <?= Helpers::e($item['username']) ?>">
              <?php else: ?>
                <div class="avatar search-avatar avatar-fallback">
                  <?= strtoupper(substr((string) $item['username'], 0, 1)) ?>
                </div>
              <?php endif; ?>

              <div class="search-user-meta">
                <strong>@<?= Helpers::e($item['username']) ?></strong>
                <span><?= Helpers::e(trim(($item['firstname'] ?? '') . ' ' . ($item['lastname'] ?? ''))) ?></span>
              </div>
            </a>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p>No users to show.</p>
    <?php endif; ?>
  </section>
<?php endif; ?>