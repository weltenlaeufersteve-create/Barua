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
    $view = in_array($_GET['view'] ?? '', ['sent', 'pinned'], true) ? $_GET['view'] : 'inbox';
    require __DIR__ . '/../views/dashboard.php';
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
        // MailSender already appended the copy to the IMAP Sent folder; pull it into the cache.
        \Barua\Mail\SyncService::syncSentFolder($account);
    } else {
        http_response_code(502);
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
