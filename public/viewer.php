<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
// public/viewer.php  – full-screen, no header/footer
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/util.php';
session_start();
require_once __DIR__ . '/../config/i18n.php';

$code = $_GET['code'] ?? '';
$stmt = $pdo->prepare('SELECT id, file_path, settings_json, owner_id, recipient_pwd_hash FROM files WHERE short_code=?');
$stmt->execute([$code]);
$file = $stmt->fetch();
if (!$file) { http_response_code(404); echo 'Not found'; exit; }

// Check if password protection is enabled
$hasPassword = !empty($file['recipient_pwd_hash']);

// Owner auto-login to recipient view
if (function_exists('current_user_id') && current_user_id() && (int)$file['owner_id'] === current_user_id()) {
  $_SESSION['recipient_login_'.$code] = true;
}
$authed = !empty($_SESSION['recipient_login_'.$code]);
$filename = basename($file['file_path']);
?>
<!doctype html>
<html data-theme="light">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars(t('viewer.title')) ?></title>
  <style>
    /* Full-viewport, no chrome */
    html, body { height:100%; margin:0; background:#fff; }
    body { overflow:hidden; }
    #pdf { position:fixed; inset:0; width:100vw; height:100vh; border:0; display:block; }

    /* Minimal login styling (centered card) */
    .center {
      min-height:100vh; display:grid; place-items:center; padding:24px; box-sizing:border-box;
      font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji","Segoe UI Emoji";
      background:#0f172a;
      position:relative;
      overflow:hidden;
    }
    .center::before,
    .center::after {
      content:"";
      position:absolute;
      inset:-40%;
      background:linear-gradient(120deg, rgba(56,189,248,0.8), rgba(167,139,250,0.75), rgba(244,114,182,0.8), rgba(253,186,116,0.8));
      background-size:200% 200%;
      animation: gradientShift 18s ease infinite;
      filter: blur(40px);
      z-index:0;
    }
    .center::after {
      animation-duration: 26s;
      mix-blend-mode: screen;
      opacity:0.8;
      transform: translate3d(10%, -5%, 0);
    }
    .card {
      width:min(420px, 92vw); background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:20px;
      box-shadow: 0 20px 60px rgba(15,23,42,.25);
      position:relative;
      z-index:1;
    }
    .card h1 { margin:.25rem 0 1rem; font-size:1.25rem; }
     .file-note { margin:0 0 .75rem; color:#0f172a; font-size:.95rem; font-weight:600; word-break: break-word; }
    .row { display:flex; gap:.5rem; }
    .consent { margin:.75rem 0 1rem; font-size:.9rem; color:#374151; }
    .consent label { display:flex; gap:.5rem; align-items:flex-start; line-height:1.4; }
    .consent input[type=checkbox]{ margin-top:.15rem; }
    .consent a{ color:#2563eb; text-decoration:none; }
    .consent a:hover{ text-decoration:underline; }
    input[type=password]{
      width:100%; padding:.75rem .9rem; border:1px solid #d1d5db; border-radius:10px; font-size:1rem; outline:0;
    }
    button{
      padding:.75rem 1rem; border:0; border-radius:10px; background:#111827; color:#fff; font-weight:600; cursor:pointer;
    }
    button:disabled{ background:#9ca3af; cursor:not-allowed; }
    .error{ background:#fee2e2; color:#991b1b; padding:.5rem .75rem; border-radius:8px; margin-bottom:.75rem; }
    @keyframes gradientShift {
      0% { background-position: 0% 50%; }
      50% { background-position: 100% 50%; }
      100% { background-position: 0% 50%; }
    }
  </style>
</head>
<body>

<?php if (!$authed): ?>
  <!-- Minimal, centered login (no header/footer) -->
  <div class="center">
    <div class="card">
      <?php if ($hasPassword): ?>
        <!-- Password required -->
        <h1><?= htmlspecialchars(t('viewer.recipient_password')) ?></h1>
        <p class="file-note"><?= htmlspecialchars(t('viewer.new_file')) ?><?= htmlspecialchars($filename) ?></p>
        <?php if (!empty($_GET['e'])): ?><div class="error"><?= htmlspecialchars(t('viewer.wrong_pwd')) ?></div><?php endif; ?>
        <form method="post" action="/auth.php">
          <input type="hidden" name="action" value="recipient_login">
          <input type="hidden" name="code" value="<?= htmlspecialchars($code) ?>">
          <div class="row">
            <input type="password" name="password" placeholder="<?= htmlspecialchars(t('viewer.recipient_password')) ?>" required>
            <button type="submit"><?= htmlspecialchars(t('viewer.open')) ?></button>
          </div>
          <div class="consent">
            <label for="consent">
              <input type="checkbox" id="consent" name="consent" required>
              <span>
                <?= htmlspecialchars(t('viewer.checkbox')) ?>
                <a href="/privacy.php" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars(t('viewer.link')) ?></a>
              </span>
            </label>
          </div>
        </form>
      <?php else: ?>
        <!-- No password - consent only -->
        <h1><?= htmlspecialchars(t('viewer.access_document')) ?></h1>
        <p class="file-note"><?= htmlspecialchars(t('viewer.new_file')) ?><?= htmlspecialchars($filename) ?></p>
        <form method="post" action="/auth.php">
          <input type="hidden" name="action" value="recipient_login">
          <input type="hidden" name="code" value="<?= htmlspecialchars($code) ?>">
          <input type="hidden" name="password" value="">
          <div class="consent">
            <label for="consent">
              <input type="checkbox" id="consent" name="consent" required>
              <span>
                <?= htmlspecialchars(t('viewer.checkbox')) ?>
                <a href="/privacy.php" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars(t('viewer.link')) ?></a>
              </span>
            </label>
          </div>
          <button type="submit" style="width:100%; margin-top:0.5rem;"><?= htmlspecialchars(t('viewer.open_document')) ?></button>
        </form>
      <?php endif; ?>
    </div>
  </div>
  <script>
  (function() {
    const consent = document.getElementById('consent');
    const button = document.querySelector('button[type="submit"]');
    if (!consent || !button) return;
    button.disabled = true;
    const toggle = () => { button.disabled = !consent.checked; };
    consent.addEventListener('change', toggle);
    toggle();
  })();
  </script>
</body>
</html>
<?php exit; endif; ?>

<!-- Full-screen iframe -->
<iframe id="pdf" src="/static/pdfjs/web/viewer.html?file=<?= urlencode('/serve.php?code='.$code) ?>"></iframe>

<script>
(function(){
  // --- Settings & session context ---
  const settings   = <?= $file['settings_json'] ? $file['settings_json'] : '{}' ?>;
  const sessionId  = (crypto.randomUUID && crypto.randomUUID()) || ([1e7]+-1e3+-4e3+-8e3+-1e11).replace(/[018]/g,c=>(c ^ crypto.getRandomValues(new Uint8Array(1))[0]&15>>c/4).toString(16));
  const code       = <?= json_encode($code) ?>;
  const DEBUG_SCROLL = /[?&]debug=1/i.test(location.search);
  const UA = navigator.userAgent || '';
  const IS_SAFARI = /^((?!chrome|android).)*safari/i.test(UA);
  const IS_IOS    = /iP(hone|ad|od)/.test(UA);
  const iframe = document.getElementById('pdf');

  // Per-load timing
  let start = Date.now();
  let visibleMs = 0;
  let lastVisTs = document.visibilityState === 'visible' ? Date.now() : null;

  // Per-load scroll state (reset on iframe load)
  let maxScroll = 0;
  let lastSentScroll = 0;

  // Use the PDF iframe's window to send a 1x1 GET pixel
  function sendPixelFromIframe(win, type, extra) {
    try {
      // FIX: Build base payload FIRST, then merge extra to allow overrides
      const basePayload = {
        type,
        code,
        session_id: sessionId,
        visible_ms: visibleMs,
        max_scroll_pct: Math.round(maxScroll),
        t: Date.now().toString()
      };
      
      // Merge extra AFTER to ensure it overrides (especially visible_ms)
      const payload = Object.assign({}, basePayload, (extra || {}));

      const q = new URLSearchParams();
      for (const [k,v] of Object.entries(payload)) q.set(k, String(v));

      const img = new win.Image();
      img.referrerPolicy = 'no-referrer-when-downgrade';
      img.src = '/track.php?img=1&' + q.toString();
    } catch (e) {
      // fallback to parent if something odd happens
      const basePayload = {
        type, 
        code, 
        session_id: sessionId,
        visible_ms: String(visibleMs),
        max_scroll_pct: String(Math.round(maxScroll)),
        t: String(Date.now())
      };
      const payload = Object.assign({}, basePayload, (extra || {}));
      const q = new URLSearchParams(payload);
      const img = new Image();
      img.src = '/track.php?img=1&' + q.toString();
    }
  }

  function runWithIframeWin(cb){
    if (iframe && iframe.contentWindow) {
      cb(iframe.contentWindow);
    } else if (iframe) {
      iframe.addEventListener('load', () => cb(iframe.contentWindow), { once:true });
    }
  }

  // --- robust sender (Safari-safe) ---
  async function send(type, extra) {
    const payload = {
      type, code, session_id: sessionId,
      ts: new Date().toISOString(),
      visible_ms: visibleMs,
      max_scroll_pct: Math.round(maxScroll)
    };
    if (extra) Object.assign(payload, extra);

    const url = '/track.php';
    const bodyStr = JSON.stringify(payload);

    // A) Beacon (preferred)
    try {
      if (navigator.sendBeacon) {
        const ok = navigator.sendBeacon(url, new Blob([bodyStr], { type: 'text/plain;charset=UTF-8' }));
        if (ok) return;
      }
    } catch {}

    // B) fetch keepalive
    try {
      await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: bodyStr,
        keepalive: true,
        credentials: 'same-origin',
        cache: 'no-store',
      });
      return;
    } catch {}

    // C) image GET fallback
    try {
      const q = new URLSearchParams({
        type, code, session_id: sessionId,
        visible_ms: String(visibleMs),
        max_scroll_pct: String(Math.round(maxScroll)),
        t: String(Date.now())
      });
      const img = new Image();
      img.src = '/track.php?img=1&' + q.toString();
    } catch {}
  }

  const PROGRESS_STEP = 1;
  function maybeSendProgress(force=false){
    if (!settings.track_scroll) return;
    const rounded = Math.min(100, Math.round(maxScroll));
    if (force || rounded === 100 || rounded >= lastSentScroll + PROGRESS_STEP){
      lastSentScroll = rounded;
      send('progress', { max_scroll_pct: rounded });
      if (DEBUG_SCROLL) console.log('[progress->server]', rounded);
    }
  }

  // Send open
  if (settings.track_open) {
    if (IS_SAFARI || IS_IOS) {
      runWithIframeWin(w => sendPixelFromIframe(w, 'open'));
    } else {
      send('open');
    }
  }

  // Heartbeat timer - WICHTIG für Safari da close-Events oft fehlschlagen
  if (settings.track_time) {
    setInterval(() => {
      // Aktualisiere visibleMs VOR dem Senden
      if (lastVisTs && document.visibilityState === 'visible') {
        const now = Date.now();
        visibleMs += now - lastVisTs;
        lastVisTs = now; // Reset für nächstes Intervall
      }
      
      if (IS_SAFARI || IS_IOS) {
        const w = iframe.contentWindow;
        if (w) {
          sendPixelFromIframe(w, 'heartbeat', { visible_ms: visibleMs });
        }
      } else {
        send('heartbeat');
      }
    }, 15000); // Alle 15 Sekunden
  }

  if (settings.track_scroll) setInterval(()=> maybeSendProgress(), 5000);

  // Visibility → active time
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
      lastVisTs = Date.now();
    } else if (lastVisTs) {
      visibleMs += Date.now() - lastVisTs;
      lastVisTs = null;
    }
  });

  // ---------- NEW per-page, height-weighted tracking ----------
  function bindPdfjsScroll(){
    try {
      const win    = iframe.contentWindow;
      const idoc   = iframe.contentDocument || win.document;
      const vc     = idoc.getElementById('viewerContainer');
      const viewer = idoc.getElementById('viewer');
      const app    = win && win.PDFViewerApplication;
      if (!vc || !viewer || !app || !app.pdfViewer) return false;

      // Tunables
      const REQUIRE_LAST_PAGE_TAIL_SLICES = 3;
      const SLICE_TARGET_COUNT            = 40;
      const MIN_SLICE_PX                  = 24;
      const DEBUG = DEBUG_SCROLL;

      /** Per-page model */
      const pages = [];
      let io = null, ro = null;

      function buildPagesAndSlices(){
        pages.length = 0;

        const count = app.pdfViewer.pagesCount || (app.pdfDocument && app.pdfDocument.numPages) || 0;
        for (let n = 1; n <= count; n++) {
          const pv = app.pdfViewer.getPageView(n - 1);
          if (!pv || !pv.div) continue;
          const pageEl = pv.div;
          const ph = Math.max(1, pageEl.offsetHeight);

          // Remove old slice container if exists
          let overlay = pageEl.querySelector(':scope > .sf-slices');
          if (overlay) overlay.remove();

          // Create overlay container
          overlay = idoc.createElement('div');
          overlay.className = 'sf-slices';
          overlay.style.cssText = 'position:absolute; inset:0; pointer-events:none;';
          const cs = win.getComputedStyle(pageEl);
          if (cs.position === 'static') pageEl.style.position = 'relative';
          pageEl.appendChild(overlay);

          // Decide number of slices
          const approx = Math.max(1, Math.round(ph / Math.max(MIN_SLICE_PX, Math.ceil(ph / SLICE_TARGET_COUNT))));
          const sliceCount = Math.max(1, approx);

          const slices = [];
          for (let i = 0; i < sliceCount; i++) {
            const top = Math.round((i * ph) / sliceCount);
            const next = Math.round(((i + 1) * ph) / sliceCount);
            const h = Math.max(1, next - top);

            const s = idoc.createElement('div');
            s.className = 'sf-slice';
            s.style.cssText = `position:absolute; left:0; right:0; top:${top}px; height:${h}px;`;
            overlay.appendChild(s);

            slices.push({ el: s, top, h, seen: false });
          }

          pages.push({
            n, el: pageEl, height: ph, slices,
            get totalPx(){ return this.slices.reduce((a,b)=>a+b.h,0); },
            get seenPx(){ return this.slices.reduce((a,b)=>a+(b.seen?b.h:0),0); },
          });
        }
      }

      function observeSlices(){
        if (io) io.disconnect();
        if (!('IntersectionObserver' in win)) return;

        io = new win.IntersectionObserver((entries) => {
          let updated = false;
          for (const e of entries) {
            if (e.intersectionRatio > 0) {
              const p = pages.find(pg => pg.slices.some(sl => sl.el === e.target));
              if (!p) continue;
              const sl = p.slices.find(sl => sl.el === e.target);
              if (sl && !sl.seen) {
                sl.seen = true;
                updated = true;
              }
            }
          }
          if (updated) computeAndPush('io');
        }, { root: vc, threshold: [0] });

        for (const p of pages) {
          for (const sl of p.slices) io.observe(sl.el);
        }
      }

      function watchResizes(){
        if (!('ResizeObserver' in win)) return;
        if (ro) ro.disconnect();
        ro = new win.ResizeObserver(() => {
          buildPagesAndSlices();
          observeSlices();
          computeAndPush('resize');
        });
        for (const p of pages) ro.observe(p.el);
      }

      function lastPageTailSeen(){
        const last = pages[pages.length - 1];
        if (!last) return false;
        const tail = last.slices.slice(-REQUIRE_LAST_PAGE_TAIL_SLICES);
        return tail.every(sl => sl.seen);
      }

      function computePct(){
        const total = pages.reduce((a,p)=> a + p.totalPx, 0) || 1;
        const seen  = pages.reduce((a,p)=> a + p.seenPx , 0);
        let pct = (seen / total) * 100;

        if (!lastPageTailSeen()) pct = Math.min(pct, 99);
        if (pct > 99.9) pct = 99.9;
        return pct;
      }

      function computeAndPush(src='tick'){
        const pct = computePct();
        if (pct > maxScroll) {
          maxScroll = pct;
          sendPixelFromIframe(win, 'progress', { max_scroll_pct: Math.round(maxScroll) });
          maybeSendProgress();
        }
        if (lastPageTailSeen() && maxScroll < 100) {
          maxScroll = 100;
          sendPixelFromIframe(win, 'progress', { max_scroll_pct: 100 });
          maybeSendProgress(true);
        }

        if (DEBUG) {
          const tailSeen = lastPageTailSeen();
          console.debug('[bands]', src, 
            'pct=', Math.round(computePct()),
            'max=', Math.round(maxScroll),
            'pages=', pages.length,
            'lastTailSeen=', tailSeen
          );
        }
      }

      // Rebuild on key PDF.js events
      const eb = app.eventBus || app._eventBus;
      if (eb && eb.on) {
        eb.on('pagesloaded', () => { buildPagesAndSlices(); observeSlices(); watchResizes(); computeAndPush('pagesloaded'); });
        eb.on('pagerendered', () => computeAndPush('pagerendered'));
        eb.on('pagechanging', () => computeAndPush('pagechange'));
      }

      // Fallback sampler
      const sampler = setInterval(() => {
        if (!document.body.contains(iframe)) { clearInterval(sampler); return; }
        computeAndPush('interval');
      }, 400);

      // Initial
      buildPagesAndSlices();
      observeSlices();
      watchResizes();
      computeAndPush('init');

      return true;
    } catch (e) {
      console.error('[bindPdfjsScroll error]', e);
      return false;
    }
  }
  // ---------- END new tracking ----------

  // Reset per load + bind scroll tracking + force page 1
  iframe.addEventListener('load', () => {
    // reset per-load state
    maxScroll = 0;
    lastSentScroll = 0;
    start = Date.now();
    if (document.visibilityState === 'visible') lastVisTs = Date.now();

    let tries = 0, bound = false, paged = false;
    const t = setInterval(() => {
      // FIX: Nur binden wenn track_scroll aktiviert ist
      if (!bound && settings.track_scroll) {
        bound = !!bindPdfjsScroll();
      }

      const app = iframe.contentWindow && iframe.contentWindow.PDFViewerApplication;
      if (!paged && app && app.pdfViewer) {
        try {
          const ls = iframe.contentWindow.localStorage;
          Object.keys(ls).forEach(k => { if (k.startsWith('pdfjs.')) ls.removeItem(k); });
        } catch {}
        app.pdfViewer.currentPageNumber = 1;
        paged = true;
        if (settings.track_scroll) maybeSendProgress(true);
      }

      if ((bound && paged) || (!settings.track_scroll && paged) || ++tries > 50) {
        clearInterval(t);
      }
    }, 150);
  });

  // FIX: pagehide mit mehrfacher Safari-Absicherung
  window.addEventListener('pagehide', () => {
    // Nur addieren wenn noch nicht durch visibilitychange gemacht
    if (lastVisTs) {
      visibleMs += Date.now() - lastVisTs;
      lastVisTs = null;
    }
    
    const extra = { 
      total_ms: Date.now() - start,
      visible_ms: visibleMs,
      max_scroll_pct: Math.round(maxScroll) 
    };
    
    if (IS_SAFARI || IS_IOS) {
      const win = iframe.contentWindow;
      
      // Strategie 1: Mehrfache Pixel-Requests (erhöht Erfolgsrate)
      for (let i = 0; i < 3; i++) {
        setTimeout(() => {
          if (win) {
            sendPixelFromIframe(win, 'close', extra);
          } else {
            const q = new URLSearchParams({
              type: 'close',
              code,
              session_id: sessionId,
              visible_ms: String(extra.visible_ms),
              total_ms: String(extra.total_ms),
              max_scroll_pct: String(extra.max_scroll_pct),
              t: String(Date.now())
            });
            const img = new Image();
            img.src = '/track.php?img=1&' + q.toString();
          }
        }, i * 10); // 0ms, 10ms, 20ms
      }
      
      // Strategie 2: Auch vom parent window versuchen
      const q = new URLSearchParams({
        type: 'close',
        code,
        session_id: sessionId,
        visible_ms: String(extra.visible_ms),
        total_ms: String(extra.total_ms),
        max_scroll_pct: String(extra.max_scroll_pct),
        t: String(Date.now())
      });
      const img = new Image();
      img.src = '/track.php?img=1&' + q.toString();
      
    } else {
      send('close', extra);
    }
  }, { capture:true });

  // FIX: beforeunload mit mehrfacher Safari-Absicherung
  window.addEventListener('beforeunload', () => {
    // Nur addieren wenn noch nicht durch visibilitychange gemacht
    if (lastVisTs) {
      visibleMs += Date.now() - lastVisTs;
      lastVisTs = null;
    }
    
    const extra = { 
      total_ms: Date.now() - start,
      visible_ms: visibleMs,
      max_scroll_pct: Math.round(maxScroll) 
    };
    
    if (IS_SAFARI || IS_IOS) {
      const win = iframe.contentWindow;
      
      // Mehrfache Versuche
      for (let i = 0; i < 3; i++) {
        if (win) {
          sendPixelFromIframe(win, 'close', extra);
        }
        const q = new URLSearchParams({
          type: 'close',
          code,
          session_id: sessionId,
          visible_ms: String(extra.visible_ms),
          total_ms: String(extra.total_ms),
          max_scroll_pct: String(extra.max_scroll_pct),
          t: String(Date.now())
        });
        const img = new Image();
        img.src = '/track.php?img=1&' + q.toString();
      }
    } else {
      send('close', extra);
    }
  });

  // Flush when tab goes to background - KRITISCH für Safari
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden') {
      if (lastVisTs) visibleMs += Date.now() - lastVisTs;
      
      if (IS_SAFARI || IS_IOS) {
        const w = iframe.contentWindow;
        
        // Für Safari: Sende sowohl heartbeat ALS AUCH close
        // Da wir nicht wissen ob der User zurückkommt oder den Tab schließt
        const extra = {
          visible_ms: visibleMs,
          total_ms: Date.now() - start,
          max_scroll_pct: Math.round(maxScroll)
        };
        
        if (w) {
          // Beide Events senden für maximale Datensicherheit
          sendPixelFromIframe(w, 'heartbeat', extra);
          sendPixelFromIframe(w, 'close', extra);
        }
        
        // Auch parent-window Fallback
        const q = new URLSearchParams({
          type: 'close',
          code,
          session_id: sessionId,
          visible_ms: String(extra.visible_ms),
          total_ms: String(extra.total_ms),
          max_scroll_pct: String(extra.max_scroll_pct),
          t: String(Date.now())
        });
        const img = new Image();
        img.src = '/track.php?img=1&' + q.toString();
        
      } else {
        send('heartbeat');
      }
      lastVisTs = null; // Wichtig: nicht doppelt zählen
    } else {
      lastVisTs = Date.now();
    }
  });

})();
</script>
</body>
</html>
