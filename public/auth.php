<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
require_once __DIR__ . '/../config/db.php';
session_start();
require_once __DIR__ . '/../config/i18n.php';
require_once __DIR__ . '/../config/util.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
  http_response_code(405); 
  exit; 
}

$action = $_POST['action'] ?? '';
$code = $_POST['code'] ?? '';

// ✅ SECURITY: CSRF Token Validation
// NOTE: recipient_login & sender_login skip CSRF check because viewer.php
// is a public page with no initial CSRF token. Access is already protected
// by the short_code randomness and password hashing.
if ($action !== 'recipient_login' && $action !== 'sender_login') {
  if (!verify_csrf_token()) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'CSRF token validation failed']);
    exit;
  }
}

if ($action === 'save_settings') {
  $settings = [
    'track_open' => isset($_POST['track_open']),
    'track_time' => isset($_POST['track_time']),
    'track_scroll' => isset($_POST['track_scroll'])
  ];
  $stmt = $pdo->prepare('UPDATE files SET settings_json=? WHERE short_code=?');
  $stmt->execute([json_encode($settings), $code]);
  header('Location: /analytics.php?code='.urlencode($code)); exit;
}

if ($action === 'recipient_login' || $action === 'sender_login') {
  $pwd = $_POST['password'] ?? '';
  $stmt = $pdo->prepare('SELECT id, recipient_pwd_hash, sender_pwd_hash FROM files WHERE short_code=?');
  $stmt->execute([$code]); $file = $stmt->fetch();
  
  if (!$file) { 
    header('Location: /viewer.php?code='.urlencode($code).'&e=notfound'); 
    exit; 
  }
  
  $ok = ($action==='recipient_login')
        ? password_verify($pwd, $file['recipient_pwd_hash'])
        : password_verify($pwd, $file['sender_pwd_hash']);
  
  if ($ok) {
    $_SESSION[($action.'_'.$code)] = true;
    // Redirect to explicit URLs (not short URLs) to avoid .htaccess issues
    if ($action === 'recipient_login') {
      header('Location: /viewer.php?code='.urlencode($code));
    } else {
      header('Location: /analytics.php?code='.urlencode($code));
    }
    exit;
  } else {
    // Redirect back with error
    if ($action === 'recipient_login') {
      header('Location: /viewer.php?code='.urlencode($code).'&e=badpwd');
    } else {
      header('Location: /analytics.php?code='.urlencode($code).'&e=badpwd');
    }
    exit;
  }
}
