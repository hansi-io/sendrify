<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
require_once __DIR__ . '/../../config/i18n.php';
?>
<footer class="site-footer">
  <div class="container">
    <nav>
      <span style="color: var(--gray-400); font-size: 0.875rem;">
        © <?= date('Y') ?> Sendrify
      </span>
      <div style="display: flex; gap: 1.5rem;">
        <a href="/imprint.html"><?= htmlspecialchars(t('nav.imprint')) ?></a>
        <a href="/privacy.html"><?= htmlspecialchars(t('nav.privacy')) ?></a>
      </div>
    </nav>
  </div>
</footer>
