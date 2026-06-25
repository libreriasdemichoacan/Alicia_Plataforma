<?php
require_once (getenv('APP_INCLUDES_PATH') ?: ((preg_match('/^https?:\/\//i', getenv('APP_ROOT_PATH') ?: '') ? dirname(__DIR__) : (getenv('APP_ROOT_PATH') ?: dirname(__DIR__))) . '/includes')) . '/auth.php';
require_once app_path('includes/layout.php');
require_once app_path('includes/remote_statements.php');
$user = require_login();

if (($user['account_type'] ?? 'internal') === 'third_party') {
    $remoteStatement = $user['third_party_type'] === 'client' ? remote_customer_statement((string)($user['internal_number'] ?? ''), isset($user['branch_id']) ? (int)$user['branch_id'] : null) : ['enabled' => false, 'error' => null, 'movements' => []];
    $statements = [];
    if (!$remoteStatement['enabled']) {
        $stmt = db()->prepare('SELECT statement_date, concept, debit, credit, balance FROM account_statements WHERE third_party_id = ? ORDER BY statement_date DESC, id DESC LIMIT 20');
        $stmt->execute([$user['third_party_id']]);
        $statements = $stmt->fetchAll();
    }
    render_header('Mi estado de cuenta', $user);
    ?>
    <section class="hero">
        <div>
            <h1>Bienvenido, <?= e($user['name']) ?></h1>
            <p class="muted">Dashboard personalizado para <?= $user['third_party_type'] === 'client' ? 'cliente' : 'proveedor' ?>.</p>
            <?php if (!empty($user['internal_number'])): ?><p><strong>Número interno:</strong> <?= e($user['internal_number']) ?></p><?php endif; ?>
        </div>
        <div class="card">
            <?php if (!empty($user['logo_path'])): ?><div class="logo-preview"><img src="<?= e($user['logo_path']) ?>" alt="Logo"></div><?php endif; ?>
            <h2>Acceso al portal</h2>
            <p class="muted"><?= e($user['email']) ?></p>
        </div>
    </section>
    <section class="statement-card" style="margin-top:24px">
        <div class="statement-actions no-print"><h2>Últimos movimientos</h2><div class="actions"><button type="button" onclick="window.print()">Imprimir estado</button><a class="btn secondary" href="/export_statement.php">Descargar Excel</a></div></div>
        <div class="print-header"><h2>Estado de cuenta</h2><p><?= e($user['name']) ?><?php if (!empty($user['internal_number'])): ?> · <?= e($user['internal_number']) ?><?php endif; ?></p><p class="muted">Generado el <?= e(date('d/m/Y H:i')) ?></p></div>
        <?php if (!empty($remoteStatement['error'])): ?><div class="alert error"><?= e($remoteStatement['error']) ?></div><?php endif; ?>
        <?php if ($remoteStatement['enabled']): ?>
            <?php $remoteCustomer = $remoteStatement['customer']; ?>
            <?php if ($remoteCustomer): ?>
                <div class="card" style="margin-bottom:18px"><strong>Saldo remoto:</strong> $<?= e(number_format((float)($remoteCustomer['saldo1'] ?? 0), 2)) ?> · <span class="muted">Desde <?= e(date('d/m/Y', strtotime($remoteStatement['from']))) ?> hasta <?= e(date('d/m/Y', strtotime($remoteStatement['to']))) ?></span></div>
            <?php endif; ?>
            <table><thead><tr><th>Documento</th><th>Tipo</th><th>Fecha</th><th>Vence</th><th>Cargos</th><th>Abonos</th><th>Saldo</th><th>Observaciones</th></tr></thead><tbody>
            <?php foreach ($remoteStatement['movements'] as $row): ?><tr><td><?php if (($row['type_label'] ?? '') === 'Venta'): ?><a href="/sale_detail.php?doc=<?= e(urlencode((string)$row['id'])) ?>"><?= e($row['document_label']) ?></a><?php else: ?><?= e($row['document_label']) ?><?php endif; ?></td><td><?= e($row['type_label']) ?></td><td><?= e($row['fecha']) ?></td><td><?= e($row['due_date']) ?></td><td><?= e(number_format((float)$row['cargos'], 2)) ?></td><td><?= e(number_format((float)$row['abonos'], 2)) ?></td><td><strong>$<?= e(number_format((float)$row['render_balance'], 2)) ?></strong></td><td><em><?= e(substr((string)$row['obs'], 0, 50)) ?></em></td></tr><?php endforeach; ?>
            <?php if (!$remoteStatement['movements']): ?><tr><td colspan="8" class="muted">Sin resultados de movimientos en el periodo seleccionado.</td></tr><?php endif; ?>
            </tbody></table>
        <?php else: ?>
            <table><thead><tr><th>Fecha</th><th>Concepto</th><th>Cargo</th><th>Abono</th><th>Saldo</th></tr></thead><tbody>
            <?php foreach ($statements as $row): ?><tr><td><?= e($row['statement_date']) ?></td><td><?= e($row['concept']) ?></td><td><?= e(number_format((float)$row['debit'], 2)) ?></td><td><?= e(number_format((float)$row['credit'], 2)) ?></td><td><?= e(number_format((float)$row['balance'], 2)) ?></td></tr><?php endforeach; ?>
            <?php if (!$statements): ?><tr><td colspan="5" class="muted">Aún no hay movimientos para mostrar.</td></tr><?php endif; ?>
            </tbody></table>
        <?php endif; ?>
    </section>
    <?php
    render_footer();
    exit;
}

render_header('Panel principal', $user);
?>
<section class="hero">
    <div>
        <h1>Estados de cuenta para clientes y proveedores</h1>
        <p class="muted">Administra terceros, usuarios internos y permisos por módulos desde una base segura en PHP y MySQL.</p>
        <div class="actions">
            <a class="btn" href="/admin/clients.php">Alta de clientes</a>
            <a class="btn secondary" href="/admin/providers.php">Alta de proveedores</a>
        </div>
    </div>
    <div class="card">
        <h2>Sesión activa</h2>
        <p><strong><?= e($user['name']) ?></strong></p>
        <p class="muted"><?= e($user['email']) ?> · <?= e($user['role_name']) ?></p>
    </div>
</section>
<section class="grid" style="margin-top:24px">
    <div class="card stat"><h2>Clientes</h2><p class="muted">Registro y control de acceso al estado de cuenta.</p></div>
    <div class="card stat"><h2>Proveedores</h2><p class="muted">Portal para saldos, movimientos y documentación.</p></div>
    <div class="card stat"><h2>Permisos</h2><p class="muted">Roles internos por nivel y permisos granulares.</p></div>
</section>
<?php render_footer(); ?>
