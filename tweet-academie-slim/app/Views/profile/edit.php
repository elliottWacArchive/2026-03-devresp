<?php use App\Core\Helpers; ?>

<section class="panel">
  <h1>Modifier mon profil</h1>

  <form action="/profile/edit" method="POST" enctype="multipart/form-data" class="row">
    <div class="six columns">
      <label for="firstname">Prénom</label>
      <input class="u-full-width" id="firstname" name="firstname" type="text" value="<?= Helpers::e($profile['firstname']) ?>" required>
    </div>

    <div class="six columns">
      <label for="lastname">Nom</label>
      <input class="u-full-width" id="lastname" name="lastname" type="text" value="<?= Helpers::e($profile['lastname']) ?>" required>
    </div>

    <div class="twelve columns">
      <label for="bio">Bio</label>
      <textarea class="u-full-width" id="bio" name="bio"><?= Helpers::e($profile['bio']) ?></textarea>
    </div>

    <div class="six columns">
      <label for="github">GitHub</label>
      <input class="u-full-width" id="github" name="github" type="url" value="<?= Helpers::e($profile['github']) ?>">
    </div>

    <div class="six columns">
      <label for="linkedin">LinkedIn</label>
      <input class="u-full-width" id="linkedin" name="linkedin" type="url" value="<?= Helpers::e($profile['linkedin']) ?>">
    </div>

    <div class="six columns">
      <label for="icon">Avatar</label>
      <input id="icon" class="input-file-dark" name="icon" type="file" accept="image/*">
    </div>

    <div class="six columns">
      <label for="banner">Bannière</label>
      <input id="banner" class="input-file-dark" name="banner" type="file" accept="image/*">
    </div>

    <div class="twelve columns">
      <label class="checkbox-line">
        <input type="checkbox" name="is_private" value="1" <?= !empty($profile['is_private']) ? 'checked' : '' ?>>
        Private account
      </label>
    </div>

    <div class="twelve columns">
    <label class="checkbox-line">
  <input
    type="checkbox"
    name="allow_dm_from_anyone"
    value="1"
    <?= !empty($profile['allow_dm_from_anyone']) ? 'checked' : '' ?>
  >
  <span>Allow message requests from everyone</span>
</label>
</div>

    <div class="twelve columns">
      <button type="submit" class="button-primary">Enregistrer</button>
    </div>
  </form>
</section>