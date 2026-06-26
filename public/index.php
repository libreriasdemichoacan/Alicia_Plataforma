<?php
require_once (getenv('APP_INCLUDES_PATH') ?: ((preg_match('/^https?:\/\//i', getenv('APP_ROOT_PATH') ?: '') ? dirname(__DIR__) : (getenv('APP_ROOT_PATH') ?: dirname(__DIR__))) . '/includes')) . '/auth.php';
require_once app_path('includes/layout.php');
require_once app_path('includes/remote_statements.php');
$user = require_login();

if (($user['account_type'] ?? 'internal') === 'third_party') {
    $isClient = ($user['third_party_type'] ?? '') === 'client';
    $isProvider = ($user['third_party_type'] ?? '') === 'provider';
    $articleReportRequested = isset($_GET['report_from']) || isset($_GET['report_to']);
    $reportFrom = normalize_report_date($_GET['report_from'] ?? null, date('Y-m-01'));
    $reportTo = normalize_report_date($_GET['report_to'] ?? null, date('Y-m-d'));
    if ($reportFrom > $reportTo) {
        [$reportFrom, $reportTo] = [$reportTo, $reportFrom];
    }
    $remoteStatement = $isClient ? remote_customer_statement((string)($user['internal_number'] ?? ''), isset($user['branch_id']) ? (int)$user['branch_id'] : null) : ['enabled' => false, 'error' => null, 'movements' => []];
    $articleReport = $isClient ? remote_article_sales_report((string)($user['internal_number'] ?? ''), $reportFrom, $reportTo, isset($user['branch_id']) ? (int)$user['branch_id'] : null) : ['enabled' => false, 'error' => null, 'rows' => [], 'totals' => ['sale' => 0, 'return' => 0, 'net' => 0], 'from' => $reportFrom, 'to' => $reportTo];
    $stockBranches = $isProvider ? array_values(array_filter(report_branches(), fn(array $branch): bool => ($branch['status'] ?? '') === 'active')) : [];
    $requestedStockBranchIds = $_GET['stock_branch_ids'] ?? ($_GET['stock_branch_id'] ?? []);
    if (!is_array($requestedStockBranchIds)) {
        $requestedStockBranchIds = [$requestedStockBranchIds];
    }
    $selectedStockBranchIds = array_values(array_unique(array_filter(array_map('intval', $requestedStockBranchIds))));
    if (!$selectedStockBranchIds && $stockBranches) {
        $selectedStockBranchIds = [(int)$stockBranches[0]['id']];
    }
    $providerStock = $isProvider ? remote_provider_stock_for_branches((string)($user['internal_number'] ?? ''), $selectedStockBranchIds) : ['enabled' => false, 'error' => null, 'rows' => [], 'branches' => []];
    $stockExportQuery = http_build_query(['stock_branch_ids' => $selectedStockBranchIds]);
    $providerSalesRequested = isset($_GET['sales_branch_id']) || isset($_GET['sales_from']) || isset($_GET['sales_to']);
    $salesFrom = normalize_report_date($_GET['sales_from'] ?? null, date('Y-m-01'));
    $salesTo = normalize_report_date($_GET['sales_to'] ?? null, date('Y-m-d'));
    if ($salesFrom > $salesTo) {
        [$salesFrom, $salesTo] = [$salesTo, $salesFrom];
    }
    $selectedSalesBranchId = (int)($_GET['sales_branch_id'] ?? ($stockBranches[0]['id'] ?? 0));
    $providerDetailedSales = $isProvider ? remote_provider_detailed_sales((string)($user['internal_number'] ?? ''), $selectedSalesBranchId, $salesFrom, $salesTo) : ['enabled' => false, 'error' => null, 'rows' => [], 'from' => $salesFrom, 'to' => $salesTo, 'branch' => null];
    $salesExportQuery = http_build_query(['sales_branch_id' => $selectedSalesBranchId, 'sales_from' => $salesFrom, 'sales_to' => $salesTo]);
    $statements = [];
    if ($isClient && !$remoteStatement['enabled']) {
        $stmt = db()->prepare('SELECT statement_date, concept, debit, credit, balance FROM account_statements WHERE third_party_id = ? ORDER BY statement_date DESC, id DESC LIMIT 20');
        $stmt->execute([$user['third_party_id']]);
        $statements = $stmt->fetchAll();
    }
    log_portal_activity($user, 'access.dashboard', 'dashboard', null, 'Acceso al dashboard del portal');
    if ($isClient && $articleReportRequested) {
        log_portal_activity($user, 'report.view', 'client_article_report', 'Reporte detalle por artículo', 'Consulta de reporte detalle por artículo', ['from' => $reportFrom, 'to' => $reportTo]);
    }
    if ($isProvider && isset($_GET['stock_branch_ids'])) {
        log_portal_activity($user, 'report.view', 'provider_stock', 'Stock por sucursal', 'Consulta de stock por sucursal', ['branch_ids' => $selectedStockBranchIds]);
    }
    if ($isProvider && $providerSalesRequested) {
        log_portal_activity($user, 'report.view', 'provider_detailed_sales', 'Venta detallada', 'Consulta de venta detallada', ['branch_id' => $selectedSalesBranchId, 'from' => $salesFrom, 'to' => $salesTo]);
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

    <?php if ($isProvider): ?>
    <section class="dashboard-tabs no-print" aria-label="Secciones del dashboard de proveedor">
        <button type="button" class="tab-button <?= $providerSalesRequested ? '' : 'is-active' ?>" data-tab-target="provider-stock-panel" aria-controls="provider-stock-panel" aria-selected="<?= $providerSalesRequested ? 'false' : 'true' ?>">Stock por sucursal</button>
        <button type="button" class="tab-button <?= $providerSalesRequested ? 'is-active' : '' ?>" data-tab-target="provider-sales-panel" aria-controls="provider-sales-panel" aria-selected="<?= $providerSalesRequested ? 'true' : 'false' ?>">Venta detallada</button>
    </section>
    <div class="tab-panels">
        <div id="provider-stock-panel" class="tab-panel <?= $providerSalesRequested ? '' : 'is-active' ?>" <?= $providerSalesRequested ? 'hidden' : '' ?>>
            <section class="statement-card" style="margin-top:24px">
                <div class="statement-actions no-print">
                    <div>
                        <h2>Stock por sucursal</h2>
                        <p class="muted">Consulta el stock disponible de las editoriales asociadas a tu código interno.</p>
                    </div>
                    <form class="report-filters" method="get">
                        <label>Sucursales
                            <select name="stock_branch_ids[]" multiple size="<?= min(max(count($stockBranches), 2), 6) ?>" required>
                                <?php foreach ($stockBranches as $branch): ?>
                                    <option value="<?= (int)$branch['id'] ?>" <?= in_array((int)$branch['id'], $selectedStockBranchIds, true) ? 'selected' : '' ?>><?= e($branch['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="muted">Puedes seleccionar varias sucursales.</small>
                        </label>
                        <button type="submit">Consultar stock</button>
                        <a class="btn secondary" href="/export_provider_stock.php?<?= e($stockExportQuery) ?>">Descargar Excel</a>
                    </form>
                </div>
                <?php if (!empty($providerStock['error'])): ?><div class="alert error"><?= e($providerStock['error']) ?></div><?php endif; ?>
                <?php if (!$stockBranches): ?>
                    <p class="muted">No hay sucursales activas configuradas para consultar stock.</p>
                <?php elseif ($providerStock['enabled']): ?>
                    <?php if (!empty($providerStock['branches'])): ?><p class="muted">Concentrado de: <?= e(implode(', ', array_column($providerStock['branches'], 'name'))) ?></p><?php endif; ?>
                    <div class="table-responsive" role="region" aria-label="Stock por sucursal" tabindex="0">
                        <table class="data-table"><thead><tr><th>Código</th><th>Título</th><th>Autor</th><th>Editorial</th><th>Precio</th><th>Stock</th><th>Sucursales</th></tr></thead><tbody>
                        <?php foreach ($providerStock['rows'] as $row): ?><tr><td data-label="Código"><?= e($row['codbar']) ?></td><td data-label="Título"><strong><?= e($row['titulo']) ?></strong></td><td data-label="Autor"><?= e($row['autor']) ?></td><td data-label="Editorial"><?= e($row['editorial']) ?></td><td data-label="Precio" class="text-money">$<?= e(number_format((float)$row['precio'], 2)) ?></td><td data-label="Stock" class="text-money"><strong><?= e(number_format((float)$row['cantidad'], 0)) ?></strong></td><td data-label="Sucursales"><?= e($row['branch_names'] ?? '') ?></td></tr><?php endforeach; ?>
                        <?php if (!$providerStock['rows']): ?><tr><td colspan="7" class="muted">Sin stock disponible para este proveedor en la sucursal seleccionada.</td></tr><?php endif; ?>
                        </tbody></table>
                    </div>
                <?php endif; ?>
            </section>
        </div>
        <div id="provider-sales-panel" class="tab-panel <?= $providerSalesRequested ? 'is-active' : '' ?>" <?= $providerSalesRequested ? '' : 'hidden' ?>>
            <section class="statement-card" style="margin-top:24px">
                <div class="statement-actions no-print">
                    <div>
                        <h2>Venta detallada</h2>
                        <p class="muted">Consulta venta neta por artículo para una sucursal y rango de fechas.</p>
                    </div>
                    <form class="report-filters" method="get">
                        <label>Sucursal
                            <select name="sales_branch_id" required>
                                <?php foreach ($stockBranches as $branch): ?>
                                    <option value="<?= (int)$branch['id'] ?>" <?= (int)$selectedSalesBranchId === (int)$branch['id'] ? 'selected' : '' ?>><?= e($branch['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Desde <input type="date" name="sales_from" value="<?= e($providerDetailedSales['from']) ?>"></label>
                        <label>Hasta <input type="date" name="sales_to" value="<?= e($providerDetailedSales['to']) ?>"></label>
                        <button type="submit">Consultar venta</button>
                        <a class="btn secondary" href="/export_provider_sales.php?<?= e($salesExportQuery) ?>">Exportar a Excel</a>
                    </form>
                </div>
                <?php if (!empty($providerDetailedSales['error'])): ?><div class="alert error"><?= e($providerDetailedSales['error']) ?></div><?php endif; ?>
                <?php if (!$stockBranches): ?>
                    <p class="muted">No hay sucursales activas configuradas para consultar venta detallada.</p>
                <?php elseif ($providerDetailedSales['enabled']): ?>
                    <?php if (!empty($providerDetailedSales['branch'])): ?><p class="muted">Sucursal: <?= e($providerDetailedSales['branch']['name']) ?> · Desde <?= e($providerDetailedSales['from']) ?> hasta <?= e($providerDetailedSales['to']) ?></p><?php endif; ?>
                    <div class="table-responsive" role="region" aria-label="Venta detallada" tabindex="0">
                        <table class="data-table"><thead><tr><th>Código</th><th>Título</th><th>Autor</th><th>Editorial</th><th>Precio</th><th>Stock</th><th>Venta neta</th></tr></thead><tbody>
                        <?php foreach ($providerDetailedSales['rows'] as $row): ?><tr><td data-label="Código"><?= e($row['codbar']) ?></td><td data-label="Título"><strong><?= e($row['titulo']) ?></strong></td><td data-label="Autor"><?= e($row['autor']) ?></td><td data-label="Editorial"><?= e($row['editorial']) ?></td><td data-label="Precio" class="text-money">$<?= e(number_format((float)$row['precio'], 2)) ?></td><td data-label="Stock" class="text-money"><?= e(number_format((float)$row['stock'], 0)) ?></td><td data-label="Venta neta" class="text-money"><strong><?= e(number_format((float)$row['venta_neta'], 0)) ?></strong></td></tr><?php endforeach; ?>
                        <?php if (!$providerDetailedSales['rows']): ?><tr><td colspan="7" class="muted">Sin venta detallada para este proveedor en el rango seleccionado.</td></tr><?php endif; ?>
                        </tbody></table>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>
    <script>
        document.querySelectorAll('[data-tab-target]').forEach((button) => {
            button.addEventListener('click', () => {
                const targetId = button.dataset.tabTarget;
                document.querySelectorAll('[data-tab-target]').forEach((tab) => {
                    const isActive = tab === button;
                    tab.classList.toggle('is-active', isActive);
                    tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
                });
                document.querySelectorAll('.tab-panel').forEach((panel) => {
                    const isActive = panel.id === targetId;
                    panel.classList.toggle('is-active', isActive);
                    panel.hidden = !isActive;
                });
            });
        });
    </script>
    <?php render_footer(); exit; endif; ?>
    <?php if ($user['third_party_type'] === 'client'): ?>
    <section class="dashboard-tabs no-print" aria-label="Secciones del dashboard">
        <button type="button" class="tab-button <?= $articleReportRequested ? '' : 'is-active' ?>" data-tab-target="movements-panel" aria-controls="movements-panel" aria-selected="<?= $articleReportRequested ? 'false' : 'true' ?>">Últimos movimientos</button>
        <button type="button" class="tab-button <?= $articleReportRequested ? 'is-active' : '' ?>" data-tab-target="article-report-panel" aria-controls="article-report-panel" aria-selected="<?= $articleReportRequested ? 'true' : 'false' ?>">Reporte detalle por artículo</button>
    </section>
    <div class="tab-panels">
        <div id="movements-panel" class="tab-panel <?= $articleReportRequested ? '' : 'is-active' ?>" <?= $articleReportRequested ? 'hidden' : '' ?>>
    <?php endif; ?>
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

    <?php if ($user['third_party_type'] === 'client'): ?>
        </div>
        <div id="article-report-panel" class="tab-panel <?= $articleReportRequested ? 'is-active' : '' ?>" <?= $articleReportRequested ? '' : 'hidden' ?>>
    <section class="statement-card" style="margin-top:24px">
        <div class="statement-actions no-print">
            <div>
                <h2>Reporte detalle por artículo</h2>
                <p class="muted">Ventas menos devoluciones por artículo, consultado desde la sucursal remota.</p>
            </div>
            <form class="report-filters" method="get">
                <label>Desde <input type="date" name="report_from" value="<?= e($articleReport['from']) ?>"></label>
                <label>Hasta <input type="date" name="report_to" value="<?= e($articleReport['to']) ?>"></label>
                <button type="submit">Consultar</button>
                <a class="btn secondary" href="/export_article_report.php?from=<?= e(urlencode($articleReport['from'])) ?>&to=<?= e(urlencode($articleReport['to'])) ?>">Exportar a Excel</a>
            </form>
        </div>
        <?php if (!empty($articleReport['error'])): ?><div class="alert error"><?= e($articleReport['error']) ?></div><?php endif; ?>
        <?php if ($articleReport['enabled']): ?>
            <div class="table-responsive" role="region" aria-label="Reporte detalle por artículo" tabindex="0">
                <table class="data-table"><thead><tr><th>Código</th><th>Artículo</th><th>Autor</th><th>Venta</th><th>Devolución</th><th>Venta neta</th></tr></thead><tbody>
                <?php foreach ($articleReport['rows'] as $row): ?><tr><td data-label="Código"><?= e($row['codbar'] ?: $row['libro']) ?></td><td data-label="Artículo"><strong><?= e($row['titulo']) ?></strong></td><td data-label="Autor"><?= e($row['autor']) ?></td><td data-label="Venta" class="text-money"><?= e(number_format((float)$row['sale_quantity'], 0)) ?></td><td data-label="Devolución" class="text-money"><?= e(number_format((float)$row['return_quantity'], 0)) ?></td><td data-label="Venta neta" class="text-money"><strong><?= e(number_format((float)$row['net_quantity'], 0)) ?></strong></td></tr><?php endforeach; ?>
                <?php if (!$articleReport['rows']): ?><tr><td colspan="6" class="muted">Sin ventas o devoluciones por artículo en el rango seleccionado.</td></tr><?php endif; ?>
                <?php if ($articleReport['rows']): ?><tr><td colspan="3"><strong>Totales</strong></td><td class="text-money"><strong><?= e(number_format((float)$articleReport['totals']['sale'], 0)) ?></strong></td><td class="text-money"><strong><?= e(number_format((float)$articleReport['totals']['return'], 0)) ?></strong></td><td class="text-money"><strong><?= e(number_format((float)$articleReport['totals']['net'], 0)) ?></strong></td></tr><?php endif; ?>
                </tbody></table>
            </div>
        <?php else: ?>
            <p class="muted">El reporte detalle requiere una sucursal remota asignada o la conexión remota global activa.</p>
        <?php endif; ?>
    </section>
        </div>
    </div>
    <script>
        document.querySelectorAll('[data-tab-target]').forEach((button) => {
            button.addEventListener('click', () => {
                const targetId = button.dataset.tabTarget;
                document.querySelectorAll('[data-tab-target]').forEach((tab) => {
                    const isActive = tab === button;
                    tab.classList.toggle('is-active', isActive);
                    tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
                });
                document.querySelectorAll('.tab-panel').forEach((panel) => {
                    const isActive = panel.id === targetId;
                    panel.classList.toggle('is-active', isActive);
                    panel.hidden = !isActive;
                });
            });
        });
    </script>
    <?php endif; ?>
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
