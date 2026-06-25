<?php
require_once (getenv('APP_INCLUDES_PATH') ?: ((preg_match('/^https?:\/\//i', getenv('APP_ROOT_PATH') ?: '') ? dirname(__DIR__, 2) : (getenv('APP_ROOT_PATH') ?: dirname(__DIR__, 2))) . '/includes')) . '/auth.php';
require_once app_path('includes/layout.php');
$user = require_login();
require_permission('users');
$error = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_permission('users', 'create');
    verify_csrf();
    $password = $_POST['password'] ?? '';
    if (!password_is_strong($password)) {
        $error = password_rules_message();
    } else {
        $stmt = db()->prepare('INSERT INTO users (role_id, name, email, password_hash, status) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([(int)$_POST['role_id'], trim($_POST['name']), trim($_POST['email']), password_hash($password, PASSWORD_DEFAULT), $_POST['status']]);
        set_flash('Usuario interno creado correctamente.');
        header('Location: /admin/users.php'); exit;
    }
}
$roles = db()->query('SELECT id, name FROM roles ORDER BY level DESC')->fetchAll();
$users = db()->query('SELECT u.name, u.email, u.status, r.name AS role_name FROM users u INNER JOIN roles r ON r.id = u.role_id ORDER BY u.created_at DESC')->fetchAll();
$csrfToken = csrf_token();
render_header('Usuarios internos', $user);
?>
<h1>Usuarios internos</h1><?php if ($m = flash_message()): ?><div class="alert"><?= e($m) ?></div><?php endif; ?><?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<section class="card"><h2>Alta de usuario</h2><form method="post"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><div class="grid"><div class="form-row"><label>Nombre</label><input name="name" required></div><div class="form-row"><label>Correo</label><input type="email" name="email" required></div><div class="form-row"><label>Rol</label><select name="role_id"><?php foreach ($roles as $role): ?><option value="<?= (int)$role['id'] ?>"><?= e($role['name']) ?></option><?php endforeach; ?></select></div></div><div class="grid"><div class="form-row"><label>Contraseña</label><input type="password" name="password" required><small class="muted"><?= e(password_rules_message()) ?></small></div><div class="form-row"><label>Estatus</label><select name="status"><option value="active">Activo</option><option value="inactive">Inactivo</option></select></div></div><button>Crear usuario</button></form></section>
<section style="margin-top:24px"><table><thead><tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Estatus</th></tr></thead><tbody><?php foreach ($users as $row): ?><tr><td><?= e($row['name']) ?></td><td><?= e($row['email']) ?></td><td><?= e($row['role_name']) ?></td><td><span class="badge"><?= e($row['status']) ?></span></td></tr><?php endforeach; ?></tbody></table></section>
<?php render_footer(); ?>
