<?php
if (!function_exists('env_value')) {
    function load_env_file(string $path): void
    {
        if (!is_readable($path)) {
            return;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $line, 2));
            $value = trim($value, "\"'");
            if (getenv($key) === false) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
            }
        }
    }


    function normalize_app_root_path(mixed $path, string $default): string
    {
        $path = trim((string) $path);
        if ($path === '' || preg_match('/^https?:\/\//i', $path)) {
            return rtrim($default, '/\\');
        }

        return rtrim($path, '/\\');
    }

    function env_value(string $key, mixed $default = null): mixed
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            return $default;
        }

        return match (strtolower($value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            default => $value,
        };
    }
}

$defaultRoot = dirname(__DIR__);
$envRoot = normalize_app_root_path(getenv('APP_ROOT_PATH') ?: $defaultRoot, $defaultRoot);
load_env_file($envRoot . '/.env');
$appRoot = normalize_app_root_path(env_value('APP_ROOT_PATH', $defaultRoot), $defaultRoot);
$includesPath = normalize_app_root_path(env_value('APP_INCLUDES_PATH', $appRoot . '/includes'), $appRoot . '/includes');
$configPath = normalize_app_root_path(env_value('APP_CONFIG_PATH', $appRoot . '/config'), $appRoot . '/config');
$publicPath = normalize_app_root_path(env_value('APP_PUBLIC_PATH', $appRoot . '/public'), $appRoot . '/public');
$storagePath = normalize_app_root_path(env_value('APP_STORAGE_PATH', $appRoot . '/storage'), $appRoot . '/storage');

return [
    'app_name' => env_value('APP_NAME', 'Alicia Cuenta'),
    'base_url' => env_value('APP_BASE_URL', ''),
    'environment' => env_value('APP_ENV', 'local'),
    'branding' => [
        'login_title' => env_value('LOGIN_TITLE', 'Ingreso seguro'),
        'logo_path' => env_value('APP_LOGO_PATH', ''),
    ],
    'paths' => [
        'root' => $appRoot,
        'includes' => $includesPath,
        'config' => $configPath,
        'public' => $publicPath,
        'storage' => $storagePath,
        'logs' => $storagePath . '/logs',
    ],
    'db' => [
        'default' => [
            'host' => env_value('DB_HOST', '127.0.0.1'),
            'port' => env_value('DB_PORT', '3306'),
            'name' => env_value('DB_NAME', 'alicia_cuenta'),
            'user' => env_value('DB_USER', 'root'),
            'pass' => env_value('DB_PASS', ''),
            'charset' => env_value('DB_CHARSET', 'utf8mb4'),
        ],
        'remote_reports' => [
            'enabled' => env_value('REPORT_DB_ENABLED', false),
            'host' => env_value('REPORT_DB_HOST', ''),
            'port' => env_value('REPORT_DB_PORT', '3306'),
            'name' => env_value('REPORT_DB_NAME', ''),
            'user' => env_value('REPORT_DB_USER', ''),
            'pass' => env_value('REPORT_DB_PASS', ''),
            'charset' => env_value('REPORT_DB_CHARSET', 'utf8mb4'),
        ],
    ],
    'security' => [
        'branch_password_key' => env_value('BRANCH_DB_PASSWORD_KEY', env_value('APP_KEY', '')),
    ],
    'logging' => [
        'enabled' => env_value('LOG_ERRORS', true),
        'display_errors' => env_value('DISPLAY_ERRORS', false),
        'path' => env_value('ERROR_LOG_PATH', $storagePath . '/logs/app_errors.log'),
    ],
    'session' => [
        'name' => env_value('SESSION_NAME', 'ALICIA_CUENTA_SESSID'),
        'timeout_minutes' => (int) env_value('SESSION_TIMEOUT_MINUTES', 30),
    ],
];
