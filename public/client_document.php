<?php
require_once (getenv('APP_INCLUDES_PATH') ?: ((preg_match('/^https?:\/\//i', getenv('APP_ROOT_PATH') ?: '') ? dirname(__DIR__) : (getenv('APP_ROOT_PATH') ?: dirname(__DIR__))) . '/includes')) . '/auth.php';
require_once app_path('includes/client_documents.php');

$user = require_login();
if (($user['account_type'] ?? '') !== 'third_party' || ($user['third_party_type'] ?? '') !== 'client') {
    http_response_code(403);
    exit('No autorizado.');
}

$document = client_document_for_download((int)($_GET['id'] ?? 0), (int)$user['third_party_id']);
if (!$document) {
    http_response_code(404);
    exit('Documento no encontrado.');
}
if (!client_document_storage_is_secure()) {
    http_response_code(500);
    exit('El almacenamiento de documentos no está configurado de forma segura.');
}
$path = client_document_file_path((string)$document['stored_name']);
if (!is_file($path) || !is_readable($path)) {
    http_response_code(404);
    exit('Archivo no encontrado.');
}

log_portal_activity($user, 'document.download', 'client_documents', 'Documentación del cliente', 'Descarga de documento protegido', ['document_id' => (int)$document['id']]);
$downloadName = preg_replace('/[^\pL\pN._ -]+/u', '_', (string)$document['original_name']) ?: 'documento';
header('Content-Type: ' . $document['mime_type']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="documento"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
readfile($path);
