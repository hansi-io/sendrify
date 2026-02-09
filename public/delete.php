<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/util.php';
session_start();
require_login();
$uid = (int)$_SESSION['user_id'];

$code = $_GET['code'] ?? '';
$ok = false;

if ($code) {
  $s = $pdo->prepare('SELECT id, file_path, owner_id FROM files WHERE short_code=?');
  $s->execute([$code]); $f = $s->fetch();
  if ($f && (int)$f['owner_id'] === $uid) {
    // delete DB rows (events/page_views cascade via FK if set; else manual)
    $pdo->prepare('DELETE FROM events WHERE file_id=?')->execute([$f['id']]);
    $pdo->prepare('DELETE FROM page_views WHERE file_id=?')->execute([$f['id']]);
    $pdo->prepare('DELETE FROM files WHERE id=?')->execute([$f['id']]);
    // delete file
    if (is_file($f['file_path'])) @unlink($f['file_path']);
    $ok = true;
  }
}
header('Location: /admin.php' . ($ok ? '' : '?e=1'));
