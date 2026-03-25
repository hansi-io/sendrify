<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
/**
 * Demo Mode Configuration
 * 
 * Enables a demo mode with pre-configured user accounts and automatic file cleanup
 */

// Demo mode enabled/disabled
define('DEMO_MODE', false);

// File lifetime in minutes (files will be deleted after this time)
define('DEMO_FILE_LIFETIME_MINUTES', 30);

// Demo user credentials
define('DEMO_USER_EMAIL', 'demo@sendrify.local');
define('DEMO_USER_PASSWORD', 'demo123');

// Admin demo user (optional, for testing admin features)
define('DEMO_ADMIN_EMAIL', 'admin@sendrify.local');
define('DEMO_ADMIN_PASSWORD', 'admin123');

// Maximum number of files per demo session
define('DEMO_MAX_FILES_PER_SESSION', 5);

// Maximum file size for demo uploads (in bytes) - 10MB
// Note: This is also enforced in upload.php
define('DEMO_MAX_FILE_SIZE', 10 * 1024 * 1024);

/**
 * Check if demo mode is enabled
 */
function is_demo_mode() {
    return defined('DEMO_MODE') && DEMO_MODE === true;
}

/**
 * Get demo user ID if logged in as demo user
 */
function get_demo_user_id($pdo) {
    if (!is_demo_mode()) return null;
    
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([DEMO_USER_EMAIL]);
    $user = $stmt->fetch();
    
    return $user ? (int)$user['id'] : null;
}

/**
 * Check if current session is a demo user
 */
function is_demo_user($pdo) {
    if (!is_demo_mode() || empty($_SESSION['user_id'])) {
        return false;
    }
    
    $demoUserId = get_demo_user_id($pdo);
    return $_SESSION['user_id'] === $demoUserId;
}

/**
 * Initialize demo users in database
 */
function init_demo_users($pdo) {
    if (!is_demo_mode()) return;
    
    // Check if demo user exists
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([DEMO_USER_EMAIL]);
    
    if (!$stmt->fetch()) {
        // Create demo user
        $stmt = $pdo->prepare('INSERT INTO users (email, password_hash) VALUES (?, ?)');
        $stmt->execute([
            DEMO_USER_EMAIL,
            password_hash(DEMO_USER_PASSWORD, PASSWORD_DEFAULT)
        ]);
    }
    
    // Check if admin demo user exists
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([DEMO_ADMIN_EMAIL]);
    
    if (!$stmt->fetch()) {
        // Create admin demo user
        $stmt = $pdo->prepare('INSERT INTO users (email, password_hash) VALUES (?, ?)');
        $stmt->execute([
            DEMO_ADMIN_EMAIL,
            password_hash(DEMO_ADMIN_PASSWORD, PASSWORD_DEFAULT)
        ]);
    }
}

/**
 * Clean up old demo files
 * Should be called periodically (e.g., via cron or on page load)
 */
function cleanup_demo_files($pdo) {
    if (!is_demo_mode()) return;
    
    $cutoff = date('Y-m-d H:i:s', strtotime('-' . DEMO_FILE_LIFETIME_MINUTES . ' minutes'));
    
    // Get files to delete (exclude permanent demo file with code DEMO00)
    $stmt = $pdo->prepare('
        SELECT f.id, f.file_path 
        FROM files f
        INNER JOIN users u ON f.owner_id = u.id
        WHERE u.email IN (?, ?) 
        AND f.created_at < ?
        AND f.short_code != ?
    ');
    $stmt->execute([DEMO_USER_EMAIL, DEMO_ADMIN_EMAIL, $cutoff, 'DEMO00']);
    
    $files = $stmt->fetchAll();
    
    foreach ($files as $file) {
        // Delete physical file
        if (file_exists($file['file_path'])) {
            unlink($file['file_path']);
        }
        
        // Delete from database (cascade will handle related records)
        $delStmt = $pdo->prepare('DELETE FROM files WHERE id = ?');
        $delStmt->execute([$file['id']]);
    }
    
    return count($files);
}

/**
 * Get demo info banner HTML
 */
function get_demo_banner_html() {
    if (!is_demo_mode()) return '';
    
    $minutes = DEMO_FILE_LIFETIME_MINUTES;
    
    $html = '<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1rem; text-align: center; border-radius: 0.5rem; margin-bottom: 1.5rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">';
    $html .= '<strong>🎯 Demo Mode Active</strong><br>';
    $html .= '<small style="opacity: 0.9;">';
    $html .= 'Login: <code style="background: rgba(255,255,255,0.2); padding: 0.2rem 0.4rem; border-radius: 0.25rem;">demo@sendrify.local</code> / ';
    $html .= '<code style="background: rgba(255,255,255,0.2); padding: 0.2rem 0.4rem; border-radius: 0.25rem;">demo123</code> ';
    $html .= '• Files auto-delete after ' . $minutes . ' minutes';
    $html .= '</small>';
    $html .= '</div>';
    
    return $html;
}
