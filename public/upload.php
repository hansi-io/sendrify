<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
// Suppress ugly PHP warnings for better UX
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
ini_set('display_errors', '0');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/util.php';
require_once __DIR__ . '/../config/demo.php';
session_start();
require_once __DIR__ . '/../config/i18n.php';

// In demo mode, require login for upload
if (is_demo_mode() && empty($_SESSION['user_id'])) {
    header('Location: /account/login.php?next=' . urlencode('/upload.php'));
    exit;
}

$err = null;

// Maximum file size (10MB for demo, can be adjusted)
$maxFileSize = is_demo_mode() ? 10 * 1024 * 1024 : 50 * 1024 * 1024; // 10MB in demo, 50MB otherwise

// Check if POST data was too large (happens before PHP can process the request properly)
// This catches the "POST Content-Length exceeds limit" error
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES)) {
    $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
    if ($contentLength > 0) {
        $postMaxSize = ini_get('post_max_size');
        $postMaxBytes = return_bytes($postMaxSize);
        $err = 'Upload failed: File is too large. The server limit is ' . $postMaxSize . '. Please choose a smaller file.';
    }
}

/**
 * Convert PHP size format (like "8M") to bytes
 */
function return_bytes($size_str) {
    if (empty($size_str)) return 0;
    
    $size_str = trim($size_str);
    $last = strtolower($size_str[strlen($size_str)-1]);
    $size_str = (int)$size_str;
    
    switch($last) {
        case 'g': $size_str *= 1024;
        case 'm': $size_str *= 1024;
        case 'k': $size_str *= 1024;
    }
    
    return $size_str;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$err) {
  // Check if file was uploaded
  if (!isset($_FILES['pdf'])) {
    $err = 'No file uploaded.';
  } 
  // Check for upload errors
  elseif ($_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
    switch ($_FILES['pdf']['error']) {
      case UPLOAD_ERR_INI_SIZE:
      case UPLOAD_ERR_FORM_SIZE:
        $err = 'File is too large. Maximum size is ' . ($maxFileSize / 1024 / 1024) . 'MB.';
        break;
      case UPLOAD_ERR_PARTIAL:
        $err = 'File upload was interrupted. Please try again.';
        break;
      case UPLOAD_ERR_NO_FILE:
        $err = 'No file was uploaded.';
        break;
      case UPLOAD_ERR_NO_TMP_DIR:
        $err = 'Server error: Missing temporary folder.';
        break;
      case UPLOAD_ERR_CANT_WRITE:
        $err = 'Server error: Failed to write file to disk.';
        break;
      case UPLOAD_ERR_EXTENSION:
        $err = 'Server error: File upload stopped by extension.';
        break;
      default:
        $err = 'Upload failed with error code: ' . $_FILES['pdf']['error'];
    }
  } 
  // Check file size manually as well
  elseif ($_FILES['pdf']['size'] > $maxFileSize) {
    $sizeInMB = round($_FILES['pdf']['size'] / 1024 / 1024, 2);
    $maxInMB = $maxFileSize / 1024 / 1024;
    $err = "File is too large ({$sizeInMB}MB). Maximum size is {$maxInMB}MB.";
  } 
  else {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $_FILES['pdf']['tmp_name']);
    if ($mime !== 'application/pdf') {
      $err = 'File must be a PDF.';
    } else {
      // Ensure unique short code
      $code = generate_code(6);
      $stmt = $pdo->prepare('SELECT 1 FROM files WHERE short_code=?');
      while (true) {
        $stmt->execute([$code]);
        if (!$stmt->fetch()) break;
        $code = generate_code(6);
      }
      // Keep original filename with code prefix for uniqueness
      $originalName = pathinfo($_FILES['pdf']['name'], PATHINFO_FILENAME);
      $safeOriginalName = preg_replace('/[^a-zA-Z0-9_\-äöüÄÖÜß]/', '_', $originalName);
      $safeOriginalName = preg_replace('/_+/', '_', $safeOriginalName); // Remove multiple underscores
      $safeOriginalName = trim($safeOriginalName, '_');
      if (empty($safeOriginalName)) {
        $safeOriginalName = 'document';
      }
      $storePath = __DIR__ . '/../storage/' . $code . '_' . $safeOriginalName . '.pdf';
      if (!move_uploaded_file($_FILES['pdf']['tmp_name'], $storePath)) {
        $err = 'Server error moving file.';
      } else {
        $recipient_plain = bin2hex(random_bytes(2)); // 4 hex chars
        $sender_plain    = bin2hex(random_bytes(3)); // 6 hex chars

        $settings = [
          'track_open' => true,
          'track_time' => true,
          'track_scroll' => true
        ];

        $ownerId = $_SESSION['user_id'] ?? null;

        // Store original filename for display
        $originalFilename = pathinfo($_FILES['pdf']['name'], PATHINFO_FILENAME);

        if ($ownerId) {
          $ins = $pdo->prepare(
            'INSERT INTO files (
              short_code, file_path, original_filename,
              recipient_pwd_hash, recipient_pwd_plain,
              sender_pwd_hash,    sender_pwd_plain,
              settings_json, owner_id
            )
            VALUES (?,?,?,?,?,?,?,?,?)'
          );
          $ins->execute([
            $code, $storePath, $originalFilename,
            password_hash($recipient_plain, PASSWORD_DEFAULT), $recipient_plain,
            password_hash($sender_plain,    PASSWORD_DEFAULT), $sender_plain,
            json_encode($settings),
            $ownerId
          ]);
        } else {
          $ins = $pdo->prepare(
            'INSERT INTO files (
              short_code, file_path, original_filename,
              recipient_pwd_hash, recipient_pwd_plain,
              sender_pwd_hash,    sender_pwd_plain,
              settings_json
            )
            VALUES (?,?,?,?,?,?,?,?)'
          );
          $ins->execute([
            $code, $storePath, $originalFilename,
            password_hash($recipient_plain, PASSWORD_DEFAULT), $recipient_plain,
            password_hash($sender_plain,    PASSWORD_DEFAULT), $sender_plain,
            json_encode($settings)
          ]);
        }


        $_SESSION['new_file'] = [
          'code'      => $code,
          'recipient' => $recipient_plain,
          'sender'    => $sender_plain,
          'ts'        => time(),     // optional, helpful for freshness checks later
        ];
        header('Location: /upload.php?success=1'); exit;
      }
    }
  }
}
?>
<!doctype html>
<html data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars(t('upload.title')) ?> - Sendrify</title>
  <link rel="stylesheet" href="https://unpkg.com/@picocss/pico@latest/css/pico.min.css">
  <link rel="stylesheet" href="/static/app.css">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<?php include __DIR__ . '/partials/header.php'; ?>  
<main class="container">
  <div class="animate-fade-in">
  <?php 
    // Cleanup old demo files
    if (is_demo_mode()) {
      cleanup_demo_files($pdo);
    }
    if (is_demo_mode()): ?>
    <div class="demo-banner">
      🎯 Demo Mode - Files are automatically deleted after 1 hour
    </div>
    <?php endif; ?>
  <h1><?= htmlspecialchars(t('upload.title')) ?></h1>
<?php if (!empty($_GET['success']) && isset($_SESSION['new_file'])):
  $nf   = $_SESSION['new_file'];
  $host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8080';
  $base = 'http://' . $host;
  $viewerUrl    = $base . '/viewer.php?code='    . $nf['code'];
  $analyticsUrl = $base . '/analytics.php?code=' . $nf['code'];
?>
  <article class="animate-fade-in">
    <header>
      <h2><?= htmlspecialchars(t('upload.share_header')) ?></h2>
      <p><?= htmlspecialchars(t('upload.share_sub')) ?></p>
    </header>

<div class="grid" data-cols="2">
      <!-- Recipient column -->
  <div>
            <h3><?= htmlspecialchars(t('upload.recipient')) ?></h3>
          <p class="copyline">
      <strong><?= htmlspecialchars(t('upload.link')) ?>:</strong>
      <span class="copyline-field">
        <code title="<?= htmlspecialchars($viewerUrl) ?>"><?= htmlspecialchars($viewerUrl) ?></code>
        <button type="button" class="icon-btn copy-icon"
                data-copy="<?php echo  htmlspecialchars($viewerUrl) ?>"
                aria-label="Copy recipient link" title="Copy">
          <svg viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
        </button>
      </span>
    </p>

    <p class="copyline">
      <strong><?php echo  htmlspecialchars(t('upload.password')) ?>:</strong>
      <span class="copyline-field">
        <code><?php echo  htmlspecialchars($nf['recipient']) ?></code>
        <button type="button" class="icon-btn copy-icon"
                data-copy="<?php echo  htmlspecialchars($nf['recipient']) ?>"
                aria-label="Copy recipient password" title="Copy">
          <svg viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
        </button>
      </span>
    </p>

  </div>

  <!-- Analytics column -->
  <?php $isLogged = !empty($_SESSION['user_id']); ?>
  <div>
    <h3><?php echo  htmlspecialchars(t('upload.analytics')) ?></h3>
    <?php if ($isLogged): ?>
      <p class="copyline">
        <strong><?php echo  htmlspecialchars(t('upload.link')) ?>:</strong>
        <span class="copyline-field">
          <code><?php echo  htmlspecialchars($analyticsUrl) ?></code>
          <button type="button" class="icon-btn copy-icon" data-copy="<?php echo  htmlspecialchars($analyticsUrl) ?>">
            <svg viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
          </button>
        </span>
      </p>
      <p class="copyline">
        <strong><?php echo  htmlspecialchars(t('upload.sender_password')) ?>:</strong>
        <span class="copyline-field">
          <code><?php echo  htmlspecialchars($nf['sender']) ?></code>
          <button type="button" class="icon-btn copy-icon" data-copy="<?php echo  htmlspecialchars($nf['sender']) ?>">
            <svg viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
          </button>
        </span>
      </p>
    <?php else: ?>
      <p><em><?php echo  is_demo_mode() ? htmlspecialchars(t('upload.login_for_analytics')) : htmlspecialchars(t('upload.create_account_for_analytics')) ?></em></p>
      <p>
        <?php if (!is_demo_mode()): ?>
        <a class="secondary" href="/account/register.php?next=<?php echo  urlencode('/analytics.php?code='.$nf['code']) ?>">
          <?php echo  htmlspecialchars(t('upload.create_account')) ?>
        </a>
        <?php endif; ?>
        <a class="secondary" href="/account/login.php?next=<?php echo  urlencode('/analytics.php?code='.$nf['code']) ?>">
          <?php echo  is_demo_mode() ? htmlspecialchars(t('nav.login')) : htmlspecialchars(t('upload.already_account')) ?>
        </a>
      </p>
      <details>
        <summary><?php echo  htmlspecialchars(t('upload.keep_for_later')) ?></summary>
        <p class="copyline">
          <strong><?php echo  htmlspecialchars(t('upload.sender_password')) ?>:</strong>
          <span class="copyline-field">
            <code><?php echo  htmlspecialchars($nf['sender']) ?></code>
            <button type="button" class="icon-btn copy-icon" data-copy="<?php echo  htmlspecialchars($nf['sender']) ?>">
              <svg viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
            </button>
          </span>
        </p>
        <p class="copyline">
          <strong><?php echo  htmlspecialchars(t('upload.analytics')) ?> <?php echo  htmlspecialchars(t('upload.link')) ?>:</strong>
          <span class="copyline-field">
            <code><?php echo  htmlspecialchars($analyticsUrl) ?></code>
            <button type="button" class="icon-btn copy-icon" data-copy="<?php echo  htmlspecialchars($analyticsUrl) ?>">
              <svg viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
            </button>
          </span>
        </p>
      </details>
    <?php endif; ?>
  </div>


</div>

    <p style="margin-top: var(--space-lg);">
      <a href="/upload.php" class="btn accent"><?php echo htmlspecialchars(t('upload.upload_another')) ?></a>
      <?php if (!empty($_SESSION['user_id'])): ?>
      <a href="/admin.php" class="btn secondary"><?php echo htmlspecialchars(t('nav.my_files')) ?></a>
      <?php endif; ?>
    </p>
  </article>

  <!-- Copy handler with visual feedback -->
  <script>
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.copy-icon');
    if (!btn) return;

    const text = btn.dataset.copy || '';
    try {
      await navigator.clipboard.writeText(text);
      
      // Add success class for visual feedback
      btn.classList.add('copied');
      
      // Reset after 1.5 seconds
      setTimeout(() => {
        btn.classList.remove('copied');
      }, 1500);
    } catch (err) {
      alert('Kopieren fehlgeschlagen');
    }
  });
  </script>

<?php 
  // Clear the session data so refreshing doesn't show old data
  unset($_SESSION['new_file']);
?>

<?php else: ?>
<!-- Normal Upload Page (not success) -->



<?php if (!empty($err)): ?>
<div class="alert alert-error">
  <strong>⚠️ Upload Error:</strong> <?= htmlspecialchars($err) ?>
</div>
<?php endif; ?>

<form id="uploadForm" method="post" enctype="multipart/form-data">
  <div id="dropzone" class="dropzone" role="button" tabindex="0" aria-label="Upload PDF" aria-describedby="dzHelp">
    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none">
      <path d="M12 16V4m0 0l-4 4m4-4l4 4M6 20h12a2 2 0 002-2v-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>

    <div class="dz-text">
      <div class="dz-primary"><?= htmlspecialchars(t('upload.drop_primary')) ?></div>
      <div id="dzHelp" class="dz-secondary">
        <?= htmlspecialchars(t('upload.drop_secondary')) ?>
        <br>
        <span style="opacity: 0.7; font-size: 0.875rem;">Maximum: <?= ($maxFileSize / 1024 / 1024) ?>MB</span>
      </div>
    </div>

    <!-- filename & actions appear after selection -->
    <div class="file-selected">
      <div id="fileName" class="file-name" aria-live="polite"></div>
      <div class="dz-actions">
        <button type="button" id="changeFileBtn" class="secondary sm"><?= htmlspecialchars(t('upload.change_file')) ?></button>
        <button type="button" id="clearFileBtn" class="secondary sm"><?= htmlspecialchars(t('upload.remove')) ?></button>
      </div>
    </div>
  </div>

  <!-- Hidden input still used for form submit -->
  <input id="pdfInput" type="file" name="pdf" accept="application/pdf" required style="display:none">

  <button type="submit" class="accent" style="margin-top: var(--space-lg);"><?= htmlspecialchars(t('upload.upload_btn')) ?></button>
</form>

<script>
(function(){
  // Prevent browser from navigating when dropping outside
  document.addEventListener('dragover', e => e.preventDefault());
  document.addEventListener('drop', e => e.preventDefault());

  const dz = document.getElementById('dropzone');
  const input = document.getElementById('pdfInput');
  const fileNameEl = document.getElementById('fileName');
  const changeBtn = document.getElementById('changeFileBtn');
  const clearBtn  = document.getElementById('clearFileBtn');
  const form = document.getElementById('uploadForm');
  
  // Maximum file size in bytes (10MB for demo)
  const MAX_FILE_SIZE = <?php echo  $maxFileSize ?>;
  const MAX_FILE_SIZE_MB = MAX_FILE_SIZE / 1024 / 1024;

  function hasFile(){ return !!(input.files && input.files.length); }
  
  function validateFile(file) {
    // Check if it's a PDF
    const isPdf = file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf');
    if (!isPdf) {
      alert('Please select a PDF file.');
      return false;
    }
    
    // Check file size
    if (file.size > MAX_FILE_SIZE) {
      const sizeMB = (file.size / 1024 / 1024).toFixed(2);
      alert(`File is too large (${sizeMB}MB). Maximum size is ${MAX_FILE_SIZE_MB}MB.`);
      return false;
    }
    
    return true;
  }
  
  function setFile(file){
    if (file) {
      // Validate before setting
      if (!validateFile(file)) {
        return;
      }
      
      const dt = new DataTransfer();
      dt.items.add(file);
      input.files = dt.files;
    } else {
      input.value = ''; // clear
    }
    syncUI();
  }
  
  function syncUI(){
    const f = hasFile() ? input.files[0] : null;
    dz.classList.toggle('has-file', !!f);
    
    if (f) {
      const sizeMB = (f.size / 1024 / 1024).toFixed(2);
      fileNameEl.textContent = `${f.name} (${sizeMB}MB)`;
    } else {
      fileNameEl.textContent = '';
    }
    
    // Make dropzone non-activating when a file is present (keyboard too)
    dz.setAttribute('aria-label', f ? 'PDF selected' : 'Upload PDF');
  }

  // Only open picker when NO file is selected
  dz.addEventListener('click', () => {
    if (!hasFile()) input.click();
  });
  dz.addEventListener('keydown', (e) => {
    if ((e.key === 'Enter' || e.key === ' ') && !hasFile()) {
      e.preventDefault(); input.click();
    }
  });

  // Drag visuals
  ['dragenter','dragover'].forEach(ev => dz.addEventListener(ev, e => {
    e.preventDefault(); e.stopPropagation(); dz.classList.add('is-dragover');
  }));
  ['dragleave','dragend'].forEach(ev => dz.addEventListener(ev, e => {
    dz.classList.remove('is-dragover');
  }));

  // Handle drop
  dz.addEventListener('drop', e => {
    e.preventDefault(); e.stopPropagation();
    dz.classList.remove('is-dragover');
    const files = e.dataTransfer.files;
    if (!files || !files.length) return;
    const file = files[0];
    setFile(file);
    // Optional auto-submit after drop:
    // form.submit();
  });

  // Native picker change
  input.addEventListener('change', () => syncUI());

  // Actions
  changeBtn.addEventListener('click', () => input.click());
  clearBtn.addEventListener('click', () => setFile(null));

  // Initial state
  syncUI();

  // (Keep your existing "Copy link" button handler below if present)
})();
</script>

<?php endif; // End of else (not success page) ?>

</div>
</main>
 <?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
