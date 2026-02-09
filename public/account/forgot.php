<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/util.php';
require_once __DIR__ . '/../../config/mail.php';
session_start();
require_once __DIR__ . '/../../config/i18n.php';

$err = '';
$success = false;
$devLink = null;

if (!empty($_SESSION['password_reset_requested'])) {
  $success = true;
  $devLink = $_SESSION['password_reset_link'] ?? null;
  unset($_SESSION['password_reset_requested'], $_SESSION['password_reset_link']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = strtolower(trim($_POST['email'] ?? ''));

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $err = t('account.forgot_invalid_email');
  } else {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
      $pdo->prepare('DELETE FROM password_resets WHERE user_id=?')->execute([(int)$user['id']]);

      $token = bin2hex(random_bytes(32));
      $hash = hash('sha256', $token);
      $expires = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');

      $insert = $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?,?,?)');
      $insert->execute([(int)$user['id'], $hash, $expires]);

      $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
      $host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8080';
      $resetUrl = $scheme . $host . '/account/reset.php?token=' . $token;

      $subject = t('account.forgot_email_subject', ['app' => t('app.name')]);
      $body = t('account.forgot_email_body', [
        'app' => t('app.name'),
        'link' => $resetUrl,
      ]);

      send_mail($email, $subject, $body);

      $_SESSION['password_reset_link'] = $resetUrl;
    }

    $_SESSION['password_reset_requested'] = true;
    header('Location: /account/forgot.php?sent=1');
    exit;
  }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo  htmlspecialchars(t('account.forgot_title')) ?></title>
  <link rel="stylesheet" href="https://unpkg.com/@picocss/pico@latest/css/pico.min.css">
  <link rel="stylesheet" href="/static/app.css">
</head>
<body>
<?php include __DIR__ . '/../partials/header.php'; ?>
<main class="container">
  <h1><?php echo  htmlspecialchars(t('account.forgot_title')) ?></h1>

  <?php if ($success): ?>
    <p class="contrast"><?php echo  htmlspecialchars(t('account.forgot_sent')) ?></p>
    <?php if ($devLink): ?>
      <p>
        <strong><?php echo  htmlspecialchars(t('account.forgot_dev_link')) ?>:</strong><br>
        <code><?php echo  htmlspecialchars($devLink) ?></code>
      </p>
    <?php endif; ?>
  <?php else: ?>
    <p><?php echo  htmlspecialchars(t('account.forgot_intro')) ?></p>
    <?php if ($err): ?><p class="contrast"><?php echo  htmlspecialchars($err) ?></p><?php endif; ?>
    <form method="post">
      <label>
        <?php echo  htmlspecialchars(t('account.forgot_email')) ?>
        <input type="email" name="email" required autocomplete="email">
      </label>
      <button type="submit"><?php echo  htmlspecialchars(t('account.forgot_submit')) ?></button>
    </form>
  <?php endif; ?>

  <p style="margin-top:1rem">
    <a href="/account/login.php">&larr; <?php echo  htmlspecialchars(t('nav.login')) ?></a>
  </p>
</main>
<?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
