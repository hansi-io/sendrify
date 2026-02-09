<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
require_once __DIR__ . '/../config/db.php';
session_start();
require_once __DIR__ . '/../config/i18n.php';
$code = $_GET['code'] ?? '';
if (empty($_SESSION['recipient_login_'.$code])) { http_response_code(403); exit; }
$stmt = $pdo->prepare('SELECT file_path FROM files WHERE short_code=?');
$stmt->execute([$code]); $row = $stmt->fetch();
if (!$row) { http_response_code(404); exit; }
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="'.$code.'.pdf"');
readfile($row['file_path']);
