<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $path;
if ($path !== '/' && file_exists($file)) {
  return false;
}

if (preg_match('#^/a/([A-Za-z0-9]{4,8})$#', $path, $m)) {
  $_GET['code'] = $m[1];
  require __DIR__ . '/analytics.php';
  exit;
}
if (preg_match('#^/([A-Za-z0-9]{4,8})$#', $path, $m)) {
  $_GET['code'] = $m[1];
  require __DIR__ . '/viewer.php';
  exit;
}

require __DIR__ . '/upload.php';
