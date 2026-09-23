<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/collections.php';
$userEmail = requireAuth();

$input = jsonInput();
$collection = $input['collection'] ?? '';
if (!isValidCollection($collection)) {
    sendJson(['success' => false, 'error' => 'Módulo desconocido'], 400);
}

$fields = collectionFields($collection);
$data = readData();

$botId = trim($input['botId'] ?? '');
if ($botId === '') {
    sendJson(['success' => false, 'error' => 'Falta el bot'], 400);
}
$botExists = false;
foreach ($data['bots'] as $b) {
    if ($b['id'] === $botId) { $botExists = true; break; }
}
if (!$botExists) {
    sendJson(['success' => false, 'error' => 'Ese bot no existe'], 404);
}

// Arma solo los campos definidos en el schema (evita inyectar claves arbitrarias).
$values = [];
foreach ($fields as $key => $def) {
    if (!empty($def['secret'])) {
        // null/ausente = no tocar; string (incluso vacía) = nuevo valor explícito.
        if (array_key_exists($key, $input)) {
            $values[$key] = trim((string) $input[$key]);
        }
        continue;
    }
    $raw = $input[$key] ?? ($def['default'] ?? '');
    $values[$key] = is_string($raw) ? trim($raw) : $raw;
    if (!empty($def['required']) && $values[$key] === '') {
        sendJson(['success' => false, 'error' => $def['label'] . ' es obligatorio'], 400);
    }
}

$now = date('c');
$id = $input['id'] ?? null;

if ($id) {
    $found = false;
    foreach ($data['records'][$collection] as &$item) {
        if ($item['id'] === $id) {
            foreach ($values as $key => $value) {
                $item[$key] = $value;
            }
            $item['updatedAt'] = $now;
            $found = true;
            break;
        }
    }
    unset($item);
    if (!$found) {
        sendJson(['success' => false, 'error' => 'Registro no encontrado'], 404);
    }
} else {
    $item = array_merge($values, [
        'id' => bin2hex(random_bytes(8)),
        'botId' => $botId,
        'createdBy' => $userEmail,
        'createdAt' => $now,
        'updatedAt' => $now,
    ]);
    $data['records'][$collection][] = $item;
}

if (!writeData($data)) {
    sendJson(['success' => false, 'error' => 'No se pudo guardar'], 500);
}

sendJson(['success' => true]);
