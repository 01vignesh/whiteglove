<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function start_session_if_needed(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function auth_login(string $email, string $password): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT id, name, email, password_hash, role, is_active FROM users WHERE email = ? LIMIT 1'
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || (int) $user['is_active'] !== 1) {
        return null;
    }

    if (!password_verify($password, $user['password_hash'])) {
        return null;
    }

    start_session_if_needed();
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
    ];

    return $_SESSION['user'];
}

function auth_logout(): void
{
    start_session_if_needed();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function auth_user(): ?array
{
    start_session_if_needed();
    return $_SESSION['user'] ?? null;
}

function require_auth(): array
{
    $user = auth_user();
    if (!$user) {
        header('Location: /WhiteGlove/public/login.php');
        exit;
    }

    $pdo = db();
    $statusStmt = $pdo->prepare('SELECT is_active FROM users WHERE id = ? LIMIT 1');
    $statusStmt->execute([(int) ($user['id'] ?? 0)]);
    $statusRow = $statusStmt->fetch();
    if (!$statusRow || (int) ($statusRow['is_active'] ?? 0) !== 1) {
        auth_logout();
        header('Location: /WhiteGlove/public/login.php?inactive=1');
        exit;
    }
    return $user;
}

function require_role(array $roles): array
{
    $user = require_auth();
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        echo 'Forbidden: You do not have permission to access this page.';
        exit;
    }
    return $user;
}
