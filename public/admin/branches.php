<?php
require_once (getenv('APP_INCLUDES_PATH') ?: ((preg_match('/^https?:\/\//i', getenv('APP_ROOT_PATH') ?: '') ? dirname(__DIR__, 2) : (getenv('APP_ROOT_PATH') ?: dirname(__DIR__, 2))) . '/includes')) . '/auth.php';
require_once app_path('includes/layout.php');
require_once app_path('includes/branches.php');
require_once app_path('includes/crypto.php');

$user = require_login();
require_permission('branches');
$error = null;
$editingId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editing = null;
if ($editingId > 0) {
    require_permission('branches', 'update');
    $stmt = db()->prepare('SELECT * FROM report_branches WHERE id = ? LIMIT 1');
    $stmt->execute([$editingId]);
    $editing = $stmt->fetch() ?: null;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $data = [trim($_POST['name']), trim($_POST['db_host']), trim($_POST['db_port'] ?: '3306'), trim($_POST['db_name']), trim($_POST['db_user']), trim($_POST['db_charset'] ?: 'utf8mb4'), $_POST['status'] ?? 'active'];
    $password = (string)($_POST['db_pass'] ?? '');
    if ($data[0] === '' || $data[1] === '' || $data[3] === '' || $data[4] === '') {
        $error = 'Nombre, host, base de datos y usuario son obligatorios.';
    } elseif ($id > 0) {
        require_permission('branches', 'update');
        if ($password === '') {
            $stmt = db()->prepare('UPDATE report_branches SET name=?, db_host=?, db_port=?, db_name=?, db_user=?, db_charset=?, status=? WHERE id=?');
            $stmt->execute([...$data, $id]);
        } else {
            $stmt = db()->prepare('UPDATE report_branches SET name=?, db_host=?, db_port=?, db_name=?, db_user=?, db_pass=?, db_charset=?, status=? WHERE id=?');
            $stmt->execute([$data[0], $data[1], $data[2], $data[3], $data[4], encrypt_branch_password($password), $data[5], $data[6], $id]);
        }
        set_flash('Sucursal actualizada correctamente.');
        header('Location: /admin/branches.php'); exit;
    } else {
        require_permission('branches', 'create');
        $stmt = db()->prepare('INSERT INTO report_branches (name, db_host, db_port, db_name, db_user, db_pass, db_charset, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$data[0], $data[1], $data[2], $data[3], $data[4], encrypt_branch_password($password), $data[5], $data[6]]);
        set_flash('Sucursal registrada correctamente.');
        header('Location: /admin/branches.php'); exit;
    }
}

$branches = report_branches();
$csrfToken = csrf_token();
render_header('Sucursales', $user);
?>
<h1>Sucursales</h1><?php if ($m = flash_message()): ?><div class="alert"><?= e($m) ?></div><?php endif; ?><?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<section class="card"><h2><?= $editing ? 'Editar sucursal' : 'Alta de sucursal' ?></h2><form method="post"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>"><div class="grid"><div class="form-row"><label>Nombre</label><input name="name" value="<?= e($editing['name'] ?? '') ?>" required></div><div class="form-row"><label>Host MySQL</label><input name="db_host" value="<?= e($editing['db_host'] ?? '') ?>" required></div><div class="form-row"><label>Puerto</label><input name="db_port" value="<?= e($editing['db_port'] ?? '3306') ?>"></div></div><div class="grid"><div class="form-row"><label>Base de datos</label><input name="db_name" value="<?= e($editing['db_name'] ?? '') ?>" required></div><div class="form-row"><label>Usuario</label><input name="db_user" value="<?= e($editing['db_user'] ?? '') ?>" required></div><div class="form-row"><label>Contraseña</label><input type="password" name="db_pass"><small class="muted">En edición, dejar vacío para conservarla.</small></div></div><div class="grid"><div class="form-row"><label>Charset</label><input name="db_charset" value="<?= e($editing['db_charset'] ?? 'utf8mb4') ?>"></div><div class="form-row"><label>Estatus</label><select name="status"><option value="active" <?= ($editing['status'] ?? '') === 'active' ? 'selected' : '' ?>>Activo</option><option value="inactive" <?= ($editing['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactivo</option></select></div></div><div class="actions"><button><?= $editing ? 'Actualizar sucursal' : 'Guardar sucursal' ?></button><?php if ($editing): ?><a class="btn secondary" href="/admin/branches.php">Cancelar</a><?php endif; ?></div></form></section>
<section style="margin-top:24px"><table><thead><tr><th>Nombre</th><th>Host</th><th>BD</th><th>Usuario</th><th>Estatus</th><th>Acciones</th></tr></thead><tbody><?php foreach ($branches as $branch): ?><tr><td><?= e($branch['name']) ?></td><td><?= e($branch['db_host']) ?>:<?= e($branch['db_port']) ?></td><td><?= e($branch['db_name']) ?></td><td><?= e($branch['db_user']) ?></td><td><span class="badge"><?= e($branch['status']) ?></span></td><td><a class="btn secondary" href="/admin/branches.php?edit=<?= (int)$branch['id'] ?>">Editar</a></td></tr><?php endforeach; ?><?php if (!$branches): ?><tr><td colspan="6" class="muted">Aún no hay sucursales.</td></tr><?php endif; ?></tbody></table></section>
<?php render_footer(); ?>
