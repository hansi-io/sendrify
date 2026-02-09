<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
session_start();
require_once __DIR__ . '/../../config/i18n.php';
session_unset();
session_destroy();
header('Location: /upload.php');
