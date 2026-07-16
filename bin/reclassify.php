<?php
// One-time backfill: classify already-cached inbox messages into smart groups
// (newsletter / notification / other) by re-reading their headers from IMAP.
// Usage: php bin/reclassify.php [limit-per-inbox, default 400]
require __DIR__ . '/../vendor/autoload.php';

use Barua\Accounts\AccountRepository;
use Barua\Config;
use Barua\Database;
use Barua\Mail\FolderResolver;
use Barua\Mail\SyncService;

date_default_timezone_set(Config::get('timezone', 'Europe/Berlin') ?: 'Europe/Berlin');

$limit = isset($argv[1]) ? max(1, (int) $argv[1]) : 400;
$db = Database::connection();

foreach (AccountRepository::all() as $account) {
    if ((int) $account['is_active'] !== 1) {
        continue;
    }
    try {
        $client = SyncService::makeClient($account);
        $client->connect();
        $inbox = FolderResolver::find($client, 'inbox') ?? $client->getFolder('INBOX');
        $messages = $inbox->messages()->all()->setFetchBody(false)->setFetchOrder('desc')->limit($limit)->get();

        $counts = ['newsletter' => 0, 'notification' => 0, 'other' => 0];
        $upd = $db->prepare('UPDATE messages SET group_type = ? WHERE account_id = ? AND folder = ? AND imap_uid = ?');
        foreach ($messages as $m) {
            $uid = (int) (string) $m->getUid();
            if ($uid === 0) {
                continue;
            }
            $from = '';
            try {
                $f = $m->getFrom();
                $addr = is_array($f) ? ($f[0] ?? null) : $f->first();
                $from = $addr ? (string) $addr->mail : '';
            } catch (\Throwable $e) {
            }
            $type = SyncService::classify($m, $from);
            $upd->execute([$type, (int) $account['id'], $inbox->path, $uid]);
            $counts[$type]++;
        }
        $client->disconnect();
        printf("[ok] %-28s newsletter=%d notification=%d other=%d\n",
            $account['label'], $counts['newsletter'], $counts['notification'], $counts['other']);
    } catch (\Throwable $e) {
        printf("[FAIL] %-26s %s\n", $account['label'], $e->getMessage());
    }
}
