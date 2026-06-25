<?php
require_once (getenv('APP_INCLUDES_PATH') ?: ((preg_match('/^https?:\/\//i', getenv('APP_ROOT_PATH') ?: '') ? dirname(__DIR__) : (getenv('APP_ROOT_PATH') ?: dirname(__DIR__))) . '/includes')) . '/auth.php';
require_once app_path('includes/remote_statements.php');
require_once app_path('includes/security.php');

$user = require_login();
if (($user['account_type'] ?? 'internal') !== 'third_party' || ($user['third_party_type'] ?? '') !== 'provider') {
    http_response_code(403);
    exit('No autorizado.');
}

$branchId = (int)($_GET['sales_branch_id'] ?? 0);
$from = normalize_report_date($_GET['sales_from'] ?? null, date('Y-m-01'));
$to = normalize_report_date($_GET['sales_to'] ?? null, date('Y-m-d'));
if ($from > $to) {
    [$from, $to] = [$to, $from];
}
$report = remote_provider_detailed_sales((string)($user['internal_number'] ?? ''), $branchId, $from, $to);

$filename = 'venta_detallada_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $user['internal_number'] ?: $user['third_party_id']) . '_' . str_replace('-', '', $from) . '_' . str_replace('-', '', $to) . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');
echo "\xEF\xBB\xBF";
?>
<table border="1">
    <tr><th colspan="7">Venta detallada</th></tr>
    <tr><td>Proveedor</td><td colspan="6"><?= e($user['name']) ?></td></tr>
    <tr><td>Número interno</td><td colspan="6"><?= e($user['internal_number'] ?? '') ?></td></tr>
    <tr><td>Sucursal</td><td colspan="6"><?= e($report['branch']['name'] ?? '') ?></td></tr>
    <tr><td>Desde</td><td colspan="2"><?= e($report['from']) ?></td><td>Hasta</td><td colspan="3"><?= e($report['to']) ?></td></tr>
    <?php if (!empty($report['error'])): ?><tr><td colspan="7"><?= e($report['error']) ?></td></tr><?php endif; ?>
    <tr><th>Código</th><th>Título</th><th>Autor</th><th>Editorial</th><th>Precio</th><th>Stock</th><th>Venta neta</th></tr>
    <?php foreach ($report['rows'] as $row): ?>
        <tr><td><?= e($row['codbar']) ?></td><td><?= e($row['titulo']) ?></td><td><?= e($row['autor']) ?></td><td><?= e($row['editorial']) ?></td><td><?= e(number_format((float)$row['precio'], 2, '.', '')) ?></td><td><?= e(number_format((float)$row['stock'], 0, '.', '')) ?></td><td><?= e(number_format((float)$row['venta_neta'], 0, '.', '')) ?></td></tr>
    <?php endforeach; ?>
</table>
