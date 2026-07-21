<?php

namespace Barua\Accounts;

use Webklex\PHPIMAP\ClientManager;
use PHPMailer\PHPMailer\SMTP;

/**
 * Best-effort IMAP/SMTP settings detection for account setup — no third party involved.
 * Given an email + password, it probes the usual host patterns of the address's domain
 * (mail./imap./smtp. + the bare domain) against the common encryption/port pairs and keeps
 * the FIRST combination that actually authenticates. The successful login IS the proof the
 * settings are right, so a detected account is verified before it's ever saved.
 *
 * Deliberately no external autoconfig lookup (Thunderbird ISPDB etc.) — that would leak the
 * domain to a third party. This covers custom-domain mailboxes, which is the common case here.
 */
class AutoDetect
{
    private const TIMEOUT = 5; // seconds per connection attempt (success hits the first combo fast)

    /** @return array{host:string,port:int,encryption:string,username:string}|null */
    public static function imap(string $email, string $password): ?array
    {
        $domain = self::domain($email);
        if ($domain === '') {
            return null;
        }
        $combos = [['ssl', 993], ['tls', 143]]; // starttls on 143
        foreach (self::hosts($domain, ['mail', 'imap']) as $host) {
            foreach ($combos as [$enc, $port]) {
                if (self::tryImap($host, $port, $enc, $email, $password)) {
                    return ['host' => $host, 'port' => $port, 'encryption' => $enc, 'username' => $email];
                }
            }
        }
        return null;
    }

    /** @return array{host:string,port:int,encryption:string,username:string}|null */
    public static function smtp(string $email, string $password): ?array
    {
        $domain = self::domain($email);
        if ($domain === '') {
            return null;
        }
        $combos = [['ssl', 465], ['tls', 587]]; // ssl = implicit TLS, tls = STARTTLS
        foreach (self::hosts($domain, ['smtp', 'mail']) as $host) {
            foreach ($combos as [$enc, $port]) {
                if (self::trySmtp($host, $port, $enc, $email, $password)) {
                    return ['host' => $host, 'port' => $port, 'encryption' => $enc, 'username' => $email];
                }
            }
        }
        return null;
    }

    private static function domain(string $email): string
    {
        $at = strrpos($email, '@');
        return $at === false ? '' : strtolower(trim(substr($email, $at + 1)));
    }

    /** Candidate hosts (prefix.domain + bare domain), de-duplicated, non-resolving ones dropped. */
    private static function hosts(string $domain, array $prefixes): array
    {
        $candidates = [];
        foreach ($prefixes as $p) {
            $candidates[] = $p . '.' . $domain;
        }
        $candidates[] = $domain;
        $candidates = array_values(array_unique($candidates));
        // Skip hosts that don't resolve so a bad guess fails instantly instead of timing out.
        return array_values(array_filter($candidates, fn($h) => @gethostbyname($h) !== $h));
    }

    /**
     * Run a probe with PHP warnings swallowed. Failed socket/TLS attempts deep in webklex /
     * PHPMailer raise E_WARNINGs (not exceptions) that would otherwise leak into the JSON
     * response and corrupt it. The handler returning true suppresses the default output.
     */
    private static function quietly(callable $fn): bool
    {
        set_error_handler(static fn() => true);
        try {
            return (bool) $fn();
        } finally {
            restore_error_handler();
        }
    }

    private static function tryImap(string $host, int $port, string $enc, string $user, string $pass): bool
    {
        return self::quietly(function () use ($host, $port, $enc, $user, $pass) {
        try {
            $cm = new ClientManager(['options' => ['decoder' => ['message' => 'mimeheader', 'attachment' => 'mimeheader']]]);
            $client = $cm->make([
                'host' => $host, 'port' => $port,
                'encryption' => $enc === 'none' ? false : $enc,
                'validate_cert' => true,
                'username' => $user, 'password' => $pass,
                'protocol' => 'imap', 'timeout' => self::TIMEOUT,
            ]);
            $client->connect();
            $ok = $client->isConnected();
            $client->disconnect();
            return $ok;
        } catch (\Throwable $e) {
            return false;
        }
        });
    }

    private static function trySmtp(string $host, int $port, string $enc, string $user, string $pass): bool
    {
        return self::quietly(function () use ($host, $port, $enc, $user, $pass) {
        $smtp = new SMTP();
        $smtp->Timeout = self::TIMEOUT;
        $smtp->Timelimit = self::TIMEOUT;
        $connHost = $enc === 'ssl' ? 'ssl://' . $host : $host;
        $ehlo = gethostname() ?: 'localhost';
        try {
            if (!$smtp->connect($connHost, $port, self::TIMEOUT)) {
                return false;
            }
            if (!$smtp->hello($ehlo)) {
                $smtp->quit();
                return false;
            }
            if ($enc === 'tls') {
                if (!$smtp->startTLS()) {
                    $smtp->quit();
                    return false;
                }
                $smtp->hello($ehlo); // re-EHLO after STARTTLS
            }
            $ok = $smtp->authenticate($user, $pass);
            $smtp->quit();
            return $ok;
        } catch (\Throwable $e) {
            try { $smtp->quit(); } catch (\Throwable $e2) {
            }
            return false;
        }
        });
    }
}
