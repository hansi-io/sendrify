#!/usr/bin/env php
<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
/**
 * Demo Cleanup Cron Job
 * 
 * This script should be run periodically (e.g., every 5 minutes) to clean up old demo files.
 * 
 * Add to crontab:
 * */5 * * * * /usr/bin/php /path/to/sendrify/cleanup_cron.php >> /var/log/sendrify_cleanup.log 2>&1
 * 
 * Or run manually:
 * php cleanup_cron.php
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/demo.php';

// Only run if demo mode is enabled
if (!is_demo_mode()) {
    echo "[" . date('Y-m-d H:i:s') . "] Demo mode is not enabled. Exiting.\n";
    exit(0);
}

echo "[" . date('Y-m-d H:i:s') . "] Starting demo file cleanup...\n";

try {
    $deletedCount = cleanup_demo_files($pdo);
    echo "[" . date('Y-m-d H:i:s') . "] Cleanup completed. Deleted {$deletedCount} files.\n";
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Error during cleanup: " . $e->getMessage() . "\n";
    exit(1);
}

exit(0);
