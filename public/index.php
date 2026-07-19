<?php

require __DIR__ . '/../vendor/autoload.php';

use Barua\Auth\Auth;
use Barua\Accounts\AccountRepository;
use Barua\Config;

date_default_timezone_set(Config::get('timezone', 'Europe/Berlin') ?: 'Europe/Berlin');

Auth::start();

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

if ($path === '/login' && $method === 'GET') {
    $csrfToken = Auth::csrfToken();
    $error = null;
    require __DIR__ . '/../views/login.php';
    return;
}

if ($path === '/login' && $method === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        echo 'Invalid request.';
        return;
    }

    if (Auth::attempt($_POST['username'] ?? '', $_POST['password'] ?? '')) {
        header('Location: /');
        return;
    }

    $csrfToken = Auth::csrfToken();
    $error = 'Invalid username, password, or too many attempts — try again shortly.';
    require __DIR__ . '/../views/login.php';
    return;
}

if ($path === '/logout') {
    Auth::logout();
    header('Location: /login');
    return;
}

Auth::requireLogin();

if ($path === '/' || $path === '') {
    $username = $_SESSION['user'];
    $csrfToken = Auth::csrfToken();
    $activeAccountId = isset($_GET['account']) ? (int) $_GET['account'] : null;
    $view = in_array($_GET['view'] ?? '', ['clean', 'sent', 'pinned', 'archive', 'trash', 'spam', 'drafts', 'newsletters', 'notifications', 'people'], true) ? $_GET['view'] : 'inbox';
    require __DIR__ . '/../views/dashboard.php';
    return;
}

if ($path === '/drafts/save' && $method === 'POST') {
    header('Content-Type: application/json');
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Invalid request']);
        return;
    }
    $account = AccountRepository::find((int) ($_POST['account_id'] ?? 0));
    if (!$account) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Unknown account']);
        return;
    }
    $draftId = ($_POST['draft_id'] ?? '') !== '' ? (int) $_POST['draft_id'] : null;
    $id = \Barua\Mail\DraftRepository::save($draftId, (int) $account['id'], [
        'to'         => $_POST['to'] ?? '',
        'cc'         => $_POST['cc'] ?? '',
        'bcc'        => $_POST['bcc'] ?? '',
        'subject'    => $_POST['subject'] ?? '',
        'body_plain' => $_POST['body_plain'] ?? '',
    ]);
    echo json_encode(['ok' => true, 'id' => $id]);
    return;
}

if (preg_match('#^/drafts/(\d+)/delete$#', $path, $m) && $method === 'POST') {
    header('Content-Type: application/json');
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Invalid request']);
        return;
    }
    \Barua\Mail\DraftRepository::delete((int) $m[1]);
    echo json_encode(['ok' => true]);
    return;
}

if ($path === '/api/stream' && $method === 'GET') {
    header('Content-Type: application/json');
    require_once __DIR__ . '/../views/helpers.php';

    $view = in_array($_GET['view'] ?? '', ['inbox', 'clean', 'pinned', 'archive', 'trash', 'spam', 'newsletters', 'notifications', 'people'], true)
        ? $_GET['view'] : 'inbox';
    $accountId = ($_GET['account'] ?? '') !== '' ? (int) $_GET['account'] : null;
    $after = (int) ($_GET['after'] ?? 0);

    $R = \Barua\Mail\MessageRepository::class;
    $rows = match ($view) {
        'clean'         => $R::cleanInboxMessages(60, $accountId),
        'pinned'        => $R::pinnedMessages(60, $accountId),
        'archive'       => $R::roleMessages('archive', 60, $accountId),
        'trash'         => $R::roleMessages('trash', 60, $accountId),
        'spam'          => $R::roleMessages('spam', 60, $accountId),
        'newsletters'   => $R::groupMessages('newsletter', 60, $accountId),
        'notifications' => $R::groupMessages('notification', 60, $accountId),
        'people'        => $R::peopleMessages(60, $accountId),
        default         => $R::unifiedInbox(60, $accountId),
    };

    // Only rows newer than the client's cursor (genuinely new messages get higher ids).
    $freshRows = array_values(array_filter($rows, fn($row) => (int) $row['id'] > $after));
    $attByMsg = $R::attachmentsForMessages(array_column($freshRows, 'id'));
    $newRows = array_map(function ($row) use ($attByMsg) {
        $data = mailRowData($row);
        $data['attachments'] = $attByMsg[(int) $row['id']] ?? [];
        return [
            'id'   => (int) $row['id'],
            'html' => renderMailRow($row, false, false),
            'data' => $data,
        ];
    }, $freshRows);

    echo json_encode([
        'ok'     => true,
        'rows'   => $newRows,                                   // newest first
        'unread' => \Barua\Mail\MessageRepository::totalUnread(),
    ]);
    return;
}

if ($path === '/compose/send' && $method === 'POST') {
    header('Content-Type: application/json');
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Invalid request']);
        return;
    }
    $account = AccountRepository::find((int) ($_POST['account_id'] ?? 0));
    if (!$account) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Unknown account']);
        return;
    }
    $payload = [
        'to'          => $_POST['to'] ?? '',
        'cc'          => $_POST['cc'] ?? '',
        'bcc'         => $_POST['bcc'] ?? '',
        'subject'     => $_POST['subject'] ?? '',
        'body_plain'  => $_POST['body_plain'] ?? '',
        'body_html'   => $_POST['body_html'] ?? '',
        'in_reply_to' => $_POST['in_reply_to'] ?? '',
        'references'  => $_POST['references'] ?? '',
    ];
    $result = \Barua\Mail\MailSender::send($account, $payload);
    if ($result['ok']) {
        // Recipients become correspondents immediately (feeds the People group).
        foreach (['to', 'cc', 'bcc'] as $field) {
            foreach (\Barua\Mail\MailSender::parseAddresses($payload[$field]) as $addr) {
                \Barua\Mail\CorrespondentRepository::upsert($addr, '', gmdate('Y-m-d H:i:s'));
            }
        }
        // MailSender already appended the copy to the IMAP Sent folder; pull it into the cache.
        \Barua\Mail\SyncService::syncSentFolder($account);
        // A sent draft is done — clean it up.
        if (($_POST['draft_id'] ?? '') !== '') {
            \Barua\Mail\DraftRepository::delete((int) $_POST['draft_id']);
        }
    } else {
        http_response_code(502);
    }
    echo json_encode($result);
    return;
}

if (preg_match('#^/messages/(\d+)/html$#', $path, $m) && $method === 'GET') {
    session_write_close(); // read-only render; don't hold the session lock
    $msg = \Barua\Mail\MessageRepository::find((int) $m[1]);
    if (!$msg) {
        http_response_code(404);
        echo 'Not found.';
        return;
    }
    $remote = ($_GET['images'] ?? '') === '1';
    $dark = ($_GET['dark'] ?? '') === '1';
    $html = trim($msg['body_html'] ?? '');
    if ($html === '') {
        $html = nl2br(htmlspecialchars($msg['body_plain'] ?? ''));
    }
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Security-Policy: ' . \Barua\Mail\HtmlMailRenderer::csp($remote));
    header('X-Content-Type-Options: nosniff');
    echo \Barua\Mail\HtmlMailRenderer::document($html, $remote, $dark);
    return;
}

if (preg_match('#^/attachments/(\d+)$#', $path, $m) && $method === 'GET') {
    $stmt = \Barua\Database::connection()->prepare('SELECT * FROM attachments WHERE id = ?');
    $stmt->execute([(int) $m[1]]);
    $att = $stmt->fetch();
    if (!$att) {
        http_response_code(404);
        echo 'Not found.';
        return;
    }

    // storage_path is always our own generated "{message id}/{n}-{name}" pattern, never
    // attacker input, but resolve + confirm containment anyway before touching the disk.
    $root = realpath(__DIR__ . '/../storage/attachments');
    $full = $root !== false ? realpath($root . '/' . $att['storage_path']) : false;
    if ($full === false || $root === false || strpos($full, $root) !== 0 || !is_file($full)) {
        http_response_code(404);
        echo 'Not found.';
        return;
    }

    // Attachments are attacker-controlled content (a mail's sender chooses them). Default
    // is always a forced download, never an inline render — an HTML or SVG "attachment"
    // opened inline would execute in this origin and could read the session/CSRF token.
    // Preview ("Open in Browser") is the one deliberate exception, and only for a narrow,
    // server-enforced allowlist (images + PDF) — the client asking for it is not enough
    // on its own; isPreviewableMime() is the real gate below.
    $wantsPreview = ($_GET['preview'] ?? '') === '1';
    $canPreview = $wantsPreview && \Barua\Mail\MessageRepository::isPreviewableMime($att['mime_type']);

    // RFC 6266: an ASCII-safe filename= fallback plus a UTF-8 filename*= for accents/umlauts.
    $rawName = str_replace(["\r", "\n", '"'], '', $att['filename']);
    $asciiName = preg_replace('/[^\x20-\x7E]/', '_', $rawName);
    header('Content-Type: ' . ($att['mime_type'] ?: 'application/octet-stream'));
    header('Content-Length: ' . filesize($full));
    header('Content-Disposition: ' . ($canPreview ? 'inline' : 'attachment')
        . '; filename="' . $asciiName . '"; filename*=UTF-8\'\'' . rawurlencode($rawName));
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: default-src 'none'");
    readfile($full);
    return;
}

if (preg_match('#^/messages/(\d+)/(pin|archive|trash|spam|read|group)$#', $path, $m) && $method === 'POST') {
    header('Content-Type: application/json');
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Invalid request']);
        return;
    }
    // Release the session lock before the (slow) IMAP round-trip so a parallel
    // request from the same tab — e.g. the reader's /html fetch — isn't serialized
    // behind it. Nothing below writes to the session.
    session_write_close();
    $id = (int) $m[1];
    $result = match ($m[2]) {
        'pin'     => \Barua\Mail\MessageActions::setPin($id, ($_POST['pinned'] ?? '') === '1'),
        'archive' => \Barua\Mail\MessageActions::archive($id),
        'trash'   => \Barua\Mail\MessageActions::trash($id),
        'spam'    => \Barua\Mail\MessageActions::spam($id),
        'group'   => \Barua\Mail\MessageActions::setGroup($id, $_POST['group'] ?? ''),
        'read'    => \Barua\Mail\MessageActions::markRead($id),
    };
    if (!$result['ok']) {
        http_response_code(422);
    }
    echo json_encode($result);
    return;
}

if ($path === '/sync' && $method === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        echo 'Invalid request.';
        return;
    }
    \Barua\Mail\SyncService::syncAll(50);
    if (($_POST['ajax'] ?? '') === '1') {
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        return;
    }
    $return = isset($_POST['return_account']) ? '/?account=' . (int) $_POST['return_account'] : '/';
    header('Location: ' . $return);
    return;
}

if ($path === '/accounts' && $method === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        echo 'Invalid request.';
        return;
    }

    $required = ['label', 'email', 'imap_host', 'imap_port', 'imap_encryption', 'imap_username', 'imap_password',
                 'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_password'];
    $data = [];
    foreach ($required as $field) {
        $data[$field] = trim($_POST[$field] ?? '');
    }
    $data['signature_id'] = $_POST['signature_id'] ?? null;

    $missing = array_filter($required, fn($field) => $data[$field] === '');
    if (!empty($missing)) {
        // The in-modal form marks every field required client-side; a server-side
        // miss means a bypassed form — just bounce back to the app.
        header('Location: /?settings=accounts');
        return;
    }

    AccountRepository::create($data);
    header('Location: /?settings=accounts');
    return;
}

if (preg_match('#^/accounts/(\d+)$#', $path, $m) && $method === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        echo 'Invalid request.';
        return;
    }
    $fields = ['label', 'email', 'imap_host', 'imap_port', 'imap_encryption',
               'imap_username', 'imap_password', 'smtp_host', 'smtp_port', 'smtp_encryption',
               'smtp_username', 'smtp_password'];
    $data = [];
    foreach ($fields as $f) {
        $data[$f] = trim($_POST[$f] ?? '');
    }
    $data['signature_id'] = $_POST['signature_id'] ?? null;
    AccountRepository::update((int) $m[1], $data);
    header('Location: /?settings=accounts');
    return;
}

// ---- Signatures CRUD (Settings → Signatures) ----
if ($path === '/signatures' && $method === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        echo 'Invalid request.';
        return;
    }
    $name = trim($_POST['name'] ?? '');
    if ($name !== '') {
        \Barua\Mail\SignatureRepository::create($name, $_POST['format'] ?? 'plain', $_POST['body'] ?? '');
    }
    header('Location: /?settings=signatures');
    return;
}

if (preg_match('#^/signatures/(\d+)$#', $path, $m) && $method === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        echo 'Invalid request.';
        return;
    }
    $name = trim($_POST['name'] ?? '');
    if ($name !== '') {
        \Barua\Mail\SignatureRepository::update((int) $m[1], $name, $_POST['format'] ?? 'plain', $_POST['body'] ?? '');
    }
    header('Location: /?settings=signatures');
    return;
}

if (preg_match('#^/signatures/(\d+)/delete$#', $path, $m) && $method === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        echo 'Invalid request.';
        return;
    }
    \Barua\Mail\SignatureRepository::delete((int) $m[1]);
    header('Location: /?settings=signatures');
    return;
}

if ($path === '/accounts/reorder' && $method === 'POST') {
    header('Content-Type: application/json');
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Invalid request']);
        return;
    }
    $ids = array_filter(array_map('intval', explode(',', $_POST['order'] ?? '')));
    if (empty($ids)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Empty order']);
        return;
    }
    AccountRepository::reorder($ids);
    echo json_encode(['ok' => true]);
    return;
}

if (preg_match('#^/accounts/(\d+)/colour$#', $path, $m) && $method === 'POST') {
    header('Content-Type: application/json');
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Invalid request']);
        return;
    }
    $colour = $_POST['colour'] ?? '';
    if (!\Barua\Accounts\ColorPalette::isValid($colour)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Invalid colour']);
        return;
    }
    AccountRepository::updateColour((int) $m[1], $colour);
    echo json_encode(['ok' => true, 'id' => (int) $m[1], 'colour' => $colour]);
    return;
}

if (preg_match('#^/accounts/(\d+)/delete$#', $path, $m) && $method === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        echo 'Invalid request.';
        return;
    }
    AccountRepository::delete((int) $m[1]);
    header('Location: /accounts');
    return;
}

http_response_code(404);
echo 'Not found.';
