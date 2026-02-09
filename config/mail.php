<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
function mail_default_from(): string {
  $envFrom = getenv('MAIL_FROM_ADDRESS');
  if ($envFrom) {
    return $envFrom;
  }
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  // Strip scheme if somehow present and default to localhost domain without port
  $host = preg_replace('/^https?:\/\//i', '', $host);
  $host = preg_replace('/:.*/', '', $host);
  if ($host === '') {
    $host = 'localhost';
  }
  return 'no-reply@' . $host;
}

function send_mail(string $to, string $subject, string $body, array $headers = []): bool {
  $baseHeaders = [
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'From: ' . mail_default_from(),
  ];
  $headers = array_merge($baseHeaders, $headers);
  $headerStr = implode("
", $headers);

  $sent = false;
  if (function_exists('mail')) {
    $sent = mail($to, $subject, $body, $headerStr);
  }

  $logDir = __DIR__ . '/../storage';
  if (is_dir($logDir) && is_writable($logDir)) {
    $logPath = $logDir . '/mail.log';
    $entry = sprintf(
      "[%s] %s -> %s (%s)
%s

",
      date('c'),
      $subject,
      $to,
      $sent ? 'sent' : 'failed',
      $body
    );
    file_put_contents($logPath, $entry, FILE_APPEND);
  }

  return $sent;
}
