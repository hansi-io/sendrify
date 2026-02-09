<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/util.php';
session_start();
require_once __DIR__ . '/../../config/i18n.php';

$token = trim($_GET['token'] ?? ($_POST['token'] ?? ''));
$err = '';

function fetch_reset(PDO $pdo, string $token): ?array {
  if ($token === '') return null;
  $hash = hash('sha256', $token);
  $stmt = $pdo->prepare('SELECT pr.id, pr.user_id, pr.expires_at, u.email FROM password_resets pr JOIN users u ON pr.user_id = u.id WHERE pr.token_hash=? LIMIT 1');
  $stmt->execute([$hash]);
  $row = $stmt->fetch();
  if (!$row) return null;
  if (strtotime($row['expires_at']) < time()) return null;
  return $row;
}

$reset = fetch_reset($pdo, $token);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!$reset) {
    $err = t('account.reset_invalid');
  } else {
    $pass = $_POST['password'] ?? '';
    $confirm = $_POST['password2'] ?? '';

    if (strlen($pass) < 8) {
      $err = t('account.reset_password_length');
    } elseif ($pass !== $confirm) {
      $err = t('account.reset_password_mismatch');
    } else {
      $update = $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?');
      $update->execute([password_hash($pass, PASSWORD_DEFAULT), (int)$reset['user_id']]);

      $pdo->prepare('DELETE FROM password_resets WHERE user_id=?')->execute([(int)$reset['user_id']]);

      $_SESSION['password_reset_success'] = true;
      header('Location: /account/login.php?reset=1');
      exit;
    }
  }
}

// Re-fetch in case the POST failed and token was valid earlier
if (!$reset && $err === '') {
  $err = t('account.reset_invalid');
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo  htmlspecialchars(t('account.reset_title')) ?></title>
  <link rel="stylesheet" href="https://unpkg.com/@picocss/pico@latest/css/pico.min.css">
  <link rel="stylesheet" href="/static/app.css">
</head>
<body>
<?php include __DIR__ . '/../partials/header.php'; ?>
<main class="container">
  <h1><?php echo  htmlspecialchars(t('account.reset_title')) ?></h1>

  <?php if ($err): ?><p class="contrast"><?php echo  htmlspecialchars($err) ?></p><?php endif; ?>

  <?php if ($reset): ?>
    <form method="post">
      <input type="hidden" name="token" value="<?php echo  htmlspecialchars($token) ?>">
      <label>
        <?php echo  htmlspecialchars(t('account.reset_password')) ?>
        <input type="password" name="password" required minlength="8" autocomplete="new-password">
      </label>
      <label>
        <?php echo  htmlspecialchars(t('account.reset_password_confirm')) ?>
        <input type="password" name="password2" required minlength="8" autocomplete="new-password">
      </label>
      <button type="submit"><?php echo  htmlspecialchars(t('account.reset_submit')) ?></button>
    </form>
  <?php else: ?>
    <p><a href="/account/forgot.php">&larr; <?php echo  htmlspecialchars(t('account.forgot_title')) ?></a></p>
  <?php endif; ?>
</main>
<?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
