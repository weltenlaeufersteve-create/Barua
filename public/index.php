<?php

require __DIR__ . '/../vendor/autoload.php';

use Barua\Auth\Auth;

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
    require __DIR__ . '/../views/dashboard.php';
    return;
}

http_response_code(404);
echo 'Not found.';
