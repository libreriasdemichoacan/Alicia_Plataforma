<?php
require_once (getenv('APP_INCLUDES_PATH') ?: ((preg_match('/^https?:\/\//i', getenv('APP_ROOT_PATH') ?: '') ? dirname(__DIR__) : (getenv('APP_ROOT_PATH') ?: dirname(__DIR__))) . '/includes')) . '/auth.php';
require_once app_path('includes/layout.php');
require_once app_path('includes/remote_statements.php');

$user = require_login();
if (($user['account_type'] ?? 'internal') !== 'third_party' || ($user['third_party_type'] ?? '') !== 'client') {
    http_response_code(403);
    exit('No autorizado.');
}

$document = $_GET['doc'] ?? '';
log_portal_activity($user, 'report.view', 'sale_detail', 'Detalle de venta', 'Consulta de detalle de venta', ['document' => (string)$document]);
$detail = remote_sale_detail((string)($user['internal_number'] ?? ''), (string)$document, isset($user['branch_id']) ? (int)$user['branch_id'] : null);
$sale = $detail['sale'];
$customer = $detail['customer'];
$items = $detail['items'];
$totals = $detail['totals'] ?? ['subtotal' => 0, 'discount' => 0, 'tax' => 0, 'total' => 0];

render_header('Detalle de documento', $user);
?>
<section class="statement-card sale-detail">
    <div class="statement-actions no-print"><h1>Detalle de venta</h1><div class="actions"><button type="button" onclick="window.print()">Imprimir documento</button><a class="btn secondary" href="/index.php">Volver</a></div></div>
    <?php if ($detail['error']): ?><div class="alert error"><?= e($detail['error']) ?></div><?php endif; ?>
    <?php if ($sale && $customer): ?>
        <div class="print-header"><h2>Documento <?= e($sale['doc']) ?></h2><p><?= e($customer['nombre']) ?> · <?= e($customer['c_cod']) ?></p></div>
        <div class="document-header">
            <div><?php if (!empty($user['logo_path'])): ?><img class="document-logo" src="<?= e($user['logo_path']) ?>" alt="Logo cliente"><?php endif; ?></div>
            <div><h2>DOCUMENTO: <?= e($sale['doc']) ?></h2><p class="muted">Fecha venta: <?= e($sale['fecha']) ?> · Vencimiento: <?= e($sale['vence']) ?></p><p><strong>Cliente:</strong> <?= e($customer['nombre']) ?> (<?= e($customer['c_cod']) ?>)</p></div>
        </div>
        <table><thead><tr><th>Código</th><th>Título</th><th>Autor</th><th>Cant</th><th>Precio</th><th>Desc</th><th>IVA</th><th>Subtotal</th></tr></thead><tbody>
        <?php foreach ($items as $item): ?><tr><td><?= e($item['codbar']) ?></td><td><?= e(substr((string)$item['titulo'], 0, 60)) ?></td><td><?= e(substr((string)$item['autor'], 0, 40)) ?></td><td><?= e(number_format((float)$item['quantity_render'], 0)) ?></td><td class="text-money"><?= e(number_format((float)$item['precio'], 2)) ?></td><td class="text-money"><?= e(number_format((float)$item['descuento'], 1)) ?>%</td><td class="text-money"><?= e(number_format((float)$item['impuesto'], 0)) ?>%</td><td class="text-money"><?= e(number_format((float)$item['line_total'], 2)) ?></td></tr><?php endforeach; ?>
        <?php if (!$items): ?><tr><td colspan="8" class="muted">Sin registros de artículos para esta remisión.</td></tr><?php endif; ?>
        <tr><td colspan="3"><strong>Observaciones:</strong> <?= e($sale['memo']) ?></td><td colspan="4" class="text-money"><strong>Subtotal:</strong></td><td class="text-money"><?= e(number_format((float)$totals['subtotal'], 2)) ?></td></tr>
        <tr><td colspan="7" class="text-money"><strong>Descuento:</strong></td><td class="text-money"><?= e(number_format((float)$totals['discount'], 2)) ?></td></tr>
        <tr><td colspan="7" class="text-money"><strong>IVA:</strong></td><td class="text-money"><?= e(number_format((float)$totals['tax'], 2)) ?></td></tr>
        <tr><td colspan="7" class="text-money"><strong>Total:</strong></td><td class="text-money"><strong>$<?= e(number_format((float)$totals['total'], 2)) ?></strong></td></tr>
        </tbody></table>
    <?php endif; ?>
</section>
<?php render_footer(); ?>
