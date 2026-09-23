<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/collections.php';
requireAuth();

$input = jsonInput();
$collection = $input['collection'] ?? '';
$id = $input['id'] ?? '';

if (!isValidCollection($collection)) {
    sendJson(['success' => false, 'error' => 'Módulo desconocido'], 400);
}
if ($id === '') {
    sendJson(['success' => false, 'error' => 'Falta el id'], 400);
}

$data = readData();
$before = count($data['records'][$collection]);
$data['records'][$collection] = array_values(array_filter(
    $data['records'][$collection],
    fn($item) => $item['id'] !== $id
));

if (count($data['records'][$collection]) === $before) {
    sendJson(['success' => false, 'error' => 'Registro no encontrado'], 404);
}

if (!writeData($data)) {
    sendJson(['success' => false, 'error' => 'No se pudo borrar'], 500);
}

sendJson(['success' => true]);
