<?php
require_once __DIR__ . '/../../includes/auth.php';
requireAuth();

$input = jsonInput();
$id = $input['id'] ?? '';
if ($id === '') {
    sendJson(['success' => false, 'error' => 'Falta el id'], 400);
}

$data = readData();
$before = count($data['bots']);
$data['bots'] = array_values(array_filter($data['bots'], fn($b) => $b['id'] !== $id));

if (count($data['bots']) === $before) {
    sendJson(['success' => false, 'error' => 'Bot no encontrado'], 404);
}

if (!writeData($data)) {
    sendJson(['success' => false, 'error' => 'No se pudo borrar'], 500);
}

sendJson(['success' => true]);
