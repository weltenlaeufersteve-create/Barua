<?php
// One-off (and re-runnable) Gravatar lookup for accounts that predate the avatar feature
// or whose lookup is still 'unknown' for any other reason. Safe to run repeatedly —
// accounts already resolved to 'has'/'none' are skipped.
// Run: php bin/backfill-avatars.php
require __DIR__ . '/../vendor/autoload.php';

use Barua\Accounts\AccountRepository;
use Barua\Accounts\GravatarService;

$accounts = AccountRepository::all();
$done = 0;
foreach ($accounts as $account) {
    if ($account['avatar_state'] !== 'unknown') {
        continue;
    }
    GravatarService::ensure((int) $account['id'], $account['email']);
    $done++;
    echo "checked #{$account['id']} ({$account['email']})\n";
}

echo "done ({$done} account(s) checked, " . count($accounts) . " total)\n";
