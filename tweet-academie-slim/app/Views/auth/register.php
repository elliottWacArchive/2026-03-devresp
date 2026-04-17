<section class="auth-card">
  <h1>Inscription</h1>
  <form action="/register" method="POST" class="row">
    <div class="six columns">
      <label for="username">Pseudo</label>
      <input class="u-full-width" type="text" id="username" name="username" required>
    </div>
    <div class="six columns">
      <label for="email">Email</label>
      <input class="u-full-width" type="email" id="email" name="email" required>
    </div>
    <div class="six columns">
      <label for="firstname">Prénom</label>
      <input class="u-full-width" type="text" id="firstname" name="firstname" required>
    </div>
    <div class="six columns">
      <label for="lastname">Nom</label>
      <input class="u-full-width" type="text" id="lastname" name="lastname" required>
    </div>
    <div class="six columns">
      <label for="birthdate">Date de naissance</label>
      <input class="u-full-width" type="date" id="birthdate" name="birthdate" required>
    </div>
    <div class="six columns">
      <label for="password">Mot de passe</label>
      <input class="u-full-width" type="password" id="password" name="password" required>
    </div>
    <div class="twelve columns">
      <button type="submit" class="button-primary">Créer mon compte</button>
    </div>
  </form>
</section>
