<?php
require_once (getenv('APP_INCLUDES_PATH') ?: ((preg_match('/^https?:\/\//i', getenv('APP_ROOT_PATH') ?: '') ? dirname(__DIR__) : (getenv('APP_ROOT_PATH') ?: dirname(__DIR__))) . '/includes')) . '/security.php';
require_once app_path('includes/settings.php');

function render_header(string $title, ?array $user = null): void
{
    $config = app_config();
    ?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> | <?= e($config['app_name']) ?></title>
    <link rel="stylesheet" href="/assets/styles.css">
</head>
<body>
<header class="topbar">
    <a class="brand" href="/index.php">
        <?php if (app_logo_path()): ?><img class="brand-logo" src="<?= e(app_logo_path()) ?>" alt="Logo"><?php endif; ?>
        <span><?= e($config['app_name']) ?></span>
    </a>
    <?php if ($user): ?>
        <nav>
            <a href="/index.php">Panel</a>
            <?php if (($user['account_type'] ?? 'internal') === 'internal'): ?>
                <a href="/admin/branches.php">Sucursales</a>
                <a href="/admin/clients.php">Clientes</a>
                <a href="/admin/providers.php">Proveedores</a>
                <a href="/admin/users.php">Usuarios</a>
                <a href="/admin/settings.php">Configuración</a>
            <?php endif; ?>
            <a href="/logout.php">Salir</a>
        </nav>
    <?php endif; ?>
</header>
<main class="container">
    <?php
}

function render_footer(): void
{
    ?>
</main>
</body>
</html>
    <?php
}

function flash_message(): ?string
{
    start_secure_session();
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $message;
}

function set_flash(string $message): void
{
    start_secure_session();
    $_SESSION['flash'] = $message;
}
