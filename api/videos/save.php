<?php
require_once __DIR__ . '/../../includes/auth.php';
requireAuth();

$input = jsonInput();
$title = trim($input['title'] ?? '');
if ($title === '') {
    sendJson(['success' => false, 'error' => 'Ponle un título al proyecto'], 400);
}

$pillar = trim($input['pillar'] ?? 'dolor');
$hook = trim($input['hook'] ?? '');
$cta = trim($input['cta'] ?? '');
$script = trim($input['script'] ?? '');
$subtitlesEnabled = !empty($input['subtitlesEnabled']);
$musicEnabled = !empty($input['musicEnabled']);

$data = readData();
$now = date('c');
$id = $input['id'] ?? null;

if ($id) {
    $found = false;
    foreach ($data['videoProjects'] as &$p) {
        if ($p['id'] === $id) {
            $p['title'] = $title;
            $p['pillar'] = $pillar;
            $p['hook'] = $hook;
            $p['cta'] = $cta;
            $p['script'] = $script;
            $p['subtitlesEnabled'] = $subtitlesEnabled;
            $p['musicEnabled'] = $musicEnabled;
            $p['updatedAt'] = $now;
            $found = true;
            break;
        }
    }
    unset($p);
    if (!$found) {
        sendJson(['success' => false, 'error' => 'Proyecto no encontrado'], 404);
    }
    $projectId = $id;
} else {
    $projectId = bin2hex(random_bytes(8));
    $data['videoProjects'][] = [
        'id' => $projectId,
        'title' => $title,
        'pillar' => $pillar,
        'hook' => $hook,
        'cta' => $cta,
        'script' => $script,
        'subtitlesEnabled' => $subtitlesEnabled,
        'musicEnabled' => $musicEnabled,
        'clips' => [],
        'status' => 'draft',
        'outputPath' => '',
        'error' => '',
        'createdAt' => $now,
        'updatedAt' => $now,
    ];
}

if (!writeData($data)) {
    sendJson(['success' => false, 'error' => 'No se pudo guardar'], 500);
}

sendJson(['success' => true, 'id' => $projectId]);
