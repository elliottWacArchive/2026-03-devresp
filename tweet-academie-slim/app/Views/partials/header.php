<?php
use App\Core\Auth;
use App\Core\Helpers;

$title = $title ?? 'Tweet Académie';
$notificationUnreadCount = $notificationUnreadCount ?? 0;
$messageUnreadCount = $messageUnreadCount ?? 0;

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= Helpers::e($title) ?></title>
  <meta name="description" content="Tweet Académie - réseau social étudiant">
  <meta name="robots" content="index, follow">

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/skeleton/2.0.4/skeleton.min.css">
  <link rel="stylesheet" href="/assets/css/app.css">

  <script src="/assets/js/app.js"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>

<body>
  <div class="app-shell">

    <aside class="app-sidebar" id="app-sidebar" aria-label="Navigation mobile">
      <div class="sidebar-inner">
        <a href="/" class="brand sidebar-brand">Tweet Académie</a>

        <nav class="sidebar-nav">
          <?php if (Auth::check()): ?>
            <a href="/" class="sidebar-link">🏠 <span>Accueil</span></a>

            <a href="/messages" class="sidebar-link nav-link-with-badge">
              ✉️ <span>Messages</span>
              <?php if ($messageUnreadCount > 0): ?>
                <span class="nav-badge">
                  <?= $messageUnreadCount > 99 ? '99+' : $messageUnreadCount ?>
                </span>
              <?php endif; ?>
            </a>

            <a href="/notifications" class="sidebar-link nav-link-with-badge">
              🔔 <span>Notifications</span>
              <?php if ($notificationUnreadCount > 0): ?>
                <span class="nav-badge">
                  <?= $notificationUnreadCount > 99 ? '99+' : $notificationUnreadCount ?>
                </span>
              <?php endif; ?>
            </a>

            <a href="/profile/<?= Helpers::e(Auth::user()['username']) ?>" class="sidebar-link">👤 <span>Mon profil</span></a>
            <a href="/profile/edit" class="sidebar-link">⚙️ <span>Modifier</span></a>

            <form action="/logout" method="POST" class="sidebar-form">
              <button type="submit" class="button button-outline sidebar-logout">Déconnexion</button>
            </form>
          <?php else: ?>
            <a href="/login" class="sidebar-link">Connexion</a>
            <a href="/register" class="sidebar-link">Inscription</a>
          <?php endif; ?>
        </nav>
      </div>
    </aside>

    <div class="sidebar-backdrop" id="sidebar-backdrop"></div>

    <div class="app-main">

      <header class="site-header">
        <div class="container">
          <div class="row nav-row">
            <div class="four columns">
              <a href="/" class="brand">Tweet Académie</a>
            </div>

            <nav class="eight columns nav-links" aria-label="Navigation principale">
              <?php if (Auth::check()): ?>

                <a href="/">🏠 <span>Accueil</span></a>

                <a href="/messages" class="nav-link-with-badge">
                  ✉️ <span>Messages</span>
                  <?php if ($messageUnreadCount > 0): ?>
                    <span class="nav-badge">
                      <?= $messageUnreadCount > 99 ? '99+' : $messageUnreadCount ?>
                    </span>
                  <?php endif; ?>
                </a>

                <a href="/notifications" class="nav-link-with-badge">
                  🔔 <span>Notifications</span>
                  <?php if ($notificationUnreadCount > 0): ?>
                    <span class="nav-badge">
                      <?= $notificationUnreadCount > 99 ? '99+' : $notificationUnreadCount ?>
                    </span>
                  <?php endif; ?>
                </a>

                <a href="/profile/<?= Helpers::e(Auth::user()['username']) ?>">👤 <span>Mon profil</span></a>
                <a href="/profile/edit">⚙️ <span>Modifier</span></a>

                <form action="/logout" method="POST" class="inline-form">
                  <button type="submit" class="button button-outline">Déconnexion</button>
                </form>

              <?php else: ?>

                <a href="/login">Connexion</a>
                <a href="/register">Inscription</a>

              <?php endif; ?>
            </nav>
          </div>
        </div>
      </header>

      <header class="mobile-topbar">
        <button
          type="button"
          class="sidebar-toggle"
          id="sidebar-toggle"
          aria-controls="app-sidebar"
          aria-expanded="false"
          aria-label="Ouvrir le menu"
        >
          ☰
        </button>

        <a href="/" class="brand mobile-brand">Tweet Académie</a>
      </header>

      <main class="container main-content" id="main-content">
        <?php if ($flashSuccess || $flashError): ?>
          <div
            id="toast-notification"
            class="toast-notification <?= $flashError ? 'toast-error' : 'toast-success' ?>"
            role="status"
            aria-live="polite"
          >
            <?= Helpers::e($flashError ?: $flashSuccess) ?>
          </div>
        <?php endif; ?>