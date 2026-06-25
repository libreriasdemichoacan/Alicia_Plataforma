<?php
require_once (getenv('APP_INCLUDES_PATH') ?: ((preg_match('/^https?:\/\//i', getenv('APP_ROOT_PATH') ?: '') ? dirname(__DIR__) : (getenv('APP_ROOT_PATH') ?: dirname(__DIR__))) . '/includes')) . '/bootstrap.php';

function upload_image(string $field, string $folder, ?string &$error): ?string
{
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        $error = 'No se pudo cargar el archivo. Intenta nuevamente.';
        return null;
    }

    $allowedTypes = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
    ];
    $mimeType = mime_content_type($_FILES[$field]['tmp_name']) ?: '';
    if (!isset($allowedTypes[$mimeType])) {
        $error = 'La imagen debe ser PNG, JPG, WEBP o SVG.';
        return null;
    }
    if ($_FILES[$field]['size'] > 2 * 1024 * 1024) {
        $error = 'La imagen no debe superar 2 MB.';
        return null;
    }

    $safeFolder = trim($folder, '/');
    $uploadDir = app_path('public/uploads/' . $safeFolder);
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $filename = $safeFolder . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $allowedTypes[$mimeType];
    $target = $uploadDir . '/' . $filename;
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $target)) {
        $error = 'No se pudo guardar la imagen en el servidor.';
        return null;
    }

    return '/uploads/' . $safeFolder . '/' . $filename;
}
