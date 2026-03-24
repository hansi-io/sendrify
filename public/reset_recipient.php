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
$s = $pdo->prepare('SELECT id, owner_id, recipient_pwd_plain FROM files WHERE short_code=?');
$s->execute([$code]);
$f = $s->fetch();
if (!$f) { http_response_code(404); exit('Not found'); }
if ((int)$f['owner_id'] !== current_user_id()) { http_response_code(403); exit('Forbidden'); }

// Build viewer link
$host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8080';
$base = 'http://' . $host;
$viewerUrl = $base . '/viewer.php?code=' . rawurlencode($code);

$success = false;
$newPassword = null;
$noPassword = false;

// --- handle form submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!verify_csrf_token()) {
    http_response_code(403);
    exit('CSRF token validation failed');
  }
  
  $action = $_POST['password_action'] ?? 'none';
  $customPassword = trim($_POST['custom_password'] ?? '');
  
  if ($action === 'none') {
    // Remove password protection
    $u = $pdo->prepare('UPDATE files SET recipient_pwd_hash=NULL, recipient_pwd_plain=NULL WHERE id=?');
    $u->execute([$f['id']]);
    $success = true;
    $noPassword = true;
  } elseif ($action === 'generate') {
    // Generate new random password
    $newPassword = gen_pwd();
    $u = $pdo->prepare('UPDATE files SET recipient_pwd_hash=?, recipient_pwd_plain=? WHERE id=?');
    $u->execute([password_hash($newPassword, PASSWORD_DEFAULT), $newPassword, $f['id']]);
    $success = true;
  } elseif ($action === 'custom' && !empty($customPassword)) {
    // Use custom password
    $newPassword = $customPassword;
    $u = $pdo->prepare('UPDATE files SET recipient_pwd_hash=?, recipient_pwd_plain=? WHERE id=?');
    $u->execute([password_hash($newPassword, PASSWORD_DEFAULT), $newPassword, $f['id']]);
    $success = true;
  }
}

$hasCurrentPassword = !empty($f['recipient_pwd_plain']);
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
    .radio-group { display: flex; flex-direction: column; gap: 0.75rem; margin: 1rem 0; }
    .radio-option { display: flex; align-items: flex-start; gap: 0.5rem; padding: 0.75rem; border: 1px solid var(--form-element-border-color); border-radius: 8px; cursor: pointer; }
    .radio-option:hover { background: var(--form-element-background-color); }
    .radio-option input[type="radio"] { margin-top: 0.2rem; }
    .radio-option-content { flex: 1; }
    .radio-option-title { font-weight: 600; margin-bottom: 0.25rem; }
    .radio-option-desc { font-size: 0.875rem; color: var(--muted-color); }
    .custom-password-field { margin-top: 0.5rem; margin-left: 1.5rem; }
    .success-box { background: var(--ins-color); padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
    .current-status { background: var(--card-background-color); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid var(--form-element-border-color); }
  </style>
</head>
<body>
<?php include __DIR__ . '/partials/header.php'; ?>

<main class="container">
  <h1><?php echo htmlspecialchars(t('reset_recipient.title')); ?></h1>

  <?php if ($success): ?>
    <!-- Success State -->
    <article>
      <header>
        <h2 style="margin:0"><?php echo htmlspecialchars(t('reset_recipient.updated_title')); ?></h2>
        <p style="margin:0;color:var(--muted-color)"><?php echo htmlspecialchars(t('reset_recipient.file_code')); ?>: <code><?php echo htmlspecialchars($code); ?></code></p>
      </header>

      <?php if ($noPassword): ?>
        <div class="success-box">
          <strong>✓ <?php echo htmlspecialchars(t('reset_recipient.password_removed')); ?></strong>
          <p style="margin:0.5rem 0 0 0;"><?php echo htmlspecialchars(t('reset_recipient.password_removed_desc')); ?></p>
        </div>
      <?php else: ?>
        <p style="margin-bottom:0.5rem"><?php echo htmlspecialchars(t('reset_recipient.share_header')); ?></p>
      <?php endif; ?>

      <p class="copyline">
        <strong><?php echo htmlspecialchars(t('reset_recipient.recipient_link')); ?>:</strong>
        <span class="copyline-field">
          <code><?php echo htmlspecialchars($viewerUrl); ?></code>
          <button type="button" class="icon-btn copy-icon" data-copy="<?php echo htmlspecialchars($viewerUrl); ?>" aria-label="Copy" title="Copy">
            <svg viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
          </button>
        </span>
      </p>

      <?php if (!$noPassword && $newPassword): ?>
      <p class="copyline">
        <strong><?php echo htmlspecialchars(t('reset_recipient.recipient_password')); ?>:</strong>
        <span class="copyline-field">
          <code id="pw"><?php echo htmlspecialchars($newPassword); ?></code>
          <button type="button" class="icon-btn copy-icon" data-copy="<?php echo htmlspecialchars($newPassword); ?>" aria-label="Copy" title="Copy">
            <svg viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
          </button>
        </span>
      </p>
      <?php endif; ?>

      <p style="margin-top:1.5rem">
        <a class="btn secondary sm" href="/file.php?code=<?php echo urlencode($code); ?>"><?php echo htmlspecialchars(t('reset_recipient.back_to_file')); ?></a>
        <a class="btn ghost sm" href="/admin.php"><?php echo htmlspecialchars(t('reset_recipient.back_to_files')); ?></a>
      </p>
    </article>

  <?php else: ?>
    <!-- Form State -->
    <article>
      <header>
        <h2 style="margin:0"><?php echo htmlspecialchars(t('reset_recipient.change_password')); ?></h2>
        <p style="margin:0;color:var(--muted-color)"><?php echo htmlspecialchars(t('reset_recipient.file_code')); ?>: <code><?php echo htmlspecialchars($code); ?></code></p>
      </header>

      <div class="current-status">
        <strong><?php echo htmlspecialchars(t('reset_recipient.current_status')); ?>:</strong>
        <?php if ($hasCurrentPassword): ?>
          <span style="color: var(--primary);">🔒 <?php echo htmlspecialchars(t('reset_recipient.has_password')); ?></span>
        <?php else: ?>
          <span style="color: var(--success-color);">🔓 <?php echo htmlspecialchars(t('reset_recipient.no_password')); ?></span>
        <?php endif; ?>
      </div>

      <form method="post">
        <?php echo csrf_field(); ?>
        
        <div class="radio-group">
          <label class="radio-option">
            <input type="radio" name="password_action" value="none" <?php echo !$hasCurrentPassword ? 'checked' : ''; ?>>
            <div class="radio-option-content">
              <div class="radio-option-title"><?php echo htmlspecialchars(t('reset_recipient.option_none')); ?></div>
              <div class="radio-option-desc"><?php echo htmlspecialchars(t('reset_recipient.option_none_desc')); ?></div>
            </div>
          </label>
          
          <label class="radio-option">
            <input type="radio" name="password_action" value="generate" <?php echo $hasCurrentPassword ? 'checked' : ''; ?>>
            <div class="radio-option-content">
              <div class="radio-option-title"><?php echo htmlspecialchars(t('reset_recipient.option_generate')); ?></div>
              <div class="radio-option-desc"><?php echo htmlspecialchars(t('reset_recipient.option_generate_desc')); ?></div>
            </div>
          </label>
          
          <label class="radio-option">
            <input type="radio" name="password_action" value="custom" id="customRadio">
            <div class="radio-option-content">
              <div class="radio-option-title"><?php echo htmlspecialchars(t('reset_recipient.option_custom')); ?></div>
              <div class="radio-option-desc"><?php echo htmlspecialchars(t('reset_recipient.option_custom_desc')); ?></div>
              <div class="custom-password-field">
                <input type="text" name="custom_password" id="customPasswordInput" 
                       placeholder="<?php echo htmlspecialchars(t('reset_recipient.custom_placeholder')); ?>"
                       autocomplete="off">
              </div>
            </div>
          </label>
        </div>

        <button type="submit" class="btn accent"><?php echo htmlspecialchars(t('reset_recipient.save_btn')); ?></button>
        <a class="btn ghost" href="/file.php?code=<?php echo urlencode($code); ?>"><?php echo htmlspecialchars(t('reset_recipient.cancel')); ?></a>
      </form>
    </article>
  <?php endif; ?>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>

<script>
// Copy button handler
document.addEventListener('click', async (e) => {
  const btn = e.target.closest('.copy-icon');
  if (!btn) return;
  try {
    await navigator.clipboard.writeText(btn.dataset.copy || '');
    btn.classList.add('copied');
    setTimeout(() => btn.classList.remove('copied'), 1500);
  } catch (err) { alert('Kopieren fehlgeschlagen'); }
});

// Auto-select custom radio when typing in custom password field
const customInput = document.getElementById('customPasswordInput');
const customRadio = document.getElementById('customRadio');
if (customInput && customRadio) {
  customInput.addEventListener('focus', () => { customRadio.checked = true; });
  customInput.addEventListener('input', () => { customRadio.checked = true; });
}
</script>
</body>
</html>
