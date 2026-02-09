<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/util.php';
require_once __DIR__ . '/../../config/demo.php';
session_start();
require_once __DIR__ . '/../../config/i18n.php';

// If already logged in, redirect to admin
if (!empty($_SESSION['user_id'])) {
    header('Location: /admin.php');
    exit;
}

$err = '';
$notice = '';
$next = $_GET['next'] ?? '/admin.php';

if (!empty($_SESSION['password_reset_success'])) {
  $notice = t('account.reset_success');
  unset($_SESSION['password_reset_success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  $pass  = $_POST['password'] ?? '';
  $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE email=?');
  $stmt->execute([$email]);
  $u = $stmt->fetch();
  if (!$u || !password_verify($pass, $u['password_hash'])) {
    $err = t('account.login_error');
  } else {
    // ✅ SECURITY: Regenerate session ID after login (prevent session fixation)
    session_regenerate_id(true);
    
    $_SESSION['user_id'] = (int)$u['id'];
    $_SESSION['login_time'] = time();
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
    
    // Auto-claim if this session has a just-uploaded file
    $claimedCode = auto_claim_pending_file($pdo);

    // ✅ SECURITY: Validate redirect URL to prevent open redirect attacks
    $next = $_GET['next'] ?? $_POST['next'] ?? '';
    $next = validate_redirect_url($next);
    if ($next) {
      header('Location: ' . $next); exit;
    }
    if ($claimedCode) {
      header('Location: /analytics.php?code=' . urlencode($claimedCode)); exit;
    }
    // Fallback: go to My files (or wherever you like)
    header('Location: /admin.php'); exit;
  }
}
?>
<!doctype html>
<html data-theme="light">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo is_demo_mode() ? 'Sendrify Demo - Login' : htmlspecialchars(t('account.login_title')); ?></title>
  <link rel="stylesheet" href="https://unpkg.com/@picocss/pico@latest/css/pico.min.css">
  <link rel="stylesheet" href="/static/app.css">
  <?php if (is_demo_mode()): ?>
  <style>
    .demo-hero {
      background: linear-gradient(135deg, var(--brand-navy) 0%, #1a3a8f 100%);
      color: white;
      padding: var(--space-2xl);
      text-align: center;
      border-radius: var(--radius-xl);
      margin-bottom: var(--space-xl);
      box-shadow: var(--shadow-xl);
    }
    .demo-hero h1 {
      margin: 0 0 var(--space-sm) 0;
      font-size: 2rem;
      color: #fff;
    }
    .demo-hero p {
      margin: 0;
      opacity: 0.9;
      color: rgba(255,255,255,0.9);
    }
    .demo-credentials {
      background: rgba(255,255,255,0.1);
      backdrop-filter: blur(10px);
      padding: var(--space-xl);
      border-radius: var(--radius-lg);
      margin-top: var(--space-xl);
      text-align: left;
    }
    .demo-credentials h3 {
      margin: 0 0 var(--space-md) 0;
      font-size: 1.125rem;
      text-align: center;
      color: #fff;
    }
    .demo-cred-item {
      background: rgba(255,255,255,0.15);
      padding: var(--space-md);
      border-radius: var(--radius-md);
      margin-bottom: var(--space-sm);
      font-family: var(--font-mono);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .demo-cred-item:last-child {
      margin-bottom: 0;
    }
    .demo-cred-label {
      font-weight: 600;
      opacity: 0.9;
    }
    .demo-cred-value {
      background: rgba(0,0,0,0.2);
      padding: 0.25rem 0.75rem;
      border-radius: var(--radius-sm);
    }
    .quick-login-note {
      text-align: center;
      margin-top: var(--space-md);
      font-size: 0.875rem;
      opacity: 0.85;
    }
  </style>
  <?php endif; ?>
</head>
<body>
<?php include __DIR__ . '/../partials/header.php'; ?>
<main class="container">
  <div class="auth-container animate-fade-in">
  <?php if (is_demo_mode()): ?>
  <div class="demo-hero">
    <h1>🎯 Sendrify Demo</h1>
    <p>Welcome to the Sendrify demo! Use the credentials below to log in and explore all features.</p>
  </div>
  <?php endif; ?>

  <div class="auth-card">
    <?php if (!is_demo_mode()): ?>
    <h1><?php echo htmlspecialchars(t('account.login_title')); ?></h1>
    <?php endif; ?>
    
    <?php if ($notice): ?>
      <div class="alert alert-success"><?php echo htmlspecialchars($notice); ?></div>
    <?php endif; ?>
    
    <?php if ($err): ?>
      <div class="alert alert-error"><?php echo htmlspecialchars($err); ?></div>
    <?php endif; ?>
    
    <form method="post">
      <?php echo csrf_field(); // ✅ CSRF Token ?>
      <input type="hidden" name="next" value="<?php echo htmlspecialchars($next); ?>">
      <label>
        <?php echo htmlspecialchars(t('account.email')); ?> 
        <input 
          type="email" 
          name="email" 
          required 
          autocomplete="email"
          <?php echo is_demo_mode() ? 'placeholder="demo@sendrify.local" value="demo@sendrify.local"' : ''; ?>
        >
      </label>
      <label>
        <?php echo htmlspecialchars(t('account.password')); ?> 
        <input 
          type="password" 
          name="password" 
          required
          autocomplete="current-password"
          <?php echo is_demo_mode() ? 'placeholder="demo123"' : ''; ?>
        >
      </label>
      <button type="submit"><?php echo htmlspecialchars(t('account.login_btn')); ?></button>
    </form>
    
    <?php if (!is_demo_mode()): ?>
    <div class="auth-links">
      <a href="/account/forgot.php"><?php echo htmlspecialchars(t('account.login_forgot')); ?></a>
    </div>
    <?php endif; ?>
  </div>
  </div>
</main>
<?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
