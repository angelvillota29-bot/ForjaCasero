<?php
require_once __DIR__ . '/../../../includes/auth.php';
requireAuth();

$projectId = $_POST['projectId'] ?? '';
if ($projectId === '') {
    sendJson(['success' => false, 'error' => 'Falta el proyecto'], 400);
}
if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    sendJson(['success' => false, 'error' => 'No se recibió el archivo'], 400);
}

$allowedExt = ['mp4', 'mov', 'webm', 'mkv', 'm4v'];
$ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowedExt, true)) {
    sendJson(['success' => false, 'error' => 'Formato no soportado (usa mp4, mov, webm o mkv)'], 400);
}

$data = readData();
$project = null;
foreach ($data['videoProjects'] as &$p) {
    if ($p['id'] === $projectId) { $project = &$p; break; }
}
unset($p);
if ($project === null) {
    sendJson(['success' => false, 'error' => 'Proyecto no encontrado'], 404);
}

$clipsDir = mediaDir() . '/clips';
if (!is_dir($clipsDir)) {
    @mkdir($clipsDir, 0775, true);
}

$clipId = bin2hex(random_bytes(8));
$dest = "{$clipsDir}/{$clipId}.{$ext}";
if (!move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
    sendJson(['success' => false, 'error' => 'No se pudo guardar el clip'], 500);
}

$project['clips'][] = [
    'id' => $clipId,
    'filename' => $_FILES['file']['name'],
    'path' => $dest,
    'order' => count($project['clips']),
];
$project['updatedAt'] = date('c');

writeData($data);

sendJson(['success' => true, 'clipId' => $clipId]);
