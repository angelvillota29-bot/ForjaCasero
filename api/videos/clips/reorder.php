<?php
require_once __DIR__ . '/../../../includes/auth.php';
requireAuth();

$input = jsonInput();
$projectId = $input['projectId'] ?? '';
$clipIds = $input['clipIds'] ?? [];
if (!is_array($clipIds)) {
    sendJson(['success' => false, 'error' => 'Orden inválido'], 400);
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

$byId = [];
foreach ($project['clips'] as $c) {
    $byId[$c['id']] = $c;
}

$reordered = [];
foreach ($clipIds as $i => $cid) {
    if (isset($byId[$cid])) {
        $byId[$cid]['order'] = $i;
        $reordered[] = $byId[$cid];
    }
}
if (count($reordered) !== count($project['clips'])) {
    sendJson(['success' => false, 'error' => 'La lista de orden no coincide con los clips del proyecto'], 400);
}

$project['clips'] = $reordered;
$project['updatedAt'] = date('c');
writeData($data);

sendJson(['success' => true]);
