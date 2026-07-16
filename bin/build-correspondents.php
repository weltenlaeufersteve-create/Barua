<?php
// Harvest correspondents from the FULL Sent folders (headers only — cheap).
// Run once as backfill: php bin/build-correspondents.php [limit-per-folder]
require __DIR__ . '/../vendor/autoload.php';

use Barua\Accounts\AccountRepository;
use Barua\Config;
use Barua\Mail\CorrespondentRepository;
use Barua\Mail\FolderResolver;
use Barua\Mail\SyncService;

date_default_timezone_set(Config::get('timezone', 'Europe/Berlin') ?: 'Europe/Berlin');

$limit = isset($argv[1]) ? max(1, (int) $argv[1]) : 2000;
$before = CorrespondentRepository::count();

foreach (AccountRepository::all() as $account) {
    if ((int) $account['is_active'] !== 1) {
        continue;
    }
    try {
        $client = SyncService::makeClient($account);
        $client->connect();
        $sent = FolderResolver::find($client, 'sent');
        if (!$sent) {
            printf("[--]  %-28s no sent folder\n", $account['label']);
            $client->disconnect();
            continue;
        }
        $messages = $sent->messages()->all()->setFetchBody(false)->setFetchOrder('desc')->limit($limit)->get();
        $seen = 0;
        foreach ($messages as $message) {
            foreach (['getTo', 'getCc'] as $getter) {
                try {
                    $list = $message->$getter();
                    $list = is_array($list) ? $list : ($list !== null ? $list->all() : []);
                    foreach ($list as $addr) {
                        $date = null;
                        try {
                            $d = $message->getDate()->first();
                            if ($d) {
                                $date = $d->copy()->setTimezone('UTC')->format('Y-m-d H:i:s');
                            }
                        } catch (\Throwable $e) {
                        }
                        CorrespondentRepository::upsert(
                            (string) ($addr->mail ?? ''),
                            trim((string) ($addr->personal ?? ''), "\"'"),
                            $date
                        );
                    }
                } catch (\Throwable $e) {
                }
            }
            $seen++;
        }
        $client->disconnect();
        printf("[ok]  %-28s scanned=%d\n", $account['label'], $seen);
    } catch (\Throwable $e) {
        printf("[FAIL] %-27s %s\n", $account['label'], $e->getMessage());
    }
}

printf("correspondents: %d -> %d\n", $before, CorrespondentRepository::count());
