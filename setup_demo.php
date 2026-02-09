#!/usr/bin/env php
<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
/**
 * Demo Setup Script
 * 
 * This script initializes the demo environment:
 * - Creates demo users
 * - Sets up sample files
 * - Configures database
 * 
 * Usage: php setup_demo.php
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/demo.php';
require_once __DIR__ . '/config/util.php';

echo "🚀 Setting up Sendrify Demo Environment...\n\n";

// Step 1: Initialize demo users
echo "1. Creating demo users...\n";
try {
    init_demo_users($pdo);
    echo "   ✅ Demo users created successfully\n";
    echo "      - demo@sendrify.local / demo123\n";
    echo "      - admin@sendrify.local / admin123\n\n";
} catch (Exception $e) {
    echo "   ❌ Error creating demo users: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Step 2: Create sample PDF files
echo "2. Creating sample PDF files...\n";

// Get demo user ID
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$stmt->execute([DEMO_USER_EMAIL]);
$demoUser = $stmt->fetch();

if (!$demoUser) {
    echo "   ❌ Demo user not found\n";
    exit(1);
}

$demoUserId = (int)$demoUser['id'];

// Create storage directory if it doesn't exist
$storageDir = __DIR__ . '/storage';
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0755, true);
}

// Sample files configuration
$sampleFiles = [
    [
        'name' => 'sample_invoice',
        'title' => 'Sample Invoice',
        'recipient_pwd' => 'inv2024',
        'sender_pwd' => 'track123'
    ],
    [
        'name' => 'sample_contract',
        'title' => 'Sample Contract',
        'recipient_pwd' => 'read123',
        'sender_pwd' => 'view456'
    ],
    [
        'name' => 'sample_report',
        'title' => 'Sample Report',
        'recipient_pwd' => 'report1',
        'sender_pwd' => 'stats99'
    ]
];

$createdFiles = 0;

foreach ($sampleFiles as $sampleFile) {
    // Generate unique short code
    $code = generate_code(6);
    $stmt = $pdo->prepare('SELECT 1 FROM files WHERE short_code=?');
    while (true) {
        $stmt->execute([$code]);
        if (!$stmt->fetch()) break;
        $code = generate_code(6);
    }
    
    $storePath = $storageDir . '/' . $code . '.pdf';
    
    // Create a simple PDF with text
    $pdfContent = create_sample_pdf($sampleFile['title']);
    file_put_contents($storePath, $pdfContent);
    
    // Insert into database
    $settings = [
        'track_open' => true,
        'track_time' => true,
        'track_scroll' => true
    ];
    
    $ins = $pdo->prepare(
        'INSERT INTO files (
          short_code, file_path,
          recipient_pwd_hash, recipient_pwd_plain,
          sender_pwd_hash, sender_pwd_plain,
          settings_json, owner_id
        )
        VALUES (?,?,?,?,?,?,?,?)'
    );
    
    $ins->execute([
        $code, 
        $storePath,
        password_hash($sampleFile['recipient_pwd'], PASSWORD_DEFAULT), 
        $sampleFile['recipient_pwd'],
        password_hash($sampleFile['sender_pwd'], PASSWORD_DEFAULT), 
        $sampleFile['sender_pwd'],
        json_encode($settings),
        $demoUserId
    ]);
    
    echo "   ✅ Created: {$sampleFile['title']}\n";
    echo "      Code: {$code}\n";
    echo "      Recipient password: {$sampleFile['recipient_pwd']}\n";
    echo "      Sender password: {$sampleFile['sender_pwd']}\n\n";
    
    $createdFiles++;
}

echo "📊 Summary:\n";
echo "   - Demo users: 2\n";
echo "   - Sample files: {$createdFiles}\n";
echo "   - File lifetime: " . DEMO_FILE_LIFETIME_MINUTES . " minutes\n\n";

echo "✨ Demo environment is ready!\n";
echo "   Visit: http://localhost:8080/upload.php\n";
echo "   Login: demo@sendrify.local / demo123\n\n";

/**
 * Create a simple sample PDF
 */
function create_sample_pdf($title) {
    // This is a minimal valid PDF structure
    return <<<PDF
%PDF-1.4
1 0 obj
<<
/Type /Catalog
/Pages 2 0 R
>>
endobj

2 0 obj
<<
/Type /Pages
/Kids [3 0 R]
/Count 1
>>
endobj

3 0 obj
<<
/Type /Page
/Parent 2 0 R
/Resources <<
/Font <<
/F1 <<
/Type /Font
/Subtype /Type1
/BaseFont /Helvetica
>>
>>
>>
/MediaBox [0 0 612 792]
/Contents 4 0 R
>>
endobj

4 0 obj
<<
/Length 100
>>
stream
BT
/F1 24 Tf
50 700 Td
({$title}) Tj
/F1 12 Tf
50 650 Td
(This is a sample PDF file for the Sendrify demo.) Tj
ET
endstream
endobj

xref
0 5
0000000000 65535 f 
0000000009 00000 n 
0000000058 00000 n 
0000000115 00000 n 
0000000317 00000 n 
trailer
<<
/Size 5
/Root 1 0 R
>>
startxref
467
%%EOF
PDF;
}
