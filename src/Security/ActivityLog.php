<?php

namespace Barua\Security;

use Barua\Config;

/**
 * Audit log of security- and data-relevant actions by the signed-in user — the sibling of
 * the sign-in log in Auth. Records irreversible/destructive or config-changing events
 * (empty Trash/Spam, account add/remove/edit, sign-out), NOT routine per-message triage.
 * Lines land in storage/logs/activity.log (outside the web root, gitignored), one parseable
 * line each. Best-effort: a logging failure must never break the action it describes.
 *   tail -f ~/barua_app/storage/logs/activity.log
 */
class ActivityLog
{
    private const FILE = __DIR__ . '/../../storage/logs/activity.log';

    /**
     * @param string $action short token, e.g. 'empty', 'logout', 'account_add'
     * @param string $detail human context, e.g. 'Trash · all accounts · 60 messages'
     */
    public static function log(string $action, string $detail = ''): void
    {
        $dir = dirname(self::FILE);
        if (!is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }

        $tz = Config::get('timezone', 'Europe/Berlin') ?: 'Europe/Berlin';
        $ts = (new \DateTime('now', new \DateTimeZone($tz)))->format('Y-m-d H:i:s P');

        // Strip newlines/quotes from anything that could contain user/attacker text, so a
        // single log line can't be forged or split.
        $clean = fn(string $s): string => str_replace(["\r", "\n", '"'], [' ', ' ', "'"], $s);
        $user = $clean(mb_substr((string) ($_SESSION['user'] ?? ''), 0, 100));
        $ip   = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $xff  = trim($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');

        $line = sprintf(
            "%s  %-15s  action=%-14s  user=\"%s\"  detail=\"%s\"%s\n",
            $ts,
            $ip,
            $action,
            $user,
            $clean(mb_substr($detail, 0, 300)),
            $xff !== '' ? '  xff="' . $clean($xff) . '"' : ''
        );

        @file_put_contents(self::FILE, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Most recent activity entries, newest first, for Settings → Security.
     * @return array<int, array{time:string, ip:string, action:string, user:string, detail:string, xff:string}>
     */
    public static function recent(int $limit = 100): array
    {
        if (!is_file(self::FILE)) {
            return [];
        }
        $lines = @file(self::FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $lines = array_reverse(array_slice($lines, -$limit)); // last N, newest first

        $out = [];
        foreach ($lines as $line) {
            if (preg_match('/^(.+?)\s{2,}(\S+)\s+action=(\S+)\s+user="(.*?)"\s+detail="(.*?)"(?:\s+xff="(.*?)")?\s*$/', $line, $m)) {
                $out[] = [
                    'time'   => trim($m[1]),
                    'ip'     => $m[2],
                    'action' => $m[3],
                    'user'   => $m[4],
                    'detail' => $m[5],
                    'xff'    => $m[6] ?? '',
                ];
            }
        }
        return $out;
    }
}
