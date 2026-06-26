<?php
require_once (getenv('APP_INCLUDES_PATH') ?: ((preg_match('/^https?:\/\//i', getenv('APP_ROOT_PATH') ?: '') ? dirname(__DIR__) : (getenv('APP_ROOT_PATH') ?: dirname(__DIR__))) . '/includes')) . '/auth.php';
require_once app_path('includes/remote_statements.php');
require_once app_path('includes/security.php');

$user = require_login();
if (($user['account_type'] ?? 'internal') !== 'third_party' || ($user['third_party_type'] ?? '') !== 'provider') {
    http_response_code(403);
    exit('No autorizado.');
}

$branchIds = $_GET['stock_branch_ids'] ?? ($_GET['stock_branch_id'] ?? []);
if (!is_array($branchIds)) {
    $branchIds = [$branchIds];
}
$branchIds = array_values(array_unique(array_filter(array_map('intval', $branchIds))));
log_portal_activity($user, 'export.excel', 'provider_stock', 'Stock por sucursal', 'Exportación Excel de stock por sucursal', ['branch_ids' => $branchIds]);
$report = remote_provider_stock_for_branches((string)($user['internal_number'] ?? ''), $branchIds);

$filename = 'stock_sucursales_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $user['internal_number'] ?: $user['third_party_id']) . '_' . date('Ymd') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');
echo "\xEF\xBB\xBF";
?>
<table border="1">
    <tr><th colspan="7">Stock por sucursal</th></tr>
    <tr><td>Proveedor</td><td colspan="6"><?= e($user['name']) ?></td></tr>
    <tr><td>Número interno</td><td colspan="6"><?= e($user['internal_number'] ?? '') ?></td></tr>
    <tr><td>Sucursales</td><td colspan="6"><?= e(implode(', ', array_column($report['branches'] ?? [], 'name'))) ?></td></tr>
    <?php if (!empty($report['error'])): ?><tr><td colspan="7"><?= e($report['error']) ?></td></tr><?php endif; ?>
    <tr><th>Código</th><th>Título</th><th>Autor</th><th>Editorial</th><th>Precio</th><th>Stock</th><th>Sucursales</th></tr>
    <?php foreach ($report['rows'] as $row): ?>
        <tr><td><?= e($row['codbar']) ?></td><td><?= e($row['titulo']) ?></td><td><?= e($row['autor']) ?></td><td><?= e($row['editorial']) ?></td><td><?= e(number_format((float)$row['precio'], 2, '.', '')) ?></td><td><?= e(number_format((float)$row['cantidad'], 0, '.', '')) ?></td><td><?= e($row['branch_names'] ?? '') ?></td></tr>
    <?php endforeach; ?>
</table>
