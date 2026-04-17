<?php use App\Core\Helpers; ?>

<section class="panel">
  <h1>Drafts</h1>

  <?php if (empty($drafts)): ?>
    <p>No drafts yet.</p>
  <?php else: ?>
    <div class="requests-list">
      <?php foreach ($drafts as $draft): ?>
        <article class="request-card">
          <div class="request-head" style="display:block;">
            <div style="margin-bottom: 1rem;">
              <p><?= nl2br(Helpers::e((string) ($draft['body'] ?? ''))) ?></p>

              <?php if (!empty($draft['image'])): ?>
                <img class="tweet-image" src="<?= Helpers::e($draft['image']) ?>" alt="Draft image">
              <?php endif; ?>

              <p><strong>Reply setting:</strong> <?= Helpers::e((string) ($draft['reply_permission'] ?? 'everyone')) ?></p>
              <small>Updated: <?= Helpers::formatDate((string) $draft['updated_at']) ?></small>
            </div>

            <div class="request-actions">
              <form action="/tweets/drafts/<?= (int) $draft['id'] ?>/publish" method="POST" class="inline-form">
                <button type="submit" class="button button-primary">Publish</button>
              </form>

              <form action="/tweets/drafts/<?= (int) $draft['id'] ?>/delete" method="POST" class="inline-form" data-confirm="Delete this draft?">
                <button type="submit" class="button button-outline danger-outline">Delete</button>
              </form>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>