<?php
// Idempotent migration for the attachments feature. Safe to run more than once.
// Adds attachments.disposition (attachment vs inline) so the reader can hide decorative
// inline images (signature logos, newsletter social icons) and show only real attachments.
// Run: php bin/migrate-attachment-disposition.php
require __DIR__ . '/../vendor/autoload.php';

use Barua\Database;

$db = Database::connection();

$hasColumn = false;
foreach ($db->query('SHOW COLUMNS FROM attachments')->fetchAll() as $col) {
    if (($col['Field'] ?? '') === 'disposition') {
        $hasColumn = true;
        break;
    }
}

if ($hasColumn) {
    echo "attachments.disposition already present\n";
} else {
    $db->exec('ALTER TABLE attachments ADD COLUMN disposition VARCHAR(20) NULL AFTER content_id');
    echo "added attachments.disposition\n";
}
