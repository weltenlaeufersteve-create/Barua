<?php
// IMAP sync entry point — run from cron or manually:
//   php cron/sync.php [limit]
require __DIR__ . '/../vendor/autoload.php';

use Barua\Mail\SyncService;
use Barua\Config;

date_default_timezone_set(Config::get('timezone', 'Europe/Berlin') ?: 'Europe/Berlin');

$limit = isset($argv[1]) ? max(1, (int) $argv[1]) : 50;

$start = microtime(true);
$results = SyncService::syncAll($limit);

foreach ($results as $r) {
    if ($r['ok']) {
        printf("[ok]   %-28s fetched=%d stored=%d\n", $r['account'], $r['fetched'], $r['stored']);
    } else {
        printf("[FAIL] %-28s %s\n", $r['account'], $r['error']);
    }
}
printf("Done in %.1fs\n", microtime(true) - $start);
