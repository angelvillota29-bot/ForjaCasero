<?php
// Sube (una vez) el logo o la pista de música de marca que se reutilizan
// en todos los videos generados.
require_once __DIR__ . '/../../../includes/auth.php';
requireAuth();

$type = $_POST['type'] ?? '';
if (!in_array($type, ['logo', 'music'], true)) {
    sendJson(['success' => false, 'error' => 'Tipo inválido'], 400);
}
if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    sendJson(['success' => false, 'error' => 'No se recibió el archivo'], 400);
}

$assetsDir = mediaDir() . '/assets';
if (!is_dir($assetsDir)) {
    @mkdir($assetsDir, 0775, true);
}

$ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
if ($type === 'logo') {
    if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true)) {
        sendJson(['success' => false, 'error' => 'El logo debe ser PNG, JPG o WEBP'], 400);
    }
    $dest = $assetsDir . '/logo.' . $ext;
} else {
    if (!in_array($ext, ['mp3', 'm4a', 'wav', 'aac'], true)) {
        sendJson(['success' => false, 'error' => 'La música debe ser MP3, M4A, WAV o AAC'], 400);
    }
    $dest = $assetsDir . '/music.' . $ext;
}

// Borra versiones anteriores con otra extensión para no dejar basura.
foreach (glob($assetsDir . '/' . $type . '.*') as $old) {
    @unlink($old);
}

if (!move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
    sendJson(['success' => false, 'error' => 'No se pudo guardar el archivo'], 500);
}

$data = readData();
$data['videoAssets'][$type === 'logo' ? 'logoPath' : 'musicPath'] = $dest;
writeData($data);

sendJson(['success' => true]);
