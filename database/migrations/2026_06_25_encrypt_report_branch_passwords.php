<?php
require_once dirname(__DIR__, 2) . '/includes/crypto.php';

$stmt = db()->query('SELECT id, db_pass FROM report_branches WHERE db_pass IS NOT NULL AND db_pass <> ""');
$branches = $stmt->fetchAll();
$updated = 0;

foreach ($branches as $branch) {
    if (str_starts_with((string)$branch['db_pass'], ENCRYPTED_BRANCH_PASSWORD_PREFIX)) {
        continue;
    }

    $update = db()->prepare('UPDATE report_branches SET db_pass = ? WHERE id = ?');
    $update->execute([encrypt_branch_password((string)$branch['db_pass']), (int)$branch['id']]);
    $updated++;
}

echo "Contraseñas de sucursales cifradas: {$updated}" . PHP_EOL;
