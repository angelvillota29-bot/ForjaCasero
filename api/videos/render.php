<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/video_engine.php';
requireAuth();

set_time_limit(600);

$input = jsonInput();
$id = $input['id'] ?? '';
$data = readData();

$project = null;
foreach ($data['videoProjects'] as &$p) {
    if ($p['id'] === $id) { $project = &$p; break; }
}
unset($p);
if ($project === null) {
    sendJson(['success' => false, 'error' => 'Proyecto no encontrado'], 404);
}
if (empty($project['clips'])) {
    sendJson(['success' => false, 'error' => 'Sube al menos un clip antes de renderizar'], 400);
}

$project['status'] = 'rendering';
$project['error'] = '';
writeData($data);

$clips = $project['clips'];
usort($clips, fn($a, $b) => $a['order'] <=> $b['order']);
$clipPaths = array_map(fn($c) => $c['path'], $clips);

$logoPath = $data['videoAssets']['logoPath'] ?: null;
$musicPath = $data['videoAssets']['musicPath'] ?: null;
$apiKey = $data['settings']['sharedApiKey'] ?: null;

$outputDir = mediaDir() . '/output';
if (!is_dir($outputDir)) {
    @mkdir($outputDir, 0775, true);
}
$outputPath = "{$outputDir}/{$id}.mp4";

$result = renderVideoProject($project, $clipPaths, $logoPath, $musicPath, $apiKey, $outputPath);

// Vuelve a leer por si otra petición cambió datos mientras renderizaba.
$data = readData();
foreach ($data['videoProjects'] as &$p) {
    if ($p['id'] === $id) {
        if ($result['ok']) {
            $p['status'] = 'done';
            $p['outputPath'] = $outputPath;
            $p['error'] = '';
        } else {
            $p['status'] = 'error';
            $p['error'] = $result['error'];
        }
        $p['updatedAt'] = date('c');
        break;
    }
}
unset($p);
writeData($data);

if (!$result['ok']) {
    sendJson(['success' => false, 'error' => $result['error']], 500);
}

sendJson(['success' => true]);
