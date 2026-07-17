<?php
// Idempotent migration for the signatures feature. Safe to run more than once.
//   1. Create the `signatures` table if missing.
//   2. Add `accounts.signature_id` if missing.
//   3. Migrate each account's existing free-text `signature` into a signature row
//      (format 'plain', named after the account) and point signature_id at it.
// Run: php bin/migrate-signatures.php
require __DIR__ . '/../vendor/autoload.php';

use Barua\Database;

$db = Database::connection();

// 1. signatures table
$db->exec(
    "CREATE TABLE IF NOT EXISTS signatures (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(120) NOT NULL,
        format ENUM('plain','html') NOT NULL DEFAULT 'plain',
        body MEDIUMTEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
echo "signatures table ready\n";

// 2. accounts.signature_id column (portable check — no information_schema assumptions)
$hasColumn = false;
foreach ($db->query('SHOW COLUMNS FROM accounts')->fetchAll() as $col) {
    if (($col['Field'] ?? '') === 'signature_id') {
        $hasColumn = true;
        break;
    }
}
if (!$hasColumn) {
    $db->exec('ALTER TABLE accounts ADD COLUMN signature_id INT NULL AFTER signature');
    echo "added accounts.signature_id\n";
} else {
    echo "accounts.signature_id already present\n";
}

// 3. migrate existing per-account signature text
$accounts = $db->query('SELECT id, label, signature, signature_id FROM accounts')->fetchAll();
$insert = $db->prepare('INSERT INTO signatures (name, format, body) VALUES (?, ?, ?)');
$link = $db->prepare('UPDATE accounts SET signature_id = ? WHERE id = ?');
$migrated = 0;
foreach ($accounts as $acc) {
    if ($acc['signature_id'] !== null) {
        continue; // already assigned
    }
    $text = trim((string) ($acc['signature'] ?? ''));
    if ($text === '') {
        continue; // nothing to migrate
    }
    $insert->execute([$acc['label'] . ' signature', 'plain', $text]);
    $sigId = (int) $db->lastInsertId();
    $link->execute([$sigId, (int) $acc['id']]);
    printf("migrated %s -> signature #%d\n", $acc['label'], $sigId);
    $migrated++;
}
echo "done ({$migrated} account signature(s) migrated)\n";
