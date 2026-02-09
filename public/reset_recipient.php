<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
// public/reset_recipient.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/util.php';
session_start();
require_once __DIR__ . '/../config/i18n.php';
require_login();

// --- helpers ---
function gen_pwd(): string {
  $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
  $out = '';
  for ($i = 0; $i < 6; $i++) $out .= $alphabet[random_int(0, strlen($alphabet)-1)];
  return $out;
}

// --- input ---
$code = $_GET['code'] ?? '';
if (!$code) { http_response_code(400); exit('Missing code'); }

// --- fetch file & authorize ---
$s = $pdo->prepare('SELECT id, owner_id FROM files WHERE short_code=?');
$s->execute([$code]);
$f = $s->fetch();
if (!$f) { http_response_code(404); exit('Not found'); }
if ((int)$f['owner_id'] !== current_user_id()) { http_response_code(403); exit('Forbidden'); }

// --- rotate recipient password ---
$new = gen_pwd();
$u = $pdo->prepare('UPDATE files SET recipient_pwd_hash=?, recipient_pwd_plain=? WHERE id=?');
$u->execute([password_hash($new, PASSWORD_DEFAULT), $new, $f['id']]);

// Build viewer link
$host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8080';
$base = 'http://' . $host;
$viewerUrl = $base . '/viewer.php?code=' . rawurlencode($code);
?>
<!doctype html>
<html data-theme="light">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars(t('reset_recipient.title')); ?> – <?php echo htmlspecialchars($code); ?></title>
  <link rel="stylesheet" href="https://unpkg.com/@picocss/pico@latest/css/pico.min.css">
  <link rel="stylesheet" href="/static/app.css">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    .icon-btn{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;padding:0;border:0;background:transparent;cursor:pointer;border-radius:6px}
    .icon-btn:hover{background:rgba(0,0,0,.06)} .icon-btn:active{transform:translateY(1px)}
    .icon-btn i{font-size:18px;line-height:1}
  </style>
</head>
<body>
<?php include __DIR__ . '/partials/header.php'; ?>

<main class="container">
  <h1><?php echo htmlspecialchars(t('reset_recipient.updated_title')); ?></h1>

  <article>
    <header>
      <h2 style="margin:0"><?php echo htmlspecialchars(t('reset_recipient.share_header')); ?></h2>
      <p style="margin:0;color:var(--muted-color)"><?php echo htmlspecialchars(t('reset_recipient.file_code')); ?>: <code><?php echo htmlspecialchars($code); ?></code></p>
    </header>

    <p style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
      <strong><?php echo htmlspecialchars(t('reset_recipient.recipient_link')); ?>:</strong>
      <code><?php echo htmlspecialchars($viewerUrl); ?></code>
      <button class="icon-btn copy" data-copy="<?php echo htmlspecialchars($viewerUrl); ?>" aria-label="<?php echo htmlspecialchars(t('reset_recipient.copy')); ?>" title="<?php echo htmlspecialchars(t('reset_recipient.copy')); ?>">
        <svg viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
      </button>
    </p>

    <p style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
      <strong><?php echo htmlspecialchars(t('reset_recipient.recipient_password')); ?>:</strong>
      <code id="pw"><?php echo htmlspecialchars($new); ?></code>
      <button class="icon-btn copy" data-copy="<?php echo htmlspecialchars($new); ?>" aria-label="<?php echo htmlspecialchars(t('reset_recipient.copy')); ?>" title="<?php echo htmlspecialchars(t('reset_recipient.copy')); ?>">
        <svg viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
      </button>
    </p>

    <p style="margin-top:1rem">
      <a class="btn secondary sm" href="/file.php?code=<?php echo urlencode($code); ?>" class="secondary"><?php echo htmlspecialchars(t('reset_recipient.back_to_file')); ?></a>
      <a class="btn ghost sm" href="/admin.php" class="secondary"><?php echo htmlspecialchars(t('reset_recipient.back_to_files')); ?></a>
    </p>
  </article>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>

<script>
document.addEventListener('click', async (e) => {
  const btn = e.target.closest('.copy');
  if (!btn) return;
  try {
    await navigator.clipboard.writeText(btn.dataset.copy || '');
    btn.classList.add('copied');
    setTimeout(() => btn.classList.remove('copied'), 1500);
  } catch (err) { alert('Kopieren fehlgeschlagen'); }
});
</script>
</body>
</html>
