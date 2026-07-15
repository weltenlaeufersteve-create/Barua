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
            return false;
        }

        $expectedUser = Config::get('auth.username');
        $expectedHash = Config::get('auth.password_hash');

        if (hash_equals($expectedUser, $username) && password_verify($password, $expectedHash)) {
            $_SESSION['user'] = $username;
            session_regenerate_id(true);
            return true;
        }

        self::registerFailedAttempt($ip);
        return false;
    }

    public static function logout(): void
    {
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
}
