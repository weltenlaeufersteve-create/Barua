<?php
// Recompute thread_id for every cached message so existing chains group correctly under the
// new root-based logic. Works purely from stored fields — walks the in_reply_to chain through
// the cache to its earliest known ancestor (or the first external id it points at) and uses
// that as the shared key. No IMAP round-trip needed. Idempotent. Run: php bin/rethread.php
require __DIR__ . '/../vendor/autoload.php';

use Barua\Database;

$db = Database::connection();

// Ensure grouping/lookups on thread_id are indexed (cheap; the reader stack queries it).
$hasIndex = false;
foreach ($db->query('SHOW INDEX FROM messages')->fetchAll() as $ix) {
    if (($ix['Key_name'] ?? '') === 'idx_thread') {
        $hasIndex = true;
        break;
    }
}
if (!$hasIndex) {
    $db->exec('ALTER TABLE messages ADD INDEX idx_thread (thread_id)');
    echo "added idx_thread\n";
}

$rows = $db->query('SELECT id, message_id, in_reply_to FROM messages')->fetchAll();

// message_id -> row, so we can hop from a reply to its parent.
$byMsgId = [];
foreach ($rows as $r) {
    if (($r['message_id'] ?? '') !== '') {
        $byMsgId[$r['message_id']] = $r;
    }
}

// The grouping key = earliest ancestor reachable in the cache. If the chain leaves the cache
// (parent not stored), key on that external id so siblings pointing at it still group.
$rootOf = function (array $row) use ($byMsgId): string {
    $seen = [];
    $cur = $row;
    while (true) {
        $parentId = trim((string) ($cur['in_reply_to'] ?? ''));
        if ($parentId === '') {
            return (string) $cur['message_id']; // cur starts the thread
        }
        if (isset($seen[$parentId])) {
            return (string) $cur['message_id']; // cycle guard
        }
        $seen[$parentId] = true;
        if (!isset($byMsgId[$parentId])) {
            return $parentId; // earliest known ancestor is outside the cache
        }
        $cur = $byMsgId[$parentId];
    }
};

$upd = $db->prepare('UPDATE messages SET thread_id = ? WHERE id = ?');
$changed = 0;
foreach ($rows as $r) {
    $root = $rootOf($r);
    if ($root === '') {
        continue;
    }
    $stmt = $db->prepare('SELECT thread_id FROM messages WHERE id = ?');
    $stmt->execute([(int) $r['id']]);
    if ($stmt->fetchColumn() !== $root) {
        $upd->execute([$root, (int) $r['id']]);
        $changed++;
    }
}

$threads = $db->query('SELECT COUNT(*) FROM (SELECT thread_id FROM messages GROUP BY thread_id HAVING COUNT(*) > 1) t')->fetchColumn();
printf("rethreaded %d row(s); %d multi-message thread(s) now\n", $changed, (int) $threads);
