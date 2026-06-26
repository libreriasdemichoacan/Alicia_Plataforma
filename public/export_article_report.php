<?php
require_once (getenv('APP_INCLUDES_PATH') ?: ((preg_match('/^https?:\/\//i', getenv('APP_ROOT_PATH') ?: '') ? dirname(__DIR__) : (getenv('APP_ROOT_PATH') ?: dirname(__DIR__))) . '/includes')) . '/auth.php';
require_once app_path('includes/remote_statements.php');
require_once app_path('includes/security.php');

$user = require_login();
if (($user['account_type'] ?? 'internal') !== 'third_party' || ($user['third_party_type'] ?? '') !== 'client') {
    http_response_code(403);
    exit('No autorizado.');
}

$from = normalize_report_date($_GET['from'] ?? null, date('Y-m-01'));
$to = normalize_report_date($_GET['to'] ?? null, date('Y-m-d'));
if ($from > $to) {
    [$from, $to] = [$to, $from];
}
log_portal_activity($user, 'export.excel', 'client_article_report', 'Reporte detalle por artículo', 'Exportación Excel de reporte detalle por artículo', ['from' => $from, 'to' => $to]);
$report = remote_article_sales_report((string)($user['internal_number'] ?? ''), $from, $to, isset($user['branch_id']) ? (int)$user['branch_id'] : null);

$filename = 'reporte_articulos_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $user['internal_number'] ?: $user['third_party_id']) . '_' . str_replace('-', '', $from) . '_' . str_replace('-', '', $to) . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');
echo "\xEF\xBB\xBF";
?>
<table border="1">
    <tr><th colspan="6">Reporte detalle por artículo</th></tr>
    <tr><td>Cliente</td><td colspan="5"><?= e($user['name']) ?></td></tr>
    <tr><td>Número interno</td><td colspan="5"><?= e($user['internal_number'] ?? '') ?></td></tr>
    <tr><td>Desde</td><td colspan="2"><?= e($report['from']) ?></td><td>Hasta</td><td colspan="2"><?= e($report['to']) ?></td></tr>
    <?php if (!empty($report['error'])): ?><tr><td colspan="6"><?= e($report['error']) ?></td></tr><?php endif; ?>
    <tr><th>Código</th><th>Artículo</th><th>Autor</th><th>Venta</th><th>Devolución</th><th>Venta neta</th></tr>
    <?php foreach ($report['rows'] as $row): ?>
        <tr><td><?= e($row['codbar'] ?: $row['libro']) ?></td><td><?= e($row['titulo']) ?></td><td><?= e($row['autor']) ?></td><td><?= e(number_format((float)$row['sale_quantity'], 0, '.', '')) ?></td><td><?= e(number_format((float)$row['return_quantity'], 0, '.', '')) ?></td><td><?= e(number_format((float)$row['net_quantity'], 0, '.', '')) ?></td></tr>
    <?php endforeach; ?>
    <tr><td colspan="3"><strong>Totales</strong></td><td><strong><?= e(number_format((float)$report['totals']['sale'], 0, '.', '')) ?></strong></td><td><strong><?= e(number_format((float)$report['totals']['return'], 0, '.', '')) ?></strong></td><td><strong><?= e(number_format((float)$report['totals']['net'], 0, '.', '')) ?></strong></td></tr>
</table>
