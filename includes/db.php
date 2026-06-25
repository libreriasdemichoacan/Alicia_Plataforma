<?php
require_once (getenv('APP_INCLUDES_PATH') ?: ((preg_match('/^https?:\/\//i', getenv('APP_ROOT_PATH') ?: '') ? dirname(__DIR__) : (getenv('APP_ROOT_PATH') ?: dirname(__DIR__))) . '/includes')) . '/bootstrap.php';

function db(string $connection = 'default'): PDO
{
    static $connections = [];
    if (isset($connections[$connection]) && $connections[$connection] instanceof PDO) {
        return $connections[$connection];
    }

    $config = app_config();
    if (!isset($config['db'][$connection])) {
        throw new InvalidArgumentException("La conexión de base de datos '{$connection}' no existe.");
    }

    $db = $config['db'][$connection];
    if (($db['enabled'] ?? true) === false) {
        throw new RuntimeException("La conexión de base de datos '{$connection}' está desactivada.");
    }

    $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset={$db['charset']}";

    $connections[$connection] = new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $connections[$connection];
}

function remote_reports_db(): PDO
{
    return db('remote_reports');
}
