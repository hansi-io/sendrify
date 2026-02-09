<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
require_once __DIR__ . '/../config/i18n.php';
session_start();
$set = $_GET['set'] ?? '';
if ($set) i18n_set_lang($set);
$back = $_SERVER['HTTP_REFERER'] ?? '/upload.php';
header('Location: ' . $back, true, 302);
