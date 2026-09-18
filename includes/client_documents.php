<?php
require_once (getenv('APP_INCLUDES_PATH') ?: ((preg_match('/^https?:\/\//i', getenv('APP_ROOT_PATH') ?: '') ? dirname(__DIR__) : (getenv('APP_ROOT_PATH') ?: dirname(__DIR__))) . '/includes')) . '/db.php';

function client_document_types(): array
{
    return [
        'tax_status' => 'Constancia de Situación Fiscal actualizada',
        'address_proof' => 'Copia del comprobante de domicilio (agua, luz o teléfono) no mayor a 3 meses',
        'official_id' => 'Copia de identificación oficial (INE) del directivo y/o responsable de la compra',
        'power_of_attorney' => 'Carta poder simple y copia de identificación oficial (INE) de las personas autorizadas para recibir pedidos',
        'articles_of_incorporation' => 'Acta Constitutiva (en caso de persona moral)',
        'sat_compliance' => 'Opinión de cumplimiento SAT positiva',
        'credit_report' => 'Reporte especial de crédito (no mayor a 60 días)',
        'bank_statement' => 'Carátula bancaria / Estado de cuenta para validación de datos bancarios',
        'other' => 'Documentos varios',
    ];
}

function client_documents_for(int $thirdPartyId): array
{
    $stmt = db()->prepare('SELECT id, document_type, custom_label, original_name, mime_type, file_size, created_at
        FROM client_documents WHERE third_party_id = ? ORDER BY created_at DESC, id DESC');
    $stmt->execute([$thirdPartyId]);
    return $stmt->fetchAll();
}

function client_document_storage_path(): string
{
    return rtrim(app_config()['paths']['storage'], '/\\') . '/client_documents';
}

function client_document_storage_is_secure(): bool
{
    $storage = str_replace('\\', '/', rtrim(client_document_storage_path(), '/\\')) . '/';
    $public = str_replace('\\', '/', rtrim(app_config()['paths']['public'], '/\\')) . '/';
    return !str_starts_with(strtolower($storage), strtolower($public));
}

function save_client_document(array $user, array $file, string $documentType, string $customLabel = ''): array
{
    $types = client_document_types();
    if (!isset($types[$documentType])) {
        return ['success' => false, 'error' => 'Selecciona un tipo de documento válido.'];
    }
    $customLabel = trim($customLabel);
    if ($documentType === 'other' && $customLabel === '') {
        return ['success' => false, 'error' => 'Escribe una descripción para el documento adicional.'];
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
        return ['success' => false, 'error' => 'Selecciona un archivo válido para subir.'];
    }
    if (!client_document_storage_is_secure()) {
        error_log('APP_STORAGE_PATH no puede estar dentro del directorio público para almacenar documentos.');
        return ['success' => false, 'error' => 'El almacenamiento de documentos no está configurado de forma segura.'];
    }
    $size = (int)($file['size'] ?? 0);
    if ($size < 1 || $size > 10 * 1024 * 1024) {
        return ['success' => false, 'error' => 'El archivo debe pesar máximo 10 MB.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($file['tmp_name']);
    $extensions = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($extensions[$mime])) {
        return ['success' => false, 'error' => 'Solo se permiten archivos PDF e imágenes JPG, PNG o WEBP.'];
    }
    if (str_starts_with($mime, 'image/') && @getimagesize($file['tmp_name']) === false) {
        return ['success' => false, 'error' => 'La imagen seleccionada no es válida.'];
    }
    if ($mime === 'application/pdf' && file_get_contents($file['tmp_name'], false, null, 0, 5) !== '%PDF-') {
        return ['success' => false, 'error' => 'El archivo PDF seleccionado no es válido.'];
    }

    $storage = client_document_storage_path();
    if (!is_dir($storage) && !mkdir($storage, 0750, true) && !is_dir($storage)) {
        return ['success' => false, 'error' => 'No fue posible preparar el almacenamiento seguro.'];
    }
    $storedName = bin2hex(random_bytes(24)) . '.' . $extensions[$mime];
    $destination = $storage . '/' . $storedName;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => false, 'error' => 'No fue posible guardar el documento.'];
    }
    @chmod($destination, 0640);

    try {
        $stmt = db()->prepare('INSERT INTO client_documents
            (third_party_id, uploaded_by, document_type, custom_label, original_name, stored_name, mime_type, file_size, sha256)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            (int)$user['third_party_id'],
            (int)$user['id'],
            $documentType,
            $customLabel !== '' ? mb_substr($customLabel, 0, 180) : null,
            mb_substr(basename((string)($file['name'] ?? 'documento')), 0, 255),
            $storedName,
            $mime,
            $size,
            hash_file('sha256', $destination),
        ]);
    } catch (Throwable $exception) {
        @unlink($destination);
        error_log('No fue posible registrar documento del cliente: ' . $exception->getMessage());
        return ['success' => false, 'error' => 'No fue posible registrar el documento.'];
    }

    return ['success' => true, 'error' => null, 'id' => (int)db()->lastInsertId()];
}

function client_document_for_download(int $documentId, int $thirdPartyId): ?array
{
    $stmt = db()->prepare('SELECT id, original_name, stored_name, mime_type, file_size
        FROM client_documents WHERE id = ? AND third_party_id = ? LIMIT 1');
    $stmt->execute([$documentId, $thirdPartyId]);
    return $stmt->fetch() ?: null;
}
