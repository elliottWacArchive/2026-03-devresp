<?php use App\Core\Helpers; ?>
<section class="panel">
  <h1>#<?= Helpers::e($tag) ?></h1>
  <?php if (empty($tweets)): ?>
    <p>Aucun tweet trouvé pour ce hashtag.</p>
  <?php else: ?>
    <?php foreach ($tweets as $tweet): ?>
      <?php include __DIR__ . '/../partials/tweet-card.php'; ?>
    <?php endforeach; ?>
  <?php endif; ?>
</section>
