<?php
require_once (getenv('APP_INCLUDES_PATH') ?: ((preg_match('/^https?:\/\//i', getenv('APP_ROOT_PATH') ?: '') ? dirname(__DIR__) : (getenv('APP_ROOT_PATH') ?: dirname(__DIR__))) . '/includes')) . '/db.php';

const ENCRYPTED_BRANCH_PASSWORD_PREFIX = 'enc:v1:';

function branch_password_secret(): string
{
    $security = app_config()['security'] ?? [];
    $secret = trim((string)($security['branch_password_key'] ?? ''));
    if ($secret === '') {
        $secret = trim((string)(app_config()['db']['default']['pass'] ?? ''));
    }
    if ($secret === '') {
        throw new RuntimeException('Configura BRANCH_DB_PASSWORD_KEY en el archivo .env para cifrar contraseñas de sucursales.');
    }
    return hash('sha256', $secret, true);
}

function encrypt_branch_password(string $plainPassword): string
{
    if ($plainPassword === '') {
        return '';
    }
    if (str_starts_with($plainPassword, ENCRYPTED_BRANCH_PASSWORD_PREFIX)) {
        return $plainPassword;
    }

    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt($plainPassword, 'aes-256-gcm', branch_password_secret(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($ciphertext === false) {
        throw new RuntimeException('No fue posible cifrar la contraseña de la sucursal.');
    }

    return ENCRYPTED_BRANCH_PASSWORD_PREFIX . base64_encode($iv . $tag . $ciphertext);
}

function decrypt_branch_password(?string $storedPassword): string
{
    $storedPassword = (string)$storedPassword;
    if ($storedPassword === '' || !str_starts_with($storedPassword, ENCRYPTED_BRANCH_PASSWORD_PREFIX)) {
        return $storedPassword;
    }

    $payload = base64_decode(substr($storedPassword, strlen(ENCRYPTED_BRANCH_PASSWORD_PREFIX)), true);
    if ($payload === false || strlen($payload) <= 28) {
        throw new RuntimeException('La contraseña cifrada de la sucursal no tiene un formato válido.');
    }

    $iv = substr($payload, 0, 12);
    $tag = substr($payload, 12, 16);
    $ciphertext = substr($payload, 28);
    $plainPassword = openssl_decrypt($ciphertext, 'aes-256-gcm', branch_password_secret(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($plainPassword === false) {
        throw new RuntimeException('No fue posible descifrar la contraseña de la sucursal.');
    }

    return $plainPassword;
}
