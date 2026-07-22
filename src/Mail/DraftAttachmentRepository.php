<?php

namespace Barua\Mail;

use Barua\Database;

/**
 * Outbound attachments a draft carries while composing. Files live in
 * storage/outbox/{draft_id}/ (outside the web root); metadata in `draft_attachments`.
 * The DB rows cascade-delete with their draft; the FILES are removed explicitly here
 * (on individual remove, and via deleteFilesForDraft() when a draft is sent/discarded).
 */
class DraftAttachmentRepository
{
    private static function dir(int $draftId): string
    {
        return __DIR__ . '/../../storage/outbox/' . $draftId;
    }

    /** Store one uploaded file for a draft. Returns the new row (id/filename/size/mime) or null. */
    public static function add(int $draftId, string $tmpPath, string $origName, string $mime, int $size): ?array
    {
        $dir = self::dir($draftId);
        if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
            return null;
        }

        $db = Database::connection();
        $count = $db->prepare('SELECT COUNT(*) FROM draft_attachments WHERE draft_id = ?');
        $count->execute([$draftId]);
        $n = (int) $count->fetchColumn() + 1;

        $diskName = $n . '-' . self::safeName($origName);
        $full = $dir . '/' . $diskName;
        // move_uploaded_file for real HTTP uploads; copy is the fallback (tests/CLI).
        if (!@move_uploaded_file($tmpPath, $full) && !@copy($tmpPath, $full)) {
            return null;
        }

        $stmt = $db->prepare(
            'INSERT INTO draft_attachments (draft_id, filename, mime_type, size_bytes, storage_path)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $draftId,
            mb_substr($origName, 0, 500),
            mb_substr($mime !== '' ? $mime : 'application/octet-stream', 0, 255),
            $size,
            $draftId . '/' . $diskName, // relative to storage/outbox/
        ]);

        return [
            'id'       => (int) $db->lastInsertId(),
            'filename' => $origName,
            'size'     => $size,
            'mime'     => $mime,
        ];
    }

    /** Attachments of a draft, for the compose chips / draft reopen. */
    public static function forDraft(int $draftId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, filename, size_bytes FROM draft_attachments WHERE draft_id = ? ORDER BY id'
        );
        $stmt->execute([$draftId]);
        return array_map(fn($r) => [
            'id'       => (int) $r['id'],
            'filename' => $r['filename'],
            'size'     => (int) $r['size_bytes'],
        ], $stmt->fetchAll());
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM draft_attachments WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** Remove one attachment (file + row). */
    public static function delete(int $id): void
    {
        $row = self::find($id);
        if (!$row) {
            return;
        }
        @unlink(__DIR__ . '/../../storage/outbox/' . $row['storage_path']);
        Database::connection()->prepare('DELETE FROM draft_attachments WHERE id = ?')->execute([$id]);
    }

    /** What MailSender needs: absolute path + name + mime for each of a draft's files. */
    public static function forSend(int $draftId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT filename, mime_type, storage_path FROM draft_attachments WHERE draft_id = ? ORDER BY id'
        );
        $stmt->execute([$draftId]);
        $base = __DIR__ . '/../../storage/outbox/';
        $out = [];
        foreach ($stmt->fetchAll() as $r) {
            $path = $base . $r['storage_path'];
            if (is_file($path)) {
                $out[] = ['path' => $path, 'name' => $r['filename'], 'mime' => $r['mime_type']];
            }
        }
        return $out;
    }

    /** Remove every file (and the dir) for a draft — called when the draft is sent or deleted. */
    public static function deleteFilesForDraft(int $draftId): void
    {
        $dir = self::dir($draftId);
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob($dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($dir);
    }

    /** Filesystem-safe on-disk name; the readable original stays in the DB. */
    private static function safeName(string $name): string
    {
        $name = basename($name);
        $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?? '';
        $name = trim($name, '._');
        return $name !== '' ? mb_substr($name, 0, 150) : 'file';
    }
}
