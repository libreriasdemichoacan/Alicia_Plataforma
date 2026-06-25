<?php
require_once (getenv('APP_INCLUDES_PATH') ?: ((preg_match('/^https?:\/\//i', getenv('APP_ROOT_PATH') ?: '') ? dirname(__DIR__, 2) : (getenv('APP_ROOT_PATH') ?: dirname(__DIR__, 2))) . '/includes')) . '/auth.php';
require_once app_path('includes/layout.php');
require_once app_path('includes/uploads.php');
require_once app_path('includes/portal_users.php');
$user = require_login();
require_permission('providers');
$error = null;
$editingId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editing = null;
if ($editingId > 0) {
    require_permission('providers', 'update');
    $stmt = db()->prepare('SELECT * FROM third_parties WHERE id = ? AND type = "provider" LIMIT 1');
    $stmt->execute([$editingId]);
    $editing = $stmt->fetch() ?: null;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verify_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $logoPath = upload_image('logo', 'providers', $error);
    if (!$error && $id > 0) {
        require_permission('providers', 'update');
        $current = db()->prepare('SELECT logo_path FROM third_parties WHERE id = ? AND type = "provider" LIMIT 1');
        $current->execute([$id]);
        $existingLogo = $current->fetchColumn() ?: null;
        $stmt = db()->prepare('UPDATE third_parties SET internal_number = ?, legal_name = ?, tax_id = ?, email = ?, phone = ?, logo_path = ? WHERE id = ? AND type = "provider"');
        $stmt->execute([trim($_POST['internal_number']), trim($_POST['legal_name']), trim($_POST['tax_id']), trim($_POST['email']), trim($_POST['phone']), $logoPath ?: $existingLogo, $id]);
        save_portal_credentials($id, $_POST['portal_email'] ?? '', $_POST['portal_password'] ?? '', $error);
        if ($error) { $editingId = $id; } else {
        set_flash('Proveedor actualizado correctamente.');
        header('Location: /admin/providers.php'); exit; }
    } elseif (!$error) {
        require_permission('providers', 'create');
        $stmt = db()->prepare('INSERT INTO third_parties (type, internal_number, legal_name, tax_id, email, phone, logo_path, status) VALUES ("provider", ?, ?, ?, ?, ?, ?, "active")');
        $stmt->execute([trim($_POST['internal_number']), trim($_POST['legal_name']), trim($_POST['tax_id']), trim($_POST['email']), trim($_POST['phone']), $logoPath]);
        $newId = (int) db()->lastInsertId();
        save_portal_credentials($newId, $_POST['portal_email'] ?? '', $_POST['portal_password'] ?? '', $error);
        if ($error) { $editingId = $newId; } else {
        set_flash('Proveedor registrado correctamente.');
        header('Location: /admin/providers.php'); exit; }
    }
}
if ($editingId > 0 && !$editing) {
    $stmt = db()->prepare('SELECT * FROM third_parties WHERE id = ? AND type = "provider" LIMIT 1');
    $stmt->execute([$editingId]);
    $editing = $stmt->fetch() ?: null;
}
$rows = db()->query('SELECT tp.*, rb.name AS branch_name FROM third_parties tp LEFT JOIN report_branches rb ON rb.id = tp.branch_id WHERE tp.type = "provider" ORDER BY tp.created_at DESC')->fetchAll();
$portalAccess = $editing ? portal_user_for_third_party((int) $editing['id']) : null;
$csrfToken = csrf_token();
render_header('Proveedores', $user);
?>
<h1>Proveedores</h1><?php if ($m = flash_message()): ?><div class="alert"><?= e($m) ?></div><?php endif; ?><?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<section class="card"><h2><?= $editing ? 'Editar proveedor' : 'Alta de proveedor' ?></h2><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>"><div class="grid"><div class="form-row"><label>Número interno</label><input name="internal_number" value="<?= e($editing['internal_number'] ?? '') ?>"></div><div class="form-row"><label>Razón social</label><input name="legal_name" value="<?= e($editing['legal_name'] ?? '') ?>" required></div><div class="form-row"><label>RFC / ID fiscal</label><input name="tax_id" value="<?= e($editing['tax_id'] ?? '') ?>"></div></div><div class="grid"><div class="form-row"><label>Correo</label><input type="email" name="email" value="<?= e($editing['email'] ?? '') ?>"></div><div class="form-row"><label>Teléfono</label><input name="phone" value="<?= e($editing['phone'] ?? '') ?>"></div><div class="form-row"><label>Logo</label><input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml"><small class="muted">PNG, JPG, WEBP o SVG, máximo 2 MB.</small></div></div><h2>Acceso al portal</h2><div class="grid"><div class="form-row"><label>Correo de acceso</label><input type="email" name="portal_email" value="<?= e($portalAccess['email'] ?? '') ?>"></div><div class="form-row"><label>Contraseña de acceso</label><input type="password" name="portal_password"><small class="muted">Dejar vacío para conservarla al editar. <?= e(password_rules_message()) ?></small></div></div><?php if (!empty($editing['logo_path'])): ?><div class="logo-preview"><img src="<?= e($editing['logo_path']) ?>" alt="Logo proveedor"></div><?php endif; ?><div class="actions"><button><?= $editing ? 'Actualizar proveedor' : 'Guardar proveedor' ?></button><?php if ($editing): ?><a class="btn secondary" href="/admin/providers.php">Cancelar</a><?php endif; ?></div></form></section>
<?php include app_path('public/admin/partials/third_party_table.php'); render_footer(); ?>
