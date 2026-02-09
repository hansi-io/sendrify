<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
/**
 * Database configuration & Security Headers
 */

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'sendrify');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

try {
  $pdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER,
    DB_PASS,
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false
    ]
  );
} catch (PDOException $e) {
  die('Database connection failed: ' . $e->getMessage());
}

/**
 * ═══════════════════════════════════════════════════════════
 * SECURITY HEADERS - Prevent common attacks
 * ═══════════════════════════════════════════════════════════
 */

// Prevent clickjacking attacks
header('X-Frame-Options: DENY');

// Prevent MIME type sniffing
header('X-Content-Type-Options: nosniff');

// Enable XSS protection in older browsers
header('X-XSS-Protection: 1; mode=block');

// Control referrer information
header('Referrer-Policy: strict-origin-when-cross-origin');

// Enable HTTPS Strict Transport Security (if using HTTPS)
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
  header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
}

// Content Security Policy - permissive but still protective
// Allows: PDF.js, form uploads, inline styles for compatibility
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://unpkg.com/ https://cdn.jsdelivr.net/; style-src 'self' 'unsafe-inline' https://unpkg.com/ https://cdn.jsdelivr.net/; img-src 'self' data: https:; font-src 'self' https://unpkg.com/ https://cdn.jsdelivr.net/; connect-src 'self'; form-action 'self'; frame-ancestors 'none';");
