<?php
require_once __DIR__ . '/../../includes/auth.php';
$userEmail = requireAuth();

$input = jsonInput();
$name = trim($input['name'] ?? '');
if ($name === '') {
    sendJson(['success' => false, 'error' => 'El bot necesita un nombre'], 400);
}

$niche = trim($input['niche'] ?? 'generico');
$description = trim($input['description'] ?? '');
$url = trim($input['url'] ?? '');
$status = ($input['status'] ?? 'activo') === 'pausado' ? 'pausado' : 'activo';
$keyMode = ($input['keyMode'] ?? 'shared') === 'own' ? 'own' : 'shared';
$ownApiKeyInput = $input['ownApiKey'] ?? null; // null = no tocar; '' = borrar; string = nueva llave

$data = readData();
$now = date('c');
$id = $input['id'] ?? null;

if ($id) {
    $found = false;
    foreach ($data['bots'] as &$bot) {
        if ($bot['id'] === $id) {
            $bot['name'] = $name;
            $bot['niche'] = $niche;
            $bot['description'] = $description;
            $bot['url'] = $url;
            $bot['status'] = $status;
            $bot['keyMode'] = $keyMode;
            if ($ownApiKeyInput !== null) {
                $bot['ownApiKey'] = $ownApiKeyInput;
            }
            if ($keyMode === 'shared') {
                $bot['ownApiKey'] = '';
            }
            $bot['updatedAt'] = $now;
            $found = true;
            break;
        }
    }
    unset($bot);
    if (!$found) {
        sendJson(['success' => false, 'error' => 'Bot no encontrado'], 404);
    }
} else {
    $bot = [
        'id' => bin2hex(random_bytes(8)),
        'name' => $name,
        'niche' => $niche,
        'description' => $description,
        'url' => $url,
        'status' => $status,
        'keyMode' => $keyMode,
        'ownApiKey' => $keyMode === 'own' ? ($ownApiKeyInput ?? '') : '',
        'createdBy' => $userEmail,
        'createdAt' => $now,
        'updatedAt' => $now,
    ];
    $data['bots'][] = $bot;
}

if (!writeData($data)) {
    sendJson(['success' => false, 'error' => 'No se pudo guardar'], 500);
}

sendJson(['success' => true]);
