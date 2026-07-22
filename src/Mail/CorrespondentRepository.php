<?php

namespace Barua\Mail;

use Barua\Database;

/**
 * People the user has written to — harvested from Sent-folder sync and the send flow.
 * Powers the "People" smart group (and, later, composer autocomplete).
 */
class CorrespondentRepository
{
    public static function upsert(string $email, string $name = '', ?string $lastUsed = null): void
    {
        $email = strtolower(trim($email));
        if ($email === '' || !str_contains($email, '@')) {
            return;
        }
        $name = trim($name);
        if ($name !== '' && str_contains($name, '=?')) {
            $decoded = @mb_decode_mimeheader($name); // RFC 2047 encoded-words (any charset)
            if (is_string($decoded) && $decoded !== '') {
                $name = trim($decoded, "\"' ");
            }
        }
        if (strcasecmp($name, $email) === 0) {
            $name = '';
        }
        Database::connection()->prepare(
            "INSERT INTO correspondents (email, name, last_used) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE
                name = IF(VALUES(name) <> '', VALUES(name), name),
                last_used = GREATEST(COALESCE(last_used, '1970-01-01'), COALESCE(VALUES(last_used), '1970-01-01'))"
        )->execute([$email, trim($name), $lastUsed]);
    }

    public static function count(): int
    {
        return (int) Database::connection()->query('SELECT COUNT(*) FROM correspondents')->fetchColumn();
    }

    /**
     * Compose autocomplete: people whose name or email matches, most-recently-used first.
     * @return array<int, array{name:string, email:string}>
     */
    public static function search(string $q, int $limit = 8): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
        $stmt = Database::connection()->prepare(
            "SELECT name, email FROM correspondents
             WHERE email LIKE ? OR name LIKE ?
             ORDER BY (email LIKE ? OR name LIKE ?) DESC, last_used DESC
             LIMIT " . (int) $limit
        );
        // A prefix match (starts-with) ranks above a mid-string match.
        $prefix = str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
        $stmt->execute([$like, $like, $prefix, $prefix]);
        return array_map(fn($r) => ['name' => (string) $r['name'], 'email' => $r['email']], $stmt->fetchAll());
    }
}
