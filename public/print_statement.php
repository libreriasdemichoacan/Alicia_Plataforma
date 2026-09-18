<?php
require_once (getenv('APP_INCLUDES_PATH') ?: ((preg_match('/^https?:\/\//i', getenv('APP_ROOT_PATH') ?: '') ? dirname(__DIR__) : (getenv('APP_ROOT_PATH') ?: dirname(__DIR__))) . '/includes')) . '/auth.php';
require_once app_path('includes/remote_statements.php');
require_once app_path('includes/settings.php');
require_once app_path('includes/security.php');

$user = require_login();
if (($user['account_type'] ?? 'internal') !== 'third_party' || ($user['third_party_type'] ?? '') !== 'client') {
    http_response_code(403);
    exit('No autorizado.');
}

$stmt = db()->prepare('SELECT legal_name, internal_number, tax_id, email, phone, logo_path FROM third_parties WHERE id = ? AND type = "client" LIMIT 1');
$stmt->execute([(int)$user['third_party_id']]);
$client = $stmt->fetch() ?: [];

$remoteStatement = remote_customer_statement(
    (string)($user['internal_number'] ?? ''),
    isset($user['branch_id']) ? (int)$user['branch_id'] : null
);
$statements = [];
if (!$remoteStatement['enabled']) {
    $stmt = db()->prepare('SELECT statement_date, concept, debit, credit, balance FROM account_statements WHERE third_party_id = ? ORDER BY statement_date DESC, id DESC LIMIT 1000');
    $stmt->execute([(int)$user['third_party_id']]);
    $statements = $stmt->fetchAll();
}

$appLogo = app_logo_path();
$clientLogo = (string)($client['logo_path'] ?? $user['logo_path'] ?? '');
$generatedAt = date('d/m/Y H:i');
$remoteCustomer = $remoteStatement['customer'] ?? null;
$periodFrom = $remoteStatement['from'] ?? null;
$periodTo = $remoteStatement['to'] ?? null;

log_portal_activity($user, 'report.print', 'statement', 'Estado de cuenta', 'Generación de estado de cuenta para PDF');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Estado de cuenta | <?= e($client['legal_name'] ?? $user['name']) ?></title>
    <style>
        :root{--navy:#172554;--blue:#2563eb;--slate:#64748b;--line:#dbe4f0;--soft:#f4f7fb;--white:#fff}
        *{box-sizing:border-box}
        body{margin:0;background:#e9eef5;color:#172033;font-family:Inter,"Segoe UI",Arial,sans-serif}
        .toolbar{position:sticky;top:0;z-index:2;display:flex;justify-content:flex-end;gap:10px;padding:14px max(20px,calc((100vw - 1080px)/2));background:rgba(23,37,84,.94);backdrop-filter:blur(8px)}
        .toolbar button,.toolbar a{border:0;border-radius:10px;padding:11px 17px;font:700 14px inherit;text-decoration:none;cursor:pointer}
        .toolbar button{background:var(--blue);color:#fff}.toolbar a{background:#fff;color:var(--navy)}
        .sheet{width:min(1080px,calc(100% - 32px));margin:28px auto;background:var(--white);box-shadow:0 22px 60px rgba(15,23,42,.16)}
        .accent{height:8px;background:linear-gradient(90deg,var(--navy),var(--blue),#60a5fa)}
        .content{padding:34px 38px 28px}
        .header{display:grid;grid-template-columns:minmax(150px,220px) 1fr auto;gap:26px;align-items:center;padding-bottom:26px;border-bottom:1px solid var(--line)}
        .logo-box{display:flex;align-items:center;min-height:80px}.logo-box img{max-width:200px;max-height:82px;object-fit:contain}
        .title-block h1{margin:0;color:var(--navy);font-size:31px;letter-spacing:-.03em}.title-block p{margin:7px 0 0;color:var(--slate)}
        .folio{text-align:right}.folio strong{display:block;color:var(--navy);font-size:13px;text-transform:uppercase;letter-spacing:.08em}.folio span{display:block;margin-top:7px;color:var(--slate);font-size:13px}
        .client-header{display:flex;align-items:center;gap:18px;margin:26px 0 18px}.client-logo{width:72px;height:72px;display:flex;align-items:center;justify-content:center;border:1px solid var(--line);border-radius:16px;background:var(--soft);overflow:hidden}.client-logo img{max-width:62px;max-height:62px;object-fit:contain}.client-header h2{margin:0;color:var(--navy);font-size:22px}.client-header p{margin:5px 0 0;color:var(--slate)}
        .details{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:24px}.detail{padding:14px 16px;border:1px solid var(--line);border-radius:13px;background:var(--soft)}.detail span{display:block;color:var(--slate);font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.06em}.detail strong{display:block;margin-top:6px;color:var(--navy);font-size:14px;overflow-wrap:anywhere}
        .summary{display:flex;justify-content:space-between;align-items:center;gap:20px;margin-bottom:18px;padding:17px 20px;border-radius:14px;background:var(--navy);color:#fff}.summary p{margin:0}.summary .balance{text-align:right}.summary .balance span{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.08em;opacity:.78}.summary .balance strong{display:block;margin-top:4px;font-size:24px}
        .notice{margin:0 0 18px;padding:13px 15px;border-radius:10px;background:#fff1f2;color:#9f1239}
        .table-wrap{overflow:hidden;border:1px solid var(--line);border-radius:13px}table{width:100%;border-collapse:collapse;font-size:11px}thead{display:table-header-group}th{padding:11px 9px;background:#eaf1fb;color:var(--navy);text-align:left;text-transform:uppercase;letter-spacing:.04em;font-size:9px}td{padding:10px 9px;border-top:1px solid var(--line);vertical-align:top}tbody tr:nth-child(even){background:#f8fafc}.money{text-align:right;white-space:nowrap}.document{font-weight:800;color:var(--navy)}
        .footer{display:flex;justify-content:space-between;gap:20px;margin-top:22px;padding-top:16px;border-top:1px solid var(--line);color:var(--slate);font-size:10px}.footer p{margin:0}
        @page{size:A4 landscape;margin:10mm}
        @media print{body{background:#fff;-webkit-print-color-adjust:exact;print-color-adjust:exact}.toolbar{display:none}.sheet{width:100%;margin:0;box-shadow:none}.content{padding:18px 20px 12px}.header{padding-bottom:18px}.client-header{margin:18px 0 12px}.details{margin-bottom:15px}.summary{margin-bottom:12px}tr{break-inside:avoid}.footer{position:relative}}
        @media(max-width:760px){.sheet{width:100%;margin:0}.content{padding:24px 18px}.header{grid-template-columns:1fr}.folio{text-align:left}.details{grid-template-columns:1fr 1fr}.summary{align-items:flex-start;flex-direction:column}.summary .balance{text-align:left}.table-wrap{overflow:auto}}
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="/index.php">Volver al dashboard</a>
        <button type="button" onclick="window.print()">Guardar como PDF</button>
    </div>
    <article class="sheet">
        <div class="accent"></div>
        <div class="content">
            <header class="header">
                <div class="logo-box"><?php if ($appLogo): ?><img src="<?= e($appLogo) ?>" alt="Logo de la empresa"><?php endif; ?></div>
                <div class="title-block"><h1>Estado de cuenta</h1><p><?= e(app_config()['app_name']) ?> · Documento informativo</p></div>
                <div class="folio"><strong>Fecha de emisión</strong><span><?= e($generatedAt) ?></span></div>
            </header>

            <section class="client-header">
                <?php if ($clientLogo): ?><div class="client-logo"><img src="<?= e($clientLogo) ?>" alt="Logo del cliente"></div><?php endif; ?>
                <div><h2><?= e($client['legal_name'] ?? $user['name']) ?></h2><p>Información general del cliente</p></div>
            </section>
            <section class="details">
                <div class="detail"><span>Número interno</span><strong><?= e(($client['internal_number'] ?? '') ?: '—') ?></strong></div>
                <div class="detail"><span>RFC / ID fiscal</span><strong><?= e(($client['tax_id'] ?? '') ?: '—') ?></strong></div>
                <div class="detail"><span>Correo</span><strong><?= e(($client['email'] ?? '') ?: $user['email']) ?></strong></div>
                <div class="detail"><span>Teléfono</span><strong><?= e(($client['phone'] ?? '') ?: '—') ?></strong></div>
            </section>

            <section class="summary">
                <p><?php if ($periodFrom && $periodTo): ?>Periodo: <?= e(date('d/m/Y', strtotime($periodFrom))) ?> al <?= e(date('d/m/Y', strtotime($periodTo))) ?><?php else: ?>Movimientos recientes del estado de cuenta<?php endif; ?></p>
                <div class="balance"><span>Saldo actual</span><strong>$<?= e(number_format((float)($remoteCustomer['saldo1'] ?? ($statements[0]['balance'] ?? 0)), 2)) ?></strong></div>
            </section>

            <?php if (!empty($remoteStatement['error'])): ?><p class="notice"><?= e($remoteStatement['error']) ?></p><?php endif; ?>
            <div class="table-wrap">
                <?php if ($remoteStatement['enabled']): ?>
                <table><thead><tr><th>Documento</th><th>Tipo</th><th>Fecha</th><th>Vence</th><th class="money">Cargos</th><th class="money">Abonos</th><th class="money">Saldo</th><th>Observaciones</th></tr></thead><tbody>
                    <?php foreach ($remoteStatement['movements'] as $row): ?><tr><td class="document"><?= e($row['document_label']) ?></td><td><?= e($row['type_label']) ?></td><td><?= e($row['fecha']) ?></td><td><?= e($row['due_date']) ?></td><td class="money">$<?= e(number_format((float)$row['cargos'], 2)) ?></td><td class="money">$<?= e(number_format((float)$row['abonos'], 2)) ?></td><td class="money"><strong>$<?= e(number_format((float)$row['render_balance'], 2)) ?></strong></td><td><?= e(substr((string)$row['obs'], 0, 70)) ?></td></tr><?php endforeach; ?>
                    <?php if (!$remoteStatement['movements']): ?><tr><td colspan="8">No hay movimientos para mostrar.</td></tr><?php endif; ?>
                </tbody></table>
                <?php else: ?>
                <table><thead><tr><th>Fecha</th><th>Concepto</th><th class="money">Cargo</th><th class="money">Abono</th><th class="money">Saldo</th></tr></thead><tbody>
                    <?php foreach ($statements as $row): ?><tr><td><?= e($row['statement_date']) ?></td><td><?= e($row['concept']) ?></td><td class="money">$<?= e(number_format((float)$row['debit'], 2)) ?></td><td class="money">$<?= e(number_format((float)$row['credit'], 2)) ?></td><td class="money"><strong>$<?= e(number_format((float)$row['balance'], 2)) ?></strong></td></tr><?php endforeach; ?>
                    <?php if (!$statements): ?><tr><td colspan="5">No hay movimientos para mostrar.</td></tr><?php endif; ?>
                </tbody></table>
                <?php endif; ?>
            </div>
            <footer class="footer"><p>Este documento fue generado desde el portal de <?= e(app_config()['app_name']) ?>.</p><p>Generado: <?= e($generatedAt) ?></p></footer>
        </div>
    </article>
</body>
</html>
