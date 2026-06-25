<?php
$currentAdminPage = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'clients.php'));
$entityLabel = (isset($rows[0]) && ($rows[0]['type'] ?? '') === 'provider') || $currentAdminPage === 'providers.php' ? 'proveedores' : 'clientes';
$totalRows = count($rows);
?>
<section class="table-card" aria-labelledby="third-party-table-title">
    <div class="table-card__header">
        <div>
            <p class="eyebrow">Directorio</p>
            <h2 id="third-party-table-title"><?= e(ucfirst($entityLabel)) ?> registrados</h2>
        </div>
        <span class="table-count"><?= (int)$totalRows ?> <?= $totalRows === 1 ? 'registro' : 'registros' ?></span>
    </div>
    <div class="table-responsive" role="region" aria-label="Tabla de <?= e($entityLabel) ?>" tabindex="0">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Logo</th>
                    <th>Número interno</th>
                    <th>Sucursal</th>
                    <th>Razón social</th>
                    <th>ID fiscal</th>
                    <th>Correo</th>
                    <th>Teléfono</th>
                    <th>Estatus</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php $editPath = $row['type'] === 'client' ? 'clients' : 'providers'; ?>
                    <tr>
                        <td data-label="Logo">
                            <?php if (!empty($row['logo_path'])): ?>
                                <img class="table-logo" src="<?= e($row['logo_path']) ?>" alt="Logo de <?= e($row['legal_name']) ?>">
                            <?php else: ?>
                                <span class="avatar-placeholder" aria-hidden="true"><?= e(strtoupper(substr((string)$row['legal_name'], 0, 1))) ?></span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Número interno"><?= e($row['internal_number'] ?: '—') ?></td>
                        <td data-label="Sucursal"><?= e($row['branch_name'] ?? 'Global') ?></td>
                        <td data-label="Razón social"><strong><?= e($row['legal_name']) ?></strong></td>
                        <td data-label="ID fiscal"><?= e($row['tax_id'] ?: '—') ?></td>
                        <td data-label="Correo"><?= $row['email'] ? '<a href="mailto:' . e($row['email']) . '">' . e($row['email']) . '</a>' : '<span class="muted">—</span>' ?></td>
                        <td data-label="Teléfono"><?= e($row['phone'] ?: '—') ?></td>
                        <td data-label="Estatus"><span class="badge"><?= e($row['status']) ?></span></td>
                        <td data-label="Acciones"><a class="btn secondary btn-sm" href="/admin/<?= e($editPath) ?>.php?edit=<?= (int)$row['id'] ?>">Editar</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                    <tr class="empty-row"><td colspan="9"><div class="empty-state"><strong>Aún no hay registros.</strong><span class="muted">Cuando captures información, aparecerá aquí.</span></div></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
