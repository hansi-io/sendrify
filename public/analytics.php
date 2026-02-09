<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
// public/analytics.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/util.php';
@session_start();

// (optional) i18n if you added it
if (is_file(__DIR__ . '/../config/i18n.php')) {
  require_once __DIR__ . '/../config/i18n.php';
} else {
  // tiny safe stubs so this page works without i18n.php present
  if (!function_exists('t')) { function t($k){ return $k; } }
  if (!function_exists('i18n_current_lang')) { function i18n_current_lang(){ return 'en'; } }
}

$code = $_GET['code'] ?? '';
if (!$code) { http_response_code(400); exit('Missing code'); }

// 1) Require login
if (empty($_SESSION['user_id'])) {
  $next = $_SERVER['REQUEST_URI'] ?? ('/analytics.php?code=' . rawurlencode($code));
  header('Location: /account/login.php?next=' . urlencode($next));
  exit;
}

// 2) Get file and enforce ownership / claim
$stmt = $pdo->prepare('SELECT id, owner_id, short_code, file_path, original_filename FROM files WHERE short_code=? LIMIT 1');
$stmt->execute([$code]);
$file = $stmt->fetch();
if (!$file) { http_response_code(404); exit('Not found'); }

// Use original_filename if available, otherwise fallback to extracting from file_path
if (!empty($file['original_filename'])) {
  $filename = $file['original_filename'];
} else {
  // Fallback for old files: extract from path
  $filename = basename($file['file_path'], '.pdf');
  // Remove leading short code prefix (e.g., "abc123_")
  if (preg_match('/^[A-Za-z0-9]{6}_(.+)$/', $filename, $matches)) {
    $filename = str_replace('_', ' ', $matches[1]);
  } elseif (preg_match('/^[A-Za-z0-9]{6}$/', $filename)) {
    $filename = t('viewer.title');
  }
}

$uid = (int)$_SESSION['user_id'];
$owned = !empty($file['owner_id']) && (int)$file['owner_id'] === $uid;

if (!$owned) {
  // Show claim gate (sender password needed) — user is logged in at this point
  ?>
  <!doctype html>
  <html>
  <head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Claim file</title>
    <link rel="stylesheet" href="https://unpkg.com/@picocss/pico@latest/css/pico.min.css">
    <style>
      .center { min-height:100vh; display:grid; place-items:center; padding:24px; }
      .card { width:min(520px, 92vw); background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:22px; box-shadow:0 10px 30px rgba(0,0,0,.06); }
      .error{ background:#fee2e2; color:#991b1b; padding:.6rem .8rem; border-radius:8px; margin:.75rem 0; }
      .row{ display:flex; gap:.5rem; }
      input[type=password]{ width:100%; padding:.7rem .9rem; border:1px solid #d1d5db; border-radius:10px; }
      button{ padding:.7rem 1rem; border:0; border-radius:10px; background:#111827; color:#fff; font-weight:600; cursor:pointer; }
      code{ background:#f3f4f6; padding:.15rem .4rem; border-radius:6px; }
    </style>
  </head>
  <body>
    <main class="center">
      <section class="card">
        <h1 style="margin:.25rem 0 1rem">Claim this file to view Analytics</h1>
        <p style="margin:.25rem 0 .75rem">File code: <code><?php echo  htmlspecialchars($file['short_code']) ?></code></p>
        <p style="margin:.25rem 0 .75rem">Enter the <strong>sender password</strong> to attach this file to your account.</p>
        <?php if (!empty($_GET['e'])): ?><div class="error">Wrong password. Try again.</div><?php endif; ?>
        <form method="post" action="/claim.php" class="row">
          <input type="hidden" name="code" value="<?php echo  htmlspecialchars($code) ?>">
          <input type="password" name="sender_password" placeholder="Sender password" required>
          <button type="submit">Claim &amp; continue</button>
        </form>
        <p style="margin-top:1rem"><a href="/upload">Back</a></p>
      </section>
    </main>
  </body>
  </html>
  <?php
  exit;
}

// ---- Owned by current user: render Analytics ----
$fileId = (int)$file['id'];

// Summary stats
$sum = $pdo->prepare("
  SELECT
    COUNT(*)                         AS sessions,
    ROUND(AVG(max_scroll_pct))       AS avg_scroll,
    MAX(max_scroll_pct)              AS best_scroll,
    ROUND(AVG(time_spent_ms)/1000)   AS avg_time_sec
  FROM page_views WHERE file_id = ?
");
$sum->execute([$fileId]);
$S = $sum->fetch(PDO::FETCH_ASSOC) ?: ['sessions'=>0,'avg_scroll'=>null,'best_scroll'=>null,'avg_time_sec'=>null];

// Latest session scroll (quick sanity)
$latest = $pdo->prepare("
  SELECT max_scroll_pct, time_spent_ms, started_at
  FROM page_views
  WHERE file_id = ?
  ORDER BY started_at DESC
  LIMIT 1
");
$latest->execute([$fileId]);
$L = $latest->fetch(PDO::FETCH_ASSOC);

// Sessions table
$list = $pdo->prepare("
  SELECT session_id, started_at, time_spent_ms, max_scroll_pct
  FROM page_views
  WHERE file_id = ?
  ORDER BY started_at DESC
  LIMIT 200
");
$list->execute([$fileId]);
$rows = $list->fetchAll(PDO::FETCH_ASSOC);

?>
<!doctype html>
<html data-theme="light">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Analytics: <?= htmlspecialchars($filename) ?> – Sendrify</title>
  <link rel="stylesheet" href="https://unpkg.com/@picocss/pico@latest/css/pico.min.css">
  <link rel="stylesheet" href="/static/app.css">
</head>
<body>
<?php include __DIR__ . '/partials/header.php'; ?>

<main class="container">
  <div class="animate-fade-in">
    <div style="margin-bottom: var(--space-xl);">
      <a href="/admin.php" style="display: inline-flex; align-items: center; gap: var(--space-xs); font-size: 0.875rem; color: var(--gray-500); margin-bottom: var(--space-sm);">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M19 12H5M12 19l-7-7 7-7"/>
        </svg>
        <?= htmlspecialchars(t('admin.title')) ?>
      </a>
      <h1 style="margin-bottom: var(--space-xs);"><?= htmlspecialchars($filename) ?></h1>
      <p class="text-muted" style="margin: 0;">
        <code style="background: var(--gray-100); padding: 0.2em 0.5em; border-radius: var(--radius-sm);"><?= htmlspecialchars($file['short_code']) ?></code>
        · <?= htmlspecialchars(t('analytics.metrics')) ?>
      </p>
    </div>

    <section class="stats stagger">
      <div class="stat">
        <div class="stat-value"><?= (int)$S['sessions'] ?></div>
        <div class="stat-label"><?= htmlspecialchars(t('analytics.sessions')) ?></div>
      </div>
      <div class="stat">
        <div class="stat-value"><?= $S['avg_scroll'] !== null ? (int)$S['avg_scroll'].'%' : '–' ?></div>
        <div class="stat-label"><?= htmlspecialchars(t('analytics.avg_scroll')) ?></div>
      </div>
      <div class="stat">
        <div class="stat-value"><?= $L ? (int)$L['max_scroll_pct'].'%' : '–' ?></div>
        <div class="stat-label"><?= htmlspecialchars(t('analytics.latest_scroll')) ?></div>
      </div>
      <div class="stat">
        <div class="stat-value"><?= $S['best_scroll'] !== null ? (int)$S['best_scroll'].'%' : '–' ?></div>
        <div class="stat-label"><?= htmlspecialchars(t('analytics.best_scroll')) ?></div>
      </div>
    </section>

    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--space-md); margin: var(--space-xl) 0;">
      <div class="stat" style="flex: 1; min-width: 200px;">
        <div class="stat-label" style="margin-bottom: var(--space-xs);"><?= htmlspecialchars(t('analytics.av_time')) ?></div>
        <div class="stat-value"><?= $S['avg_time_sec'] !== null ? htmlspecialchars(format_duration((float)$S['avg_time_sec'])) : '–' ?></div>
      </div>
      <div style="display: flex; gap: var(--space-sm);">
        <a class="btn secondary sm" href="/file.php?code=<?= urlencode($file['short_code']) ?>"><?= htmlspecialchars(t('analytics.settings')) ?></a>
        <a class="btn ghost sm" href="/reset_analytics.php?code=<?= urlencode($file['short_code']) ?>" onclick="return confirm('Clear all analytics for this file?')" style="color: var(--error);"><?= htmlspecialchars(t('analytics.reset_analytics')) ?></a>
      </div>
    </div>

    <h2 style="margin-top: var(--space-2xl);"><?= htmlspecialchars(t('analytics.sessions')) ?></h2>
    <div class="table-responsive">
      <table role="grid" class="table-cards">
        <thead>
          <tr>
            <th><?= htmlspecialchars(t('analytics.started')) ?></th>
            <th><?= htmlspecialchars(t('analytics.time')) ?></th>
            <th><?= htmlspecialchars(t('analytics.max_scroll')) ?></th>
            <th><?= htmlspecialchars(t('analytics.session')) ?></th>
          </tr>
        </thead>
        <tbody class="stagger">
          <?php if (!$rows): ?>
            <tr><td colspan="4" style="text-align: center; padding: var(--space-xl);"><em class="text-muted"><?= htmlspecialchars(t('analytics.no_sessions')) ?></em></td></tr>
          <?php else: foreach ($rows as $r): ?>
            <?php
              $secs = (int)floor(($r['time_spent_ms'] ?? 0)/1000);
              $pretty = format_duration($secs);
            ?>
            <tr>
              <td data-label="<?= htmlspecialchars(t('analytics.started')) ?>"><?= htmlspecialchars(date('d.m.Y H:i', strtotime($r['started_at']))) ?></td>
              <td data-label="<?= htmlspecialchars(t('analytics.time')) ?>" title="<?= $secs ?>s"><?= htmlspecialchars($pretty) ?></td>
              <td data-label="<?= htmlspecialchars(t('analytics.max_scroll')) ?>"><strong style="color: var(--brand-navy);"><?= (int)$r['max_scroll_pct'] ?>%</strong></td>
              <td data-label="<?= htmlspecialchars(t('analytics.session')) ?>"><code><?= htmlspecialchars(substr($r['session_id'], 0, 12)) ?></code></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <p class="text-muted text-small" style="margin-top: var(--space-lg);"><?= htmlspecialchars(t('analytics.refresh')) ?></p>
  </div>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>

<script>
// simple auto reload every 10s (disable with ?live=0)
if (!/[?&]live=0/.test(location.search)) {
  setInterval(()=> location.reload(), 10000);
}
</script>
</body>
</html>
