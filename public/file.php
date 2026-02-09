<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
// public/file.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/util.php';
session_start();
require_once __DIR__ . '/../config/i18n.php';
require_login();

$code = $_GET['code'] ?? '';
$stmt = $pdo->prepare('
  SELECT id, short_code, owner_id, file_path, original_filename,
         recipient_pwd_plain, sender_pwd_plain
  FROM files WHERE short_code = ?
');
$stmt->execute([$code]);
$f = $stmt->fetch();

if (!$f) { http_response_code(404); exit('Not found'); }
if ((int)$f['owner_id'] !== current_user_id()) { http_response_code(403); exit('Forbidden'); }

// Use original_filename if available, otherwise fallback to extracting from file_path
if (!empty($f['original_filename'])) {
  $filename = $f['original_filename'];
} else {
  // Fallback for old files: extract from path
  $filename = basename($f['file_path'], '.pdf');
  // Remove leading short code prefix (e.g., "abc123_")
  if (preg_match('/^[A-Za-z0-9]{6}_(.+)$/', $filename, $matches)) {
    $filename = str_replace('_', ' ', $matches[1]);
  } elseif (preg_match('/^[A-Za-z0-9]{6}$/', $filename)) {
    $filename = t('viewer.title');
  }
}

$host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8080';
$base = 'http://' . $host;

/* If you already added pretty routes, switch these two lines:
$viewerUrl    = $base . '/viewer/'    . $f['short_code'];
$analyticsUrl = $base . '/analytics/' . $f['short_code'];
*/
$viewerUrl    = $base . '/viewer.php?code='    . $f['short_code'];
$analyticsUrl = $base . '/analytics.php?code=' . $f['short_code'];
?>
<!doctype html>
<html data-theme="light">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo  htmlspecialchars($filename) ?> – Sendrify</title>
  <link rel="stylesheet" href="https://unpkg.com/@picocss/pico@latest/css/pico.min.css">
  <link rel="stylesheet" href="/static/app.css">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<?php include __DIR__ . '/partials/header.php'; ?>

<main class="container">
  <h1><?php echo  htmlspecialchars($filename) ?> <small style="font-weight:normal">(<code><?php echo  htmlspecialchars($f['short_code']) ?></code>)</small></h1>

  <article>
    <header>
      <h2 style="margin:0"><?php echo  htmlspecialchars(t('file.share_header')) ?></h2>
      <p style="margin:0;color:var(--muted-color)"><?php echo  htmlspecialchars(t('file.share_description')) ?></p>
    </header>

    <div class="grid">
      <!-- Recipient column -->
      <div>
        <h3><?php echo  htmlspecialchars(t('file.recipient')) ?></h3>

        <p class="copyline">
          <strong>Link:</strong>
          <span class="copyline-field">
            <code title="<?php echo  htmlspecialchars($viewerUrl) ?>"><?php echo  htmlspecialchars($viewerUrl) ?></code>
            <button type="button" class="icon-btn copy-icon"
                    data-copy="<?php echo  htmlspecialchars($viewerUrl) ?>"
                    aria-label="Copy recipient link" title="Copy">
              <svg viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
            </button>
          </span>
        </p>

        <p class="copyline">
          <strong><?php echo  htmlspecialchars(t('file.password')) ?></strong>
          <span class="copyline-field">
            <?php if (!empty($f['recipient_pwd_plain'])): ?>
              <code><?php echo  htmlspecialchars($f['recipient_pwd_plain']) ?></code>
              <button type="button" class="icon-btn copy-icon"
                      data-copy="<?php echo  htmlspecialchars($f['recipient_pwd_plain']) ?>"
                      aria-label="Copy recipient password" title="Copy">
                <svg viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
              </button>
            <?php else: ?>
              <em><?php echo  htmlspecialchars(t('analytics.not_available')) ?></em> — <a href="/reset_recipient.php?code=<?php echo  urlencode($f['short_code']) ?>"><?php echo  htmlspecialchars(t('analytics.reset_to_generate')) ?></a>
            <?php endif; ?>
          </span>
        </p>
      </div>

      <!-- Analytics column -->
      <div>
        <h3><?php echo  htmlspecialchars(t('file.analytics')) ?></h3>

        <p class="copyline">
          <strong><?php echo  htmlspecialchars(t('upload.link')) ?>:</strong>
          <span class="copyline-field">
            <code title="<?php echo  htmlspecialchars($analyticsUrl) ?>"><?php echo  htmlspecialchars($analyticsUrl) ?></code>
            <button type="button" class="icon-btn copy-icon"
                    data-copy="<?php echo  htmlspecialchars($analyticsUrl) ?>"
                    aria-label="Copy analytics link" title="Copy">
              <svg viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
            </button>
          </span>
        </p>

        <p class="copyline">
          <strong><?php echo  htmlspecialchars(t('file.password')) ?></strong>
          <span class="copyline-field">
            <?php if (!empty($f['sender_pwd_plain'])): ?>
              <code><?php echo  htmlspecialchars($f['sender_pwd_plain']) ?></code>
              <button type="button" class="icon-btn copy-icon"
                      data-copy="<?php echo  htmlspecialchars($f['sender_pwd_plain']) ?>"
                      aria-label="Copy sender password" title="Copy">
                <svg viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
              </button>
            <?php else: ?>
              <em><?php echo  htmlspecialchars(t('analytics.not_available')) ?></em> — <a href="/reset_sender.php?code=<?php echo  urlencode($f['short_code']) ?>"><?php echo  htmlspecialchars(t('analytics.reset_to_generate')) ?></a>
            <?php endif; ?>
          </span>
        </p>
      </div>
    </div>

    <p style="margin-top:1rem">
      <a class="btn secondary sm" href="/reset_recipient.php?code=<?php echo  urlencode($f['short_code']) ?>"><?php echo  htmlspecialchars(t('admin.reset_pw')) ?></a> <a class="btn ghost sm" href="/delete.php?code=<?php echo  urlencode($f['short_code']) ?>" onclick="return confirm('<?php echo  htmlspecialchars(t('analytics.confirm_delete')) ?>')" style="color: var(--error);"><?php echo  htmlspecialchars(t('admin.delete')) ?></a>
    </p>
  </article>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>

<script>
document.addEventListener('click', async (e) => {
  const btn = e.target.closest('.copy-icon');
  if (!btn) return;
  try {
    await navigator.clipboard.writeText(btn.dataset.copy || '');
    btn.classList.add('copied');
    setTimeout(() => btn.classList.remove('copied'), 1500);
  } catch { alert('Kopieren fehlgeschlagen'); }
});
</script>
</body>
</html>
