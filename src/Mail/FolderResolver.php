<?php

namespace Barua\Mail;

use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\Folder;

/**
 * Maps an account's real IMAP folders to normalized roles (inbox/sent/archive/…),
 * so cross-account views work regardless of each server's folder naming.
 */
class FolderResolver
{
    /** Common folder basenames per role, matched case-insensitively against the leaf name. */
    private const NAMES = [
        'sent'    => ['sent', 'sent items', 'sent messages', 'gesendet', 'gesendete elemente', 'gesendete objekte'],
        'archive' => ['archive', 'archiv', 'all mail'],
        'drafts'  => ['drafts', 'draft', 'entwürfe', 'entwuerfe'],
        'trash'   => ['trash', 'deleted', 'deleted items', 'papierkorb', 'gelöscht'],
        'spam'    => ['spam', 'junk', 'bulk mail'],
    ];

    /** @return array<string,Folder|null> role => folder (or null if not found) */
    public static function map(Client $client): array
    {
        $folders = $client->getFolders(false);
        $result = ['inbox' => null, 'sent' => null, 'archive' => null, 'drafts' => null, 'trash' => null, 'spam' => null];

        foreach ($folders as $folder) {
            $leaf = self::leaf($folder->path);
            $lower = mb_strtolower($leaf);

            if (mb_strtolower($folder->path) === 'inbox') {
                $result['inbox'] = $folder;
                continue;
            }
            foreach (self::NAMES as $role => $candidates) {
                if ($result[$role] === null && in_array($lower, $candidates, true)) {
                    $result[$role] = $folder;
                }
            }
        }
        return $result;
    }

    /** Find one role's folder, or null. */
    public static function find(Client $client, string $role): ?Folder
    {
        return self::map($client)[$role] ?? null;
    }

    private static function leaf(string $path): string
    {
        // Folders look like "INBOX.Sent" (dot) or "INBOX/Sent" (slash) depending on the server.
        $parts = preg_split('/[.\/]/', $path) ?: [$path];
        return end($parts) ?: $path;
    }
}
