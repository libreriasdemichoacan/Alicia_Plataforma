<?php
require_once (getenv('APP_INCLUDES_PATH') ?: ((preg_match('/^https?:\/\//i', getenv('APP_ROOT_PATH') ?: '') ? dirname(__DIR__) : (getenv('APP_ROOT_PATH') ?: dirname(__DIR__))) . '/includes')) . '/db.php';

function setting(string $key, ?string $default = null): ?string
{
    static $cache = [];
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $stmt = db()->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
    } catch (Throwable $exception) {
        error_log('No se pudo leer configuración: ' . $exception->getMessage());
        $value = false;
    }

    $cache[$key] = $value === false ? $default : (string) $value;
    return $cache[$key];
}

function save_setting(string $key, string $value): void
{
    $stmt = db()->prepare('INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP');
    $stmt->execute([$key, $value]);
}

function app_logo_path(): string
{
    return setting('app_logo_path', app_config()['branding']['logo_path']);
}
