<section style="margin-top:24px">
<table><thead><tr><th>Logo</th><th>Número interno</th><th>Sucursal</th><th>Razón social</th><th>ID fiscal</th><th>Correo</th><th>Teléfono</th><th>Estatus</th><th>Acciones</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr><td><?php if (!empty($row['logo_path'])): ?><img class="table-logo" src="<?= e($row['logo_path']) ?>" alt="Logo"></td><?php else: ?><span class="muted">—</span></td><?php endif; ?><td><?= e($row['internal_number']) ?></td><td><?= e($row['branch_name'] ?? 'Global') ?></td><td><?= e($row['legal_name']) ?></td><td><?= e($row['tax_id']) ?></td><td><?= e($row['email']) ?></td><td><?= e($row['phone']) ?></td><td><span class="badge"><?= e($row['status']) ?></span></td><td><a class="btn secondary" href="/admin/<?= $row['type'] === 'client' ? 'clients' : 'providers' ?>.php?edit=<?= (int)$row['id'] ?>">Editar</a></td></tr><?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="9" class="muted">Aún no hay registros.</td></tr><?php endif; ?>
</tbody></table>
</section>
