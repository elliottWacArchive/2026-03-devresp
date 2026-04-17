<?php use App\Core\Helpers; ?>

<div class="row home-layout">
  <aside class="three columns">
    <section class="panel side-panel home-sidebar">
      <h2 class="section-title">Votre espace</h2>

      <?php if (!empty($me['icon'])): ?>
        <img
          class="avatar home-account-avatar"
          src="<?= Helpers::e($me['icon']) ?>"
          alt="Avatar de <?= Helpers::e($me['username']) ?>"
          width="72"
          height="72"
          loading="lazy"
          decoding="async"
        >
      <?php else: ?>
        <div class="avatar avatar-fallback home-account-avatar" aria-hidden="true">
          <?= strtoupper(substr((string) $me['username'], 0, 1)) ?>
        </div>
      <?php endif; ?>

      <p class="account-username"><strong>@<?= Helpers::e($me['username']) ?></strong></p>
      <p class="muted-line account-name">
        <?= Helpers::e(trim(($me['firstname'] ?? '') . ' ' . ($me['lastname'] ?? ''))) ?>
      </p>
    </section>
  </aside>

  <section class="six columns home-main-column">
    <section class="panel compose-panel" x-data="tweetComposer()">
      <h1 class="page-title">Accueil</h1>

      <form action="/tweets" method="POST" enctype="multipart/form-data" class="reply-form">
        <input type="hidden" name="parent_id" value="">
        <input type="hidden" name="type" value="original">

        <label for="tweet" class="form-label">Nouveau post</label>
        <textarea
          id="tweet"
          name="tweet"
          class="u-full-width input-dark textarea-dark compose-textarea"
          maxlength="140"
          x-model="text"
          placeholder="Quoi de neuf ?"
          required
          rows="5"
        ></textarea>

        <div class="compose-meta">
          <span :class="remaining < 0 ? 'text-danger' : 'muted-line'">
            <strong x-text="remaining"></strong> caractères restants
          </span>
        </div>

        <label for="reply_permission" class="form-label">Qui peut répondre ?</label>
        <select id="reply_permission" name="reply_permission" class="u-full-width input-dark">
          <option value="everyone">Tout le monde</option>
          <option value="following">Les comptes que vous suivez</option>
          <option value="mentioned">Les comptes mentionnés</option>
          <option value="disabled">Personne</option>
        </select>

        <label for="image" class="form-label">Photo</label>
        <input type="file" id="image" name="image" accept="image/*" class="input-file-dark">

        <div class="compose-actions">
          <button
            type="submit"
            class="button button-primary action-pill"
            :disabled="remaining < 0 || text.length === 0"
          >
            Publier
          </button>

          <button
            type="submit"
            formaction="/tweets/drafts"
            class="button button-outline action-pill"
            :disabled="remaining < 0 || text.length === 0"
          >
            Enregistrer le brouillon
          </button>
        </div>
      </form>
    </section>

    <section class="panel timeline-panel">
      <div class="timeline-header">
        <h2 class="section-title">Fil d’actualité</h2>
        <button class="button button-outline action-pill" type="button" data-refresh-timeline>
          Actualiser le fil
        </button>
      </div>

      <div id="timeline" data-authenticated="1">
        <?php $feedContext = 'timeline'; ?>
        <?php foreach ($timeline as $tweet): ?>
          <?php include __DIR__ . '/../partials/tweet-card.php'; ?>
        <?php endforeach; ?>
      </div>
    </section>
  </section>

  <aside class="three columns">
    <section class="panel side-panel search-sidebar" x-data="mentionSearch()">
      <h3 class="section-title">Découvrir</h3>
      <label for="search-profile" class="form-label">Chercher un membre</label>

      <div class="search-box">
        <input
          id="search-profile"
          class="u-full-width input-dark"
          type="text"
          x-model="query"
          @input.debounce.300ms="search"
          placeholder="@pseudo ou nom"
          autocomplete="off"
        >

        <template x-if="loading">
          <div class="search-loading">Recherche...</div>
        </template>

        <template x-if="results.length">
          <ul class="search-results">
            <template x-for="user in results" :key="user.id">
              <li>
                <a class="search-result-link" :href="`/profile/${user.username}`">
                  <template x-if="user.icon">
                    <img
                      class="avatar search-avatar"
                      :src="user.icon"
                      :alt="`Avatar de ${user.username}`"
                      width="48"
                      height="48"
                      loading="lazy"
                      decoding="async"
                    >
                  </template>

                  <template x-if="!user.icon">
                    <div
                      class="avatar search-avatar avatar-fallback"
                      x-text="user.username ? user.username.charAt(0).toUpperCase() : '?'"
                    ></div>
                  </template>

                  <div class="search-user-meta">
                    <strong x-text="'@' + user.username"></strong>
                    <span x-text="`${user.firstname ?? ''} ${user.lastname ?? ''}`"></span>
                  </div>
                </a>
              </li>
            </template>
          </ul>
        </template>

        <template x-if="searched && !loading && !results.length">
          <div class="search-empty">Aucun profil trouvé.</div>
        </template>
      </div>
    </section>
  </aside>
</div>