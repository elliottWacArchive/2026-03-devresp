<?php use App\Core\Helpers; ?>
<div class="row">
  <section class="seven columns panel">
    <h1>Messages privés</h1>
    <form action="/messages" method="POST">
      <label for="to_username">Envoyer à</label>
      <input class="u-full-width" type="text" id="to_username" name="to_username" placeholder="pseudo" required>

      <label for="message">Message</label>
      <textarea class="u-full-width" id="message" name="message" required></textarea>

      <button type="submit" class="button-primary">Envoyer</button>
    </form>
  </section>

  <aside class="five columns panel">
    <h2>Derniers messages</h2>
    <?php if (empty($messages)): ?>
      <p>Aucun message pour le moment.</p>
    <?php else: ?>
      <?php foreach ($messages as $message): ?>
        <article class="message-card">
          <p><strong>@<?= Helpers::e($message['from_username']) ?></strong></p>
          <p><?= Helpers::e($message['message']) ?></p>
          <small><?= Helpers::formatDate($message['created_at']) ?></small>
        </article>
      <?php endforeach; ?>
    <?php endif; ?>
  </aside>
</div>
