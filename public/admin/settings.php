<?php
require_once (getenv('APP_INCLUDES_PATH') ?: ((preg_match('/^https?:\/\//i', getenv('APP_ROOT_PATH') ?: '') ? dirname(__DIR__, 2) : (getenv('APP_ROOT_PATH') ?: dirname(__DIR__, 2))) . '/includes')) . '/auth.php';
require_once app_path('includes/layout.php');
require_once app_path('includes/settings.php');

$user = require_login();
require_permission('settings');
$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_permission('settings', 'update');
    verify_csrf();

    if (!isset($_FILES['logo']) || $_FILES['logo']['error'] === UPLOAD_ERR_NO_FILE) {
        $error = 'Selecciona un archivo de logo para cargar.';
    } elseif ($_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
        $error = 'No se pudo cargar el archivo. Intenta nuevamente.';
    } else {
        $allowedTypes = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
        ];
        $mimeType = mime_content_type($_FILES['logo']['tmp_name']) ?: '';
        if (!isset($allowedTypes[$mimeType])) {
            $error = 'El logo debe ser PNG, JPG, WEBP o SVG.';
        } elseif ($_FILES['logo']['size'] > 2 * 1024 * 1024) {
            $error = 'El logo no debe superar 2 MB.';
        } else {
            $extension = $allowedTypes[$mimeType];
            $uploadDir = app_path('public/uploads/logos');
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }
            $filename = 'logo_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
            $target = $uploadDir . '/' . $filename;
            if (!move_uploaded_file($_FILES['logo']['tmp_name'], $target)) {
                $error = 'No se pudo guardar el logo en el servidor.';
            } else {
                save_setting('app_logo_path', '/uploads/logos/' . $filename);
                set_flash('Logo general actualizado correctamente.');
                header('Location: /admin/settings.php');
                exit;
            }
        }
    }
}

$csrfToken = csrf_token();
$currentLogo = app_logo_path();
render_header('Configuración', $user);
?>
<h1>Configuración general</h1>
<?php if ($m = flash_message()): ?><div class="alert"><?= e($m) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<section class="card">
    <h2>Logo del sistema</h2>
    <p class="muted">Carga un nuevo logo para reemplazar o modificar la imagen general del sistema.</p>
    <?php if ($currentLogo): ?>
        <div class="logo-preview"><img src="<?= e($currentLogo) ?>" alt="Logo actual"></div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        <div class="form-row">
            <label>Nuevo logo</label>
            <input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml" required>
            <small class="muted">Formatos permitidos: PNG, JPG, WEBP o SVG. Tamaño máximo: 2 MB.</small>
        </div>
        <button type="submit">Guardar logo</button>
    </form>
</section>
<?php render_footer(); ?>
