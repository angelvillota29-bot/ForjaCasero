<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/video_engine.php';
requireAuth();

$input = jsonInput();
$id = $input['id'] ?? '';
$data = readData();

$kept = [];
$removed = null;
foreach ($data['videoProjects'] as $p) {
    if ($p['id'] === $id) {
        $removed = $p;
        continue;
    }
    $kept[] = $p;
}
if ($removed === null) {
    sendJson(['success' => false, 'error' => 'Proyecto no encontrado'], 404);
}

foreach ($removed['clips'] ?? [] as $clip) {
    if (!empty($clip['path']) && file_exists($clip['path'])) {
        @unlink($clip['path']);
    }
}
if (!empty($removed['outputPath']) && file_exists($removed['outputPath'])) {
    @unlink($removed['outputPath']);
}
cleanupTmpDir(videoTmpDir($id));

$data['videoProjects'] = $kept;
writeData($data);

sendJson(['success' => true]);
