<?php
require_once (getenv('APP_INCLUDES_PATH') ?: ((preg_match('/^https?:\/\//i', getenv('APP_ROOT_PATH') ?: '') ? dirname(__DIR__) : (getenv('APP_ROOT_PATH') ?: dirname(__DIR__))) . '/includes')) . '/db.php';
require_once app_path('includes/security.php');

function portal_user_for_third_party(int $thirdPartyId): ?array
{
    $stmt = db()->prepare('SELECT * FROM third_party_users WHERE third_party_id = ? LIMIT 1');
    $stmt->execute([$thirdPartyId]);
    return $stmt->fetch() ?: null;
}

function save_portal_credentials(int $thirdPartyId, string $email, string $password, ?string &$error): void
{
    $email = trim($email);
    if ($email === '' && $password === '') {
        return;
    }
    if ($email === '') {
        $error = 'Ingresa el correo de acceso al portal.';
        return;
    }
    if ($password !== '' && !password_is_strong($password)) {
        $error = password_rules_message();
        return;
    }

    $existing = portal_user_for_third_party($thirdPartyId);
    if ($existing) {
        if ($password !== '') {
            $stmt = db()->prepare('UPDATE third_party_users SET email = ?, password_hash = ?, status = "active" WHERE third_party_id = ?');
            $stmt->execute([$email, password_hash($password, PASSWORD_DEFAULT), $thirdPartyId]);
        } else {
            $stmt = db()->prepare('UPDATE third_party_users SET email = ?, status = "active" WHERE third_party_id = ?');
            $stmt->execute([$email, $thirdPartyId]);
        }
        return;
    }

    if ($password === '') {
        $error = 'Ingresa una contraseña para crear el acceso al portal.';
        return;
    }
    $stmt = db()->prepare('INSERT INTO third_party_users (third_party_id, email, password_hash, status) VALUES (?, ?, ?, "active")');
    $stmt->execute([$thirdPartyId, $email, password_hash($password, PASSWORD_DEFAULT)]);
}
