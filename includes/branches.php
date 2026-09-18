<?php
require_once (getenv('APP_INCLUDES_PATH') ?: ((preg_match('/^https?:\/\//i', getenv('APP_ROOT_PATH') ?: '') ? dirname(__DIR__) : (getenv('APP_ROOT_PATH') ?: dirname(__DIR__))) . '/includes')) . '/db.php';
require_once app_path('includes/crypto.php');

function report_branches(): array
{
    return db()->query('SELECT * FROM report_branches ORDER BY name')->fetchAll();
}

function report_branch(?int $id): ?array
{
    if (!$id) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM report_branches WHERE id = ? AND status = "active" LIMIT 1');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function branch_pdo(array $branch): PDO
{
    $dsn = "mysql:host={$branch['db_host']};port={$branch['db_port']};dbname={$branch['db_name']};charset={$branch['db_charset']}";
    return new PDO($dsn, $branch['db_user'], decrypt_branch_password($branch['db_pass'] ?? ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}
