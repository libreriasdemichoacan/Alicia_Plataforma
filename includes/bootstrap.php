<?php
function app_config(): array
{
    static $config = null;
    if ($config === null) {
        $root = getenv('APP_ROOT_PATH') ?: dirname(__DIR__);
        if (preg_match('/^https?:\/\//i', $root)) {
            $root = dirname(__DIR__);
        }
        $configPath = getenv('APP_CONFIG_PATH') ?: rtrim($root, '/\\') . '/config';
        $config = require rtrim($configPath, '/\\') . '/config.php';
        if (getenv('APP_INCLUDES_PATH') === false || getenv('APP_INCLUDES_PATH') === '') {
            $config['paths']['includes'] = __DIR__;
        }
    }
    return $config;
}

function app_path(string $path = ''): string
{
    $config = app_config();
    if ($path === '') {
        return $config['paths']['root'];
    }

    $normalized = str_replace('\\', '/', ltrim($path, '/'));
    foreach (['includes', 'config', 'public', 'storage'] as $area) {
        if ($normalized === $area || str_starts_with($normalized, $area . '/')) {
            $relative = substr($normalized, strlen($area));
            return rtrim($config['paths'][$area], '/\\') . $relative;
        }
    }

    return rtrim($config['paths']['root'], '/\\') . '/' . $normalized;
}

function configure_error_logging(): void
{
    $config = app_config();
    $logging = $config['logging'];

    ini_set('display_errors', $logging['display_errors'] ? '1' : '0');
    ini_set('log_errors', $logging['enabled'] ? '1' : '0');

    if ($logging['enabled']) {
        $logDir = dirname($logging['path']);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0775, true);
        }
        ini_set('error_log', $logging['path']);
    }

    set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        $config = app_config();
        if ($config['logging']['enabled']) {
            error_log("PHP error [{$severity}] {$message} in {$file}:{$line}");
        }

        return false;
    });
}

configure_error_logging();
