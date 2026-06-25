<?php
require_once (getenv('APP_INCLUDES_PATH') ?: ((preg_match('/^https?:\/\//i', getenv('APP_ROOT_PATH') ?: '') ? dirname(__DIR__) : (getenv('APP_ROOT_PATH') ?: dirname(__DIR__))) . '/includes')) . '/db.php';
require_once app_path('includes/security.php');

function current_user(): ?array
{
    start_secure_session();
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $accountType = $_SESSION['account_type'] ?? 'internal';

    $timeout = app_config()['session']['timeout_minutes'] * 60;
    if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > $timeout) {
        logout();
        return null;
    }
    $_SESSION['last_activity'] = time();

    if ($accountType === 'third_party') {
        $stmt = db()->prepare('SELECT tpu.id, tpu.email, tp.id AS third_party_id, tp.branch_id, tp.type AS third_party_type, tp.legal_name AS name, tp.internal_number, tp.logo_path, "Portal" AS role_name, 0 AS role_level
            FROM third_party_users tpu INNER JOIN third_parties tp ON tp.id = tpu.third_party_id
            WHERE tpu.id = ? AND tpu.status = "active" AND tp.status = "active"');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
        if ($user) {
            $user['account_type'] = 'third_party';
        }
        return $user;
    }

    $stmt = db()->prepare('SELECT u.id, u.name, u.email, u.status, r.name AS role_name, r.level AS role_level
        FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND u.status = "active"');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch() ?: null;
    if ($user) {
        $user['account_type'] = 'internal';
    }
    return $user;
}

function login(string $email, string $password): bool
{
    $stmt = db()->prepare('SELECT id, password_hash, status FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && $user['status'] === 'active' && password_verify($password, $user['password_hash'])) {
        start_secure_session();
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['account_type'] = 'internal';
        $_SESSION['last_activity'] = time();
        return true;
    }

    $stmt = db()->prepare('SELECT id, password_hash, status FROM third_party_users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $portalUser = $stmt->fetch();
    if (!$portalUser || $portalUser['status'] !== 'active' || !password_verify($password, $portalUser['password_hash'])) {
        return false;
    }

    start_secure_session();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$portalUser['id'];
    $_SESSION['account_type'] = 'third_party';
    $_SESSION['last_activity'] = time();
    return true;
}

function logout(): void
{
    start_secure_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        header('Location: /login.php');
        exit;
    }
    return $user;
}

function user_can(string $module, string $action = 'view'): bool
{
    $user = current_user();
    if (!$user) {
        return false;
    }
    if (($user['account_type'] ?? 'internal') !== 'internal') {
        return false;
    }
    if ((int)$user['role_level'] >= 100) {
        return true;
    }

    $stmt = db()->prepare('SELECT 1 FROM permissions p
        INNER JOIN role_permissions rp ON rp.permission_id = p.id
        INNER JOIN users u ON u.role_id = rp.role_id
        WHERE u.id = ? AND p.module = ? AND p.action = ? LIMIT 1');
    $stmt->execute([$user['id'], $module, $action]);
    return (bool)$stmt->fetchColumn();
}

function require_permission(string $module, string $action = 'view'): void
{
    if (!user_can($module, $action)) {
        http_response_code(403);
        exit('No tienes permiso para acceder a este módulo.');
    }
}
