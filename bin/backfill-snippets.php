<?php
// Recompute messages.body_snippet for existing rows so old newsletters stop leaking
// raw <style>/<script> CSS into the list preview. Idempotent. Run: php bin/backfill-snippets.php
require __DIR__ . '/../vendor/autoload.php';

use Barua\Database;
use Barua\Mail\HtmlMailRenderer;

$db = Database::connection();
$rows = $db->query('SELECT id, body_plain, body_html FROM messages')->fetchAll();
$update = $db->prepare('UPDATE messages SET body_snippet = ? WHERE id = ?');

$fixed = 0;
foreach ($rows as $r) {
    $plain = trim((string) ($r['body_plain'] ?? ''));
    $snippet = $plain !== ''
        ? trim(preg_replace('/\s+/', ' ', $plain))
        : HtmlMailRenderer::toText((string) ($r['body_html'] ?? ''));
    $snippet = mb_substr($snippet, 0, 300);
    $update->execute([$snippet, (int) $r['id']]);
    $fixed++;
}
echo "recomputed {$fixed} snippet(s)\n";
