<?php
require_once (getenv('APP_INCLUDES_PATH') ?: ((preg_match('/^https?:\/\//i', getenv('APP_ROOT_PATH') ?: '') ? dirname(__DIR__) : (getenv('APP_ROOT_PATH') ?: dirname(__DIR__))) . '/includes')) . '/auth.php';
require_once app_path('includes/layout.php');

$error = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verify_csrf();
    if (login(trim($_POST['email'] ?? ''), $_POST['password'] ?? '')) {
        header('Location: /index.php');
        exit;
    }
    $error = 'Correo o contraseña incorrectos, o usuario inactivo.';
}

$csrfToken = csrf_token();
$loginTitle = app_config()['branding']['login_title'];
render_header('Iniciar sesión');
?>
<section class="card login-card">
    <h1><?= e($loginTitle) ?></h1>
    <p class="muted">Accede al portal de estados de cuenta con credenciales autorizadas.</p>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        <div class="form-row"><label>Correo electrónico</label><input type="email" name="email" required autofocus></div>
        <div class="form-row"><label>Contraseña</label><input type="password" name="password" required></div>
        <button type="submit">Entrar</button>
    </form>
</section>
<?php render_footer(); ?>
