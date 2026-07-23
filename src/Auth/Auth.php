<?php

namespace Barua\Auth;

use Barua\Config;
use Barua\Database;

class Auth
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_SECONDS = 300;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start([
                'cookie_httponly' => true,
                'cookie_samesite' => 'Lax',
            ]);
        }
    }

    public static function isLoggedIn(): bool
    {
        return !empty($_SESSION['user']);
    }

    public static function requireLogin(): void
    {
        if (!self::isLoggedIn()) {
            header('Location: /login');
            exit;
        }
    }

    public static function attempt(string $username, string $password): bool
    {
        $ip = self::clientIp();

        if (self::recentFailedAttempts($ip) >= self::MAX_ATTEMPTS) {
            self::logAttempt($username, 'blocked');
            return false;
        }

        $expectedUser = Config::get('auth.username');
        $expectedHash = Config::get('auth.password_hash');

        if (hash_equals($expectedUser, $username) && password_verify($password, $expectedHash)) {
            $_SESSION['user'] = $username;
            session_regenerate_id(true);
            self::logAttempt($username, 'success');
            return true;
        }

        self::registerFailedAttempt($ip);
        self::logAttempt($username, 'fail');
        return false;
    }

    public static function logout(): void
    {
        \Barua\Security\ActivityLog::log('logout'); // before the session is cleared (reads user)
        $_SESSION = [];
        session_destroy();
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrf(?string $token): bool
    {
        return is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    private static function recentFailedAttempts(string $ip): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempted_at > (NOW() - INTERVAL ' . self::WINDOW_SECONDS . ' SECOND)'
        );
        $stmt->execute([$ip]);
        return (int) $stmt->fetchColumn();
    }

    private static function registerFailedAttempt(string $ip): void
    {
        $stmt = Database::connection()->prepare('INSERT INTO login_attempts (ip_address) VALUES (?)');
        $stmt->execute([$ip]);
    }

    private static function clientIp(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Append one line to the auth log (every login attempt: success | fail | blocked).
     * File lives in storage/logs/ (outside the web root, gitignored). Best-effort — a
     * logging failure must never block a login. Review on the server with:
     *   tail -f ~/barua_app/storage/logs/auth.log
     */
    private static function logAttempt(string $username, string $outcome): void
    {
        $dir = __DIR__ . '/../../storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }

        $tz = Config::get('timezone', 'Europe/Berlin') ?: 'Europe/Berlin';
        $ts = (new \DateTime('now', new \DateTimeZone($tz)))->format('Y-m-d H:i:s P');

        // Keep everything on a single, parseable line — strip newlines/quotes from
        // attacker-controlled fields (username, user-agent) so they can't forge entries.
        $clean = fn(string $s): string => str_replace(["\r", "\n", '"'], [' ', ' ', "'"], $s);
        $ua  = $clean(mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 300));
        $usr = $clean(mb_substr($username, 0, 100));
        $xff = trim($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');

        $line = sprintf(
            "%s  %-15s  outcome=%-7s  user=\"%s\"  ua=\"%s\"%s\n",
            $ts,
            self::clientIp(),
            $outcome,
            $usr,
            $ua,
            $xff !== '' ? '  xff="' . $clean($xff) . '"' : ''
        );

        @file_put_contents($dir . '/auth.log', $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Parse the most recent login attempts for the Settings → Security view. Newest first.
     * @return array<int, array{time:string, ip:string, outcome:string, user:string, ua:string, xff:string}>
     */
    public static function recentLoginAttempts(int $limit = 100): array
    {
        $file = __DIR__ . '/../../storage/logs/auth.log';
        if (!is_file($file)) {
            return [];
        }
        $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $lines = array_slice($lines, -$limit);          // last N
        $lines = array_reverse($lines);                 // newest first

        $out = [];
        foreach ($lines as $line) {
            if (preg_match('/^(.+?)\s{2,}(\S+)\s+outcome=(\w+)\s+user="(.*?)"\s+ua="(.*?)"(?:\s+xff="(.*?)")?\s*$/', $line, $m)) {
                $out[] = [
                    'time'    => trim($m[1]),
                    'ip'      => $m[2],
                    'outcome' => $m[3],
                    'user'    => $m[4],
                    'ua'      => $m[5],
                    'xff'     => $m[6] ?? '',
                ];
            }
        }
        return $out;
    }
}
