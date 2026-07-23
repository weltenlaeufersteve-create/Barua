<?php
// Idempotent migration. Adds accounts.avatar_state — tracks whether a Gravatar image
// was found for the account's email (cached to storage/avatars/{id}.jpg) so the
// dashboard/settings avatars never re-fetch on every page load.
// Run: php bin/migrate-avatar-state.php
require __DIR__ . '/../vendor/autoload.php';

use Barua\Database;

$db = Database::connection();

$hasColumn = false;
foreach ($db->query('SHOW COLUMNS FROM accounts')->fetchAll() as $col) {
    if (($col['Field'] ?? '') === 'avatar_state') {
        $hasColumn = true;
        break;
    }
}

if ($hasColumn) {
    echo "accounts.avatar_state already present\n";
} else {
    $db->exec("ALTER TABLE accounts ADD COLUMN avatar_state ENUM('unknown','has','none') NOT NULL DEFAULT 'unknown' AFTER signature_id");
    echo "added accounts.avatar_state\n";
}

$unknown = (int) $db->query("SELECT COUNT(*) FROM accounts WHERE avatar_state = 'unknown'")->fetchColumn();
echo "done ({$unknown} account(s) still need a Gravatar lookup — run bin/backfill-avatars.php)\n";
