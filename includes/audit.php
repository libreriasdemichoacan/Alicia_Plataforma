<?php
require_once (getenv('APP_INCLUDES_PATH') ?: ((preg_match('/^https?:\/\//i', getenv('APP_ROOT_PATH') ?: '') ? dirname(__DIR__) : (getenv('APP_ROOT_PATH') ?: dirname(__DIR__))) . '/includes')) . '/db.php';

function portal_audit_context(array $user): array
{
    return [
        'third_party_id' => isset($user['third_party_id']) ? (int)$user['third_party_id'] : null,
        'third_party_user_id' => isset($user['id']) ? (int)$user['id'] : null,
        'third_party_type' => $user['third_party_type'] ?? null,
        'internal_number' => $user['internal_number'] ?? null,
        'user_email' => $user['email'] ?? null,
    ];
}

function log_portal_activity(array $user, string $action, string $module, ?string $reportName = null, ?string $description = null, array $metadata = []): void
{
    if (($user['account_type'] ?? '') !== 'third_party') {
        return;
    }

    $context = portal_audit_context($user);
    insert_portal_activity_log($context, $action, $module, $reportName, $description, $metadata);
}

function log_portal_activity_for_user_id(int $thirdPartyUserId, string $action, string $module, ?string $reportName = null, ?string $description = null, array $metadata = []): void
{
    try {
        $stmt = db()->prepare('SELECT tpu.id, tpu.email, tp.id AS third_party_id, tp.type AS third_party_type, tp.internal_number
            FROM third_party_users tpu
            INNER JOIN third_parties tp ON tp.id = tpu.third_party_id
            WHERE tpu.id = ? LIMIT 1');
        $stmt->execute([$thirdPartyUserId]);
        $row = $stmt->fetch();
        if (!$row) {
            return;
        }

        insert_portal_activity_log([
            'third_party_id' => (int)$row['third_party_id'],
            'third_party_user_id' => (int)$row['id'],
            'third_party_type' => $row['third_party_type'],
            'internal_number' => $row['internal_number'],
            'user_email' => $row['email'],
        ], $action, $module, $reportName, $description, $metadata);
    } catch (Throwable $exception) {
        error_log('No fue posible registrar actividad del portal: ' . $exception->getMessage());
    }
}

function insert_portal_activity_log(array $context, string $action, string $module, ?string $reportName = null, ?string $description = null, array $metadata = []): void
{
    try {
        $stmt = db()->prepare('INSERT INTO portal_activity_logs
            (third_party_id, third_party_user_id, third_party_type, internal_number, user_email, action, module, report_name, description, request_method, request_uri, ip_address, user_agent, metadata)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $context['third_party_id'] ?? null,
            $context['third_party_user_id'] ?? null,
            $context['third_party_type'] ?? null,
            $context['internal_number'] ?? null,
            $context['user_email'] ?? null,
            $action,
            $module,
            $reportName,
            $description,
            $_SERVER['REQUEST_METHOD'] ?? null,
            substr((string)($_SERVER['REQUEST_URI'] ?? ''), 0, 500),
            substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
            substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
            $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ]);
    } catch (Throwable $exception) {
        error_log('No fue posible insertar bitácora del portal: ' . $exception->getMessage());
    }
}
