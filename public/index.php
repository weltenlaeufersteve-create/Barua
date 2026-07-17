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
    $view = in_array($_GET['view'] ?? '', ['sent', 'pinned', 'archive', 'trash', 'drafts', 'newsletters', 'notifications', 'people'], true) ? $_GET['view'] : 'inbox';
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

    $view = in_array($_GET['view'] ?? '', ['inbox', 'pinned', 'archive', 'trash', 'newsletters', 'notifications', 'people'], true)
        ? $_GET['view'] : 'inbox';
    $accountId = ($_GET['account'] ?? '') !== '' ? (int) $_GET['account'] : null;
    $after = (int) ($_GET['after'] ?? 0);

    $R = \Barua\Mail\MessageRepository::class;
    $rows = match ($view) {
        'pinned'        => $R::pinnedMessages(60, $accountId),
        'archive'       => $R::roleMessages('archive', 60, $accountId),
        'trash'         => $R::roleMessages('trash', 60, $accountId),
        'newsletters'   => $R::groupMessages('newsletter', 60, $accountId),
        'notifications' => $R::groupMessages('notification', 60, $accountId),
        'people'        => $R::peopleMessages(60, $accountId),
        default         => $R::unifiedInbox(60, $accountId),
    };

    // Only rows newer than the client's cursor (genuinely new messages get higher ids).
    $newRows = [];
    foreach ($rows as $row) {
        if ((int) $row['id'] > $after) {
            $newRows[] = [
                'id'   => (int) $row['id'],
                'html' => renderMailRow($row, false, false),
                'data' => mailRowData($row),
            ];
        }
    }

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

if (preg_match('#^/messages/(\d+)/(pin|archive|trash|read)$#', $path, $m) && $method === 'POST') {
    header('Content-Type: application/json');
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Invalid request']);
        return;
    }
    $id = (int) $m[1];
    $result = match ($m[2]) {
        'pin'     => \Barua\Mail\MessageActions::setPin($id, ($_POST['pinned'] ?? '') === '1'),
        'archive' => \Barua\Mail\MessageActions::archive($id),
        'trash'   => \Barua\Mail\MessageActions::trash($id),
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

if ($path === '/accounts' && $method === 'GET') {
    $accounts = AccountRepository::all();
    $csrfToken = Auth::csrfToken();
    $error = null;
    require __DIR__ . '/../views/accounts.php';
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
    $data['signature'] = trim($_POST['signature'] ?? '');

    $missing = array_filter($required, fn($field) => $data[$field] === '');
    if (!empty($missing)) {
        $accounts = AccountRepository::all();
        $csrfToken = Auth::csrfToken();
        $error = 'Please fill in all required fields.';
        require __DIR__ . '/../views/accounts.php';
        return;
    }

    AccountRepository::create($data);
    header('Location: /accounts');
    return;
}

if (preg_match('#^/accounts/(\d+)$#', $path, $m) && $method === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        echo 'Invalid request.';
        return;
    }
    $fields = ['label', 'email', 'signature', 'imap_host', 'imap_port', 'imap_encryption',
               'imap_username', 'imap_password', 'smtp_host', 'smtp_port', 'smtp_encryption',
               'smtp_username', 'smtp_password'];
    $data = [];
    foreach ($fields as $f) {
        $data[$f] = trim($_POST[$f] ?? '');
    }
    AccountRepository::update((int) $m[1], $data);
    header('Location: /');
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
