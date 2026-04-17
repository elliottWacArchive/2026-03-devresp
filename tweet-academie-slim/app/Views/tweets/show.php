<?php use App\Core\Helpers; ?>

<section class="panel">
  <h1>Tweet</h1>

  <?php if (!empty($tweet)): ?>
    <?php $feedContext = 'tweet'; ?>
    <?php include __DIR__ . '/../partials/tweet-card.php'; ?>
  <?php else: ?>
    <p>Tweet introuvable.</p>
  <?php endif; ?>
</section>