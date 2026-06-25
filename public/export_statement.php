<?php
require_once (getenv('APP_INCLUDES_PATH') ?: ((preg_match('/^https?:\/\//i', getenv('APP_ROOT_PATH') ?: '') ? dirname(__DIR__) : (getenv('APP_ROOT_PATH') ?: dirname(__DIR__))) . '/includes')) . '/auth.php';
require_once app_path('includes/remote_statements.php');
require_once app_path('includes/security.php');

$user = require_login();
if (($user['account_type'] ?? 'internal') !== 'third_party') {
    http_response_code(403);
    exit('No autorizado.');
}

$filename = 'estado_cuenta_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $user['internal_number'] ?: $user['third_party_id']) . '_' . date('Ymd') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');
echo "\xEF\xBB\xBF";

$remoteStatement = $user['third_party_type'] === 'client' ? remote_customer_statement((string)($user['internal_number'] ?? ''), isset($user['branch_id']) ? (int)$user['branch_id'] : null) : ['enabled' => false, 'error' => null, 'movements' => []];
?>
<table border="1">
    <tr><th colspan="8">Estado de cuenta</th></tr>
    <tr><td>Nombre</td><td colspan="7"><?= e($user['name']) ?></td></tr>
    <tr><td>Número interno</td><td colspan="7"><?= e($user['internal_number'] ?? '') ?></td></tr>
    <tr><td>Fecha de descarga</td><td colspan="7"><?= e(date('Y-m-d H:i:s')) ?></td></tr>
    <?php if ($remoteStatement['enabled']): ?>
        <tr><th>Documento</th><th>Tipo</th><th>Fecha</th><th>Vence</th><th>Cargos</th><th>Abonos</th><th>Saldo</th><th>Observaciones</th></tr>
        <?php foreach ($remoteStatement['movements'] as $row): ?>
            <tr><td><?= e($row['document_label']) ?></td><td><?= e($row['type_label']) ?></td><td><?= e($row['fecha']) ?></td><td><?= e($row['due_date']) ?></td><td><?= e(number_format((float)$row['cargos'], 2, '.', '')) ?></td><td><?= e(number_format((float)$row['abonos'], 2, '.', '')) ?></td><td><?= e(number_format((float)$row['render_balance'], 2, '.', '')) ?></td><td><?= e((string)$row['obs']) ?></td></tr>
        <?php endforeach; ?>
    <?php else: ?>
        <?php $stmt = db()->prepare('SELECT statement_date, concept, debit, credit, balance FROM account_statements WHERE third_party_id = ? ORDER BY statement_date DESC, id DESC LIMIT 1000'); $stmt->execute([$user['third_party_id']]); $statements = $stmt->fetchAll(); ?>
        <tr><th>Fecha</th><th colspan="3">Concepto</th><th>Cargo</th><th>Abono</th><th>Saldo</th><th></th></tr>
        <?php foreach ($statements as $row): ?>
            <tr><td><?= e($row['statement_date']) ?></td><td colspan="3"><?= e($row['concept']) ?></td><td><?= e(number_format((float)$row['debit'], 2, '.', '')) ?></td><td><?= e(number_format((float)$row['credit'], 2, '.', '')) ?></td><td><?= e(number_format((float)$row['balance'], 2, '.', '')) ?></td><td></td></tr>
        <?php endforeach; ?>
    <?php endif; ?>
</table>
