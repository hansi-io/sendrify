<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
// account/register.php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/util.php';
session_start();
require_once __DIR__ . '/../../config/i18n.php';

$err  = '';
$next = $_GET['next'] ?? $_POST['next'] ?? '';

// ✅ SECURITY: Validate redirect URL to prevent open redirect attacks
$next = validate_redirect_url($next);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // ✅ SECURITY: CSRF Token Validation
  if (!verify_csrf_token()) {
    $err = t('register.error_generic') . ' (CSRF validation failed)';
  } elseif (!($email = strtolower(trim($_POST['email'] ?? '')))) {
    $err = t('register.error_invalid_email');
  } else {
    $pass  = $_POST['password']  ?? '';
    $pass2 = $_POST['password2'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $err = t('register.error_invalid_email');
    } elseif (strlen($pass) < 8) {
      $err = t('register.error_password_length');
    } elseif ($pass !== $pass2) {
      $err = t('register.error_password_mismatch');
    } else {
      try {
        // check if already exists
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
          $err = t('register.error_email_exists');
        } else {
          // create user
          $ins = $pdo->prepare('INSERT INTO users (email, password_hash) VALUES (?, ?)');
          $ins->execute([$email, password_hash($pass, PASSWORD_DEFAULT)]);
          
          // ✅ SECURITY: Regenerate session ID after registration (prevent session fixation)
          session_regenerate_id(true);
          
          $_SESSION['user_id'] = (int)$pdo->lastInsertId();
          $_SESSION['login_time'] = time();
          $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];

          // ---- Auto-claim just-uploaded file (if util helper exists) ----
          $claimedCode = null;
          if (function_exists('auto_claim_pending_file')) {
            $claimedCode = auto_claim_pending_file($pdo); // claims and returns short_code or null
          }

          // ---- Redirect preference: next → claimed analytics → admin ----
          if ($next) {
            header('Location: ' . $next); exit;
          }
          if ($claimedCode) {
            header('Location: /analytics.php?code=' . urlencode($claimedCode)); exit;
          }
          header('Location: /admin.php'); exit;
        }
      } catch (Throwable $e) {
        // Avoid leaking DB errors to users
        $err = t('register.error_generic');
        // Uncomment for local debugging:
        // $err .= ' ('.$e->getMessage().')';
      }
    }
  }
}
?>
<!doctype html>
<html data-theme="light">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo  htmlspecialchars(t('register.title')) ?></title>
  <link rel="stylesheet" href="https://unpkg.com/@picocss/pico@latest/css/pico.min.css">
  <link rel="stylesheet" href="/static/app.css">
</head>
<body>
<?php include __DIR__ . '/../partials/header.php'; ?>
<main class="container">
  <h1><?php echo  htmlspecialchars(t('register.title')) ?></h1>
  <?php if ($err): ?><p class="contrast"><?php echo  htmlspecialchars($err) ?></p><?php endif; ?>
  <form method="post">
    <?php echo csrf_field(); // ✅ CSRF Token ?>
    <?php if ($next): ?>
      <input type="hidden" name="next" value="<?php echo  htmlspecialchars($next) ?>">
    <?php endif; ?>
    <label><?php echo  htmlspecialchars(t('register.email')) ?>
      <input type="email" name="email" required autocomplete="email">
    </label>
    <label><?php echo  htmlspecialchars(t('register.password')) ?>
      <input type="password" name="password" required minlength="8" autocomplete="new-password">
    </label>
    <label><?php echo  htmlspecialchars(t('register.repeat_password')) ?>
      <input type="password" name="password2" required minlength="8" autocomplete="new-password">
    </label>
    <button type="submit"><?php echo  htmlspecialchars(t('register.register_btn')) ?></button>
  </form>
  <p style="margin-top:.75rem">
    <?php echo  htmlspecialchars(t('register.already_account')) ?>
    <a href="/account/login.php<?php echo  $next ? ('?next='.urlencode($next)) : '' ?>"> <?php echo  htmlspecialchars(t('register.login')) ?></a>
  </p>
</main>
<?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
