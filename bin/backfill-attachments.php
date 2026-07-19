<?php
// Fetch and store attachment content for already-cached messages that predate the
// attachments feature: has_attachments=1 in the cache, but no `attachments` rows yet.
// Idempotent (SyncService::storeAttachments skips messages that already have rows).
// Run: php bin/backfill-attachments.php [limit-per-account]
require __DIR__ . '/../vendor/autoload.php';

use Barua\Accounts\AccountRepository;
use Barua\Database;
use Barua\Mail\SyncService;

$limit = isset($argv[1]) ? (int) $argv[1] : 50;
$db = Database::connection();

foreach (AccountRepository::all() as $account) {
    $stmt = $db->prepare(
        'SELECT id, folder, imap_uid FROM messages
         WHERE account_id = ? AND has_attachments = 1
           AND id NOT IN (SELECT DISTINCT message_id FROM attachments)
         ORDER BY date_sent DESC LIMIT ' . (int) $limit
    );
    $stmt->execute([(int) $account['id']]);
    $rows = $stmt->fetchAll();
    if (empty($rows)) {
        printf("[skip] %-25s nothing to backfill\n", $account['label']);
        continue;
    }

    try {
        $client = SyncService::makeClient($account);
        $client->connect();
    } catch (\Throwable $e) {
        printf("[fail] %-25s connect: %s\n", $account['label'], $e->getMessage());
        continue;
    }

    $done = 0;
    foreach ($rows as $row) {
        try {
            $folder = $client->getFolderByPath($row['folder']);
            $message = $folder->query()->getMessageByUid((int) $row['imap_uid']);
            if ($message) {
                SyncService::storeAttachments((int) $row['id'], $message);
                $done++;
            }
        } catch (\Throwable $e) {
            // one bad message shouldn't stop the batch
        }
    }
    $client->disconnect();
    printf("[ok]   %-25s backfilled %d/%d\n", $account['label'], $done, count($rows));
}
