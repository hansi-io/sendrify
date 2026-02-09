<!--  SPDX-License-Identifier: MPL-2.0 -->
<?php
// public/partials/header.php
require_once __DIR__ . '/../../config/i18n.php';
require_once __DIR__ . '/../../config/demo.php';

$currentLang = i18n_current_lang();
$toggleLang  = ($currentLang === 'de') ? 'en' : 'de';
$toggleLabel = ($currentLang === 'de') ? t('nav.lang_en') : t('nav.lang_de');
$langHref = "/lang.php?set={$toggleLang}";

// Determine home link based on login status
if (!empty($_SESSION['user_id'])) {
    $homeLink = '/admin.php';
} elseif (is_demo_mode()) {
    $homeLink = '/account/login.php';
} else {
    $homeLink = '/upload.php';
}

// Current page for active state
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<header class="site-header">
  <nav class="container">
    <ul>
      <li>
        <a href="<?= $homeLink ?>" class="brand-link">
          <img src="/static/logo.svg" alt="<?= htmlspecialchars(t('app.name')) ?>" class="logo">
          <span class="beta-badge">Beta</span>
        </a>
      </li>
    </ul>

    <button class="menu-toggle" aria-label="Menu" id="menuToggle">
      <svg viewBox="0 0 24 24">
        <line x1="3" y1="6" x2="21" y2="6"></line>
        <line x1="3" y1="12" x2="21" y2="12"></line>
        <line x1="3" y1="18" x2="21" y2="18"></line>
      </svg>
    </button>

    <ul class="nav-menu" id="navMenu">
      <?php if (!is_demo_mode() || !empty($_SESSION['user_id'])): ?>
      <li>
        <a href="/upload.php" <?= $currentPage === 'upload.php' ? 'class="active"' : '' ?>>
          <?= htmlspecialchars(t('nav.upload')) ?>
        </a>
      </li>
      <?php endif; ?>

      <?php if (!empty($_SESSION['user_id'])): ?>
        <li>
          <a href="/admin.php" <?= $currentPage === 'admin.php' ? 'class="active"' : '' ?>>
            <strong><?= htmlspecialchars(t('nav.my_files')) ?></strong>
          </a>
        </li>
        <li>
          <a href="/account/logout.php">
            <?= htmlspecialchars(t('nav.logout')) ?>
          </a>
        </li>
      <?php else: ?>
        <li>
          <a href="/account/login.php" <?= $currentPage === 'login.php' ? 'class="active"' : '' ?>>
            <?= htmlspecialchars(t('nav.login')) ?>
          </a>
        </li>
        <?php if (!is_demo_mode()): ?>
        <li>
          <a href="/account/register.php" <?= $currentPage === 'register.php' ? 'class="active"' : '' ?>>
            <?= htmlspecialchars(t('nav.register')) ?>
          </a>
        </li>
        <?php endif; ?>
      <?php endif; ?>

      <li>
        <a href="<?= htmlspecialchars($langHref) ?>" title="<?= htmlspecialchars(t('nav.lang')) ?>">
          <?= htmlspecialchars($toggleLabel) ?>
        </a>
      </li>
    </ul>
  </nav>
</header>

<div class="menu-overlay"></div>

<script>
document.getElementById('menuToggle').addEventListener('click', function() {
  document.getElementById('navMenu').classList.toggle('active');
});
</script>
