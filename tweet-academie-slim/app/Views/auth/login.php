<section class="auth-card auth-narrow">
  <h1>Connexion</h1>
  <form action="/login" method="POST">
    <label for="email">Email</label>
    <input class="u-full-width" type="email" id="email" name="email" required>

    <label for="password">Mot de passe</label>
    <input class="u-full-width" type="password" id="password" name="password" required>

    <button type="submit" class="button-primary">Se connecter</button>
  </form>
  <p>Pas encore de compte ? <a href="/register">Créer un compte</a></p>
</section>
