<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
// config/i18n.php
// Lightweight i18n for EN/DE with dot-notation keys and parameter interpolation.

if (!function_exists('i18n_current_lang')) {
  function i18n_supported(): array { return ['en','de']; }

  function i18n_detect_from_header(): string {
    $hdr = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    foreach (explode(',', $hdr) as $part) {
      $lang = strtolower(substr(trim($part), 0, 2));
      if (in_array($lang, i18n_supported(), true)) return $lang;
    }
    return 'en';
  }

  function i18n_set_lang(string $lang): void {
    if (!in_array($lang, i18n_supported(), true)) $lang = 'en';
    $_SESSION['lang'] = $lang;
    setcookie('lang', $lang, time()+60*60*24*365, '/', '', false, true);
    $GLOBALS['__i18n_lang'] = $lang;
  }

  function i18n_current_lang(): string {
    if (isset($GLOBALS['__i18n_lang'])) return $GLOBALS['__i18n_lang'];
    $lang = $_SESSION['lang'] ?? ($_COOKIE['lang'] ?? i18n_detect_from_header());
    if (!in_array($lang, i18n_supported(), true)) $lang = 'en';
    $GLOBALS['__i18n_lang'] = $lang;
    return $lang;
  }

  function i18n_load_bundle(string $lang): array {
    static $cache = [];
    if (isset($cache[$lang])) return $cache[$lang];
    $base = __DIR__ . '/../lang';
    $path = $base . '/' . $lang . '.php';
    $fallback = $base . '/en.php';
    $bundle = is_file($path) ? (require $path) : [];
    $fallbackBundle = is_file($fallback) ? (require $fallback) : [];
    // merge fallback → specific
    $cache[$lang] = array_replace_recursive($fallbackBundle, $bundle);
    return $cache[$lang];
  }

  function i18n_get(string $key, array $vars = []): string {
    $lang = i18n_current_lang();
    $bundle = i18n_load_bundle($lang);

    // dot notation
    $val = $bundle;
    foreach (explode('.', $key) as $seg) {
      if (!is_array($val) || !array_key_exists($seg, $val)) { $val = $key; break; }
      $val = $val[$seg];
    }
    if (!is_string($val)) $val = (string)$val;

    // interpolate {name} placeholders
    foreach ($vars as $k => $v) {
      $val = str_replace('{'.$k.'}', (string)$v, $val);
    }
    return $val;
  }

  // Short alias
  function t(string $key, array $vars = []): string { return i18n_get($key, $vars); }
}
