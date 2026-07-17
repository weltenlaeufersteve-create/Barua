<?php

namespace Barua\Mail;

use Barua\Database;

/**
 * Reusable signatures managed under Settings → Signatures and assigned to accounts
 * via accounts.signature_id. A signature is either 'plain' (dropped verbatim into the
 * plain-text body) or 'html' (sent as an HTML part).
 */
class SignatureRepository
{
    /** All signatures, newest first. */
    public static function all(): array
    {
        return Database::connection()
            ->query('SELECT * FROM signatures ORDER BY name ASC, id ASC')
            ->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM signatures WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** @return 'plain'|'html' — normalise any input to a known format. */
    public static function normaliseFormat(?string $format): string
    {
        return $format === 'html' ? 'html' : 'plain';
    }

    public static function create(string $name, string $format, string $body): int
    {
        $db = Database::connection();
        $db->prepare('INSERT INTO signatures (name, format, body) VALUES (?, ?, ?)')
           ->execute([$name, self::normaliseFormat($format), $body]);
        return (int) $db->lastInsertId();
    }

    public static function update(int $id, string $name, string $format, string $body): void
    {
        Database::connection()
            ->prepare('UPDATE signatures SET name = ?, format = ?, body = ?, updated_at = ? WHERE id = ?')
            ->execute([$name, self::normaliseFormat($format), $body, gmdate('Y-m-d H:i:s'), $id]);
    }

    /** Delete a signature and detach it from any account that used it. */
    public static function delete(int $id): void
    {
        $db = Database::connection();
        $db->prepare('UPDATE accounts SET signature_id = NULL WHERE signature_id = ?')->execute([$id]);
        $db->prepare('DELETE FROM signatures WHERE id = ?')->execute([$id]);
    }
}
