<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
// public/reset_analytics.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/util.php';
session_start();
require_once __DIR__ . '/../config/i18n.php';
require_login();

// --- input ---
$code = $_GET['code'] ?? '';
if (!$code) { http_response_code(400); exit('Missing code'); }

// --- fetch file & authorize ---
$s = $pdo->prepare('SELECT id, owner_id FROM files WHERE short_code=?');
$s->execute([$code]);
$f = $s->fetch();
if (!$f) { http_response_code(404); exit('Not found'); }
if ((int)$f['owner_id'] !== current_user_id()) { http_response_code(403); exit('Forbidden'); }

// --- delete all analytics data for this file ---
$fileId = (int)$f['id'];

// Delete events
$del1 = $pdo->prepare('DELETE FROM events WHERE file_id = ?');
$del1->execute([$fileId]);

// Delete page views
$del2 = $pdo->prepare('DELETE FROM page_views WHERE file_id = ?');
$del2->execute([$fileId]);

// Redirect back to analytics page
header('Location: /analytics.php?code=' . urlencode($code));
exit;
