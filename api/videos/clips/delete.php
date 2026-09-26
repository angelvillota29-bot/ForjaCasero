<?php
require_once __DIR__ . '/../../../includes/auth.php';
requireAuth();

$input = jsonInput();
$projectId = $input['projectId'] ?? '';
$clipId = $input['clipId'] ?? '';

$data = readData();
$project = null;
foreach ($data['videoProjects'] as &$p) {
    if ($p['id'] === $projectId) { $project = &$p; break; }
}
unset($p);
if ($project === null) {
    sendJson(['success' => false, 'error' => 'Proyecto no encontrado'], 404);
}

$kept = [];
$removedPath = null;
foreach ($project['clips'] as $c) {
    if ($c['id'] === $clipId) {
        $removedPath = $c['path'];
        continue;
    }
    $kept[] = $c;
}
foreach ($kept as $i => &$c) {
    $c['order'] = $i;
}
unset($c);
$project['clips'] = $kept;
$project['updatedAt'] = date('c');

writeData($data);

if ($removedPath && file_exists($removedPath)) {
    @unlink($removedPath);
}

sendJson(['success' => true]);
