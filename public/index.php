<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
require_once __DIR__ . '/../config/demo.php';
session_start();

// If logged in, redirect to admin/my files
if (!empty($_SESSION['user_id'])) {
    header('Location: /admin.php');
    exit;
}

// Not logged in: demo mode goes to login, otherwise to upload
if (is_demo_mode()) {
    header('Location: /account/login.php');
} else {
    header('Location: /upload.php');
}
exit;
