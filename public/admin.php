<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/util.php';
session_start();
require_once __DIR__ . '/../config/i18n.php';
require_login();
$uid = (int)$_SESSION['user_id'];

$q = $pdo->prepare("
  SELECT f.id, f.short_code, f.original_filename, f.created_at,
    (SELECT COUNT(*) FROM page_views pv WHERE pv.file_id=f.id) AS unique_opens,
    (SELECT ROUND(AVG(pv.time_spent_ms)/1000,1) FROM page_views pv WHERE pv.file_id=f.id) AS avg_time_sec,
    (SELECT ROUND(AVG(pv.max_scroll_pct),0) FROM page_views pv WHERE pv.file_id=f.id) AS avg_scroll
  FROM files f
  WHERE f.owner_id = ?
  ORDER BY f.created_at DESC
");
$q->execute([$uid]);
$rows = $q->fetchAll();
?>
<!doctype html>
<html data-theme="light">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars(t('admin.title')) ?> - Sendrify</title>
  <link rel="stylesheet" href="https://unpkg.com/@picocss/pico@latest/css/pico.min.css">
  <link rel="stylesheet" href="/static/app.css">
</head>
<body>
<?php include __DIR__ . '/partials/header.php'; ?>
<main class="container">
  <div class="animate-fade-in">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-xl); flex-wrap: wrap; gap: var(--space-md);">
      <h1 style="margin: 0;"><?= htmlspecialchars(t('admin.title')) ?></h1>
      <a href="/upload.php" class="btn accent">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M12 5v14M5 12h14"/>
        </svg>
        <?= htmlspecialchars(t('nav.upload')) ?>
      </a>
    </div>
    
    <?php if (!$rows): ?>
      <article style="text-align: center; padding: var(--space-3xl);">
        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="var(--gray-300)" stroke-width="1.5" style="margin-bottom: var(--space-lg);">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
          <polyline points="14,2 14,8 20,8"/>
        </svg>
        <h3 style="color: var(--gray-600); margin-bottom: var(--space-sm);"><?= htmlspecialchars(t('admin.no_files')) ?></h3>
        <p class="text-muted"><?= htmlspecialchars(t('admin.link_upload')) ?></p>
        <a href="/upload.php" class="btn" style="margin-top: var(--space-lg);">
          <?= htmlspecialchars(t('nav.upload')) ?>
        </a>
      </article>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table-cards">
          <thead>
            <tr>
              <th><?= htmlspecialchars(t('admin.filename')) ?></th>
              <th><?= htmlspecialchars(t('admin.created')) ?></th>
              <th><?= htmlspecialchars(t('admin.opens')) ?></th>
              <th><?= htmlspecialchars(t('admin.avg_time')) ?></th>
              <th><?= htmlspecialchars(t('admin.avg_scroll')) ?></th>
              <th><?= htmlspecialchars(t('admin.actions')) ?></th>
            </tr>
          </thead>
          <tbody class="stagger">
            <?php foreach ($rows as $r): ?>
              <tr>
                <td>
                  <strong><?= htmlspecialchars($r['original_filename'] ?: t('viewer.title')) ?></strong>
                </td>
                <td data-label="<?= htmlspecialchars(t('admin.created')) ?>">
                  <?= htmlspecialchars(date('d.m.Y H:i', strtotime($r['created_at']))) ?>
                </td>
                <td data-label="<?= htmlspecialchars(t('admin.opens')) ?>">
                  <strong style="color: var(--brand-navy);"><?= (int)$r['unique_opens'] ?></strong>
                </td>
                <td data-label="<?= htmlspecialchars(t('admin.avg_time')) ?>">
                  <?= $r['avg_time_sec'] !== null ? htmlspecialchars(format_duration((float)$r['avg_time_sec'])) : '–' ?>
                </td>
                <td data-label="<?= htmlspecialchars(t('admin.avg_scroll')) ?>">
                  <?= $r['avg_scroll'] !== null ? (int)$r['avg_scroll'].'%' : '–' ?>
                </td>
                <td class="actions">
                  <a class="btn secondary sm" href="/analytics.php?code=<?= urlencode($r['short_code']) ?>"><?= htmlspecialchars(t('admin.analytics')) ?></a>
                  <a class="btn ghost sm" href="/file.php?code=<?= urlencode($r['short_code']) ?>" style="color: var(--error);"><?= htmlspecialchars(t('admin.share_file')) ?></a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
