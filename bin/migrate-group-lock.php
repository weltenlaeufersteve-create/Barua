<?php
// Idempotent migration for manual group assignment. Safe to run more than once.
// Adds messages.group_locked — set to 1 when the user moves a mail into a group by
// hand, so the sync's classifier leaves it alone from then on.
// Run: php bin/migrate-group-lock.php
require __DIR__ . '/../vendor/autoload.php';

use Barua\Database;

$db = Database::connection();

$hasColumn = false;
foreach ($db->query('SHOW COLUMNS FROM messages')->fetchAll() as $col) {
    if (($col['Field'] ?? '') === 'group_locked') {
        $hasColumn = true;
        break;
    }
}

if ($hasColumn) {
    echo "messages.group_locked already present\n";
} else {
    $db->exec('ALTER TABLE messages ADD COLUMN group_locked TINYINT(1) NOT NULL DEFAULT 0 AFTER group_type');
    echo "added messages.group_locked\n";
}

$locked = (int) $db->query('SELECT COUNT(*) FROM messages WHERE group_locked = 1')->fetchColumn();
echo "done ({$locked} message(s) currently pinned to a hand-picked group)\n";
