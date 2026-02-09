<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
require_once __DIR__ . '/../config/db.php';

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '[]', true) ?: [];

$type       = $payload['type'] ?? '';
$code       = $payload['code'] ?? '';
$session_id = $payload['session_id'] ?? '';
$visible_ms = (int)($payload['visible_ms'] ?? 0);
$total_ms   = (int)($payload['total_ms'] ?? 0);

// clamp scroll if present
$scroll = null;
if (array_key_exists('max_scroll_pct', $payload)) {
  $s = (int)$payload['max_scroll_pct'];
  if ($s < 0)   $s = 0;
  if ($s > 100) $s = 100;
  $scroll = $s;
}

if (!$code || !$session_id || !$type) { http_response_code(204); exit; }

// resolve file
$stmt = $pdo->prepare('SELECT id FROM files WHERE short_code=? LIMIT 1');
$stmt->execute([$code]);
$file_id = $stmt->fetchColumn();
if (!$file_id) { http_response_code(204); exit; }

// ensure row exists
$pdo->prepare("INSERT IGNORE INTO page_views (file_id, session_id, started_at, time_spent_ms, max_scroll_pct)
               VALUES (?, ?, NOW(), 0, 0)")
    ->execute([$file_id, $session_id]);

switch ($type) {
  case 'open':
    // row already ensured
    break;

  case 'progress':
    if ($scroll !== null) {
      $pdo->prepare("UPDATE page_views
                     SET max_scroll_pct = GREATEST(max_scroll_pct, ?)
                     WHERE file_id=? AND session_id=?")
          ->execute([$scroll, $file_id, $session_id]);
    }
    break;

  case 'heartbeat':
    $pdo->prepare("UPDATE page_views
                   SET time_spent_ms = GREATEST(time_spent_ms, ?),
                       max_scroll_pct = GREATEST(max_scroll_pct, ?)
                   WHERE file_id=? AND session_id=?")
        ->execute([$visible_ms, $scroll ?? 0, $file_id, $session_id]);
    break;

  case 'close':
    $final_ms = max($total_ms, $visible_ms, 0);
    // use provided scroll if present; otherwise keep current
    if ($scroll !== null) {
      $pdo->prepare("UPDATE page_views
                     SET time_spent_ms = GREATEST(time_spent_ms, ?),
                         max_scroll_pct = GREATEST(max_scroll_pct, ?)
                     WHERE file_id=? AND session_id=?")
          ->execute([$final_ms, $scroll, $file_id, $session_id]);
    } else {
      $pdo->prepare("UPDATE page_views
                     SET time_spent_ms = GREATEST(time_spent_ms, ?)
                     WHERE file_id=? AND session_id=?")
          ->execute([$final_ms, $file_id, $session_id]);
    }
    break;
}

http_response_code(204);
