<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
function generate_code($len = 6) {
  $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
  $out = '';
  for ($i=0; $i<$len; $i++) $out .= $alphabet[random_int(0, strlen($alphabet)-1)];
  return $out;
}
function hash_pwd($plain) { return password_hash($plain, PASSWORD_DEFAULT); }
function verify_pwd($plain, $hash) { return password_verify($plain, $hash); }
function ip_hash() { return hash('sha256', $_SERVER['REMOTE_ADDR'] ?? ''); }
function json_response($arr, $status=200){
  http_response_code($status);
  header('Content-Type: application/json');
  echo json_encode($arr);
  exit;
}
function current_user_id() {
  return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}
function require_login() {
  if (!current_user_id()) { header('Location: /account/login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/admin.php')); exit; }
}

function format_duration($seconds) {
  if ($seconds === null) return '–';
  $s = (int) round($seconds);
  if ($s < 60) return $s . t('units.s');
  if ($s < 3600) return sprintf('%d:%02d%s', intdiv($s,60), $s%60, t('units.min'));
  $h = intdiv($s, 3600);
  $m = intdiv($s % 3600, 60);
  return sprintf('%d:%02d%s', $h, $m, t('units.h'));
}

function auto_claim_pending_file(PDO $pdo): ?string {
  if (empty($_SESSION['user_id'])) return null;
  if (empty($_SESSION['new_file']['code'])) return null;

  $uid  = (int) $_SESSION['user_id'];
  $code = (string) $_SESSION['new_file']['code'];

  // Look up file and claim it iff it's ownerless (or already owned by this user)
  $s = $pdo->prepare('SELECT id, owner_id FROM files WHERE short_code=? LIMIT 1');
  $s->execute([$code]);
  $f = $s->fetch();

  if (!$f) return null;

  if (empty($f['owner_id'])) {
    $u = $pdo->prepare('UPDATE files SET owner_id=? WHERE id=?');
    $u->execute([$uid, (int)$f['id']]);
  } else if ((int)$f['owner_id'] !== $uid) {
    // Owned by someone else → don't touch
    return null;
  }

  // Optionally keep it for the post-upload dashboard page.
  // If you prefer to clear it, uncomment:
  // unset($_SESSION['new_file']);

  return $code;
}

/**
 * ═══════════════════════════════════════════════════════════
 * SECURITY FUNCTIONS - CSRF & VALIDATION
 * ═══════════════════════════════════════════════════════════
 */

/**
 * Initialize CSRF token in session if it doesn't exist
 */
function init_csrf_token() {
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
}

/**
 * Get current CSRF token
 */
function get_csrf_token() {
  init_csrf_token();
  return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token from POST/GET
 */
function verify_csrf_token($token = null) {
  init_csrf_token();
  
  $provided = $token ?? $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
  
  if (empty($provided) || empty($_SESSION['csrf_token'])) {
    return false;
  }
  
  return hash_equals($_SESSION['csrf_token'], $provided);
}

/**
 * Generate CSRF token HTML hidden input field
 */
function csrf_field() {
  return '<input type="hidden" name="csrf_token" value="' 
         . htmlspecialchars(get_csrf_token()) . '">';
}

/**
 * Validate and sanitize redirect URLs - prevents open redirect vulnerability
 * Only allows relative URLs that are in the whitelist
 */
function validate_redirect_url($url, $default = '/admin.php') {
  // Whitelist of allowed redirect URLs
  $allowed = [
    '/admin.php',
    '/upload.php',
    '/account/login.php',
    '/account/register.php',
    '/account/forgot.php',
  ];
  
  // Empty or invalid input
  if (empty($url)) {
    return $default;
  }
  
  // Only allow relative URLs (must start with / and not contain http/https)
  if (!preg_match('#^/[a-zA-Z0-9_/\-\.]*(\?.*)?$#', $url) || 
      strpos($url, 'http') !== false) {
    return $default;
  }
  
  // Check against whitelist
  foreach ($allowed as $allowed_url) {
    if (strpos($url, $allowed_url) === 0) {
      return $url;
    }
  }
  
  return $default;
}

/**
 * Validate session hasn't been hijacked (Session Fixation Protection)
 */
function validate_session_integrity() {
  if (empty($_SESSION['user_id'])) {
    return false;
  }
  
  // Check if user agent changed (indicates potential hijacking)
  if (!empty($_SESSION['user_agent'])) {
    if ($_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
      session_destroy();
      return false;
    }
  }
  
  // Check session age (optional - prevent very old sessions)
  if (!empty($_SESSION['login_time'])) {
    $session_age = time() - $_SESSION['login_time'];
    $max_age = 86400 * 30;  // 30 days
    
    if ($session_age > $max_age) {
      session_destroy();
      return false;
    }
  }
  
  return true;
}
