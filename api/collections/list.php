<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/collections.php';
requireAuth();

$collection = $_GET['collection'] ?? '';
$botId = $_GET['botId'] ?? '';

if (!isValidCollection($collection)) {
    sendJson(['success' => false, 'error' => 'Módulo desconocido'], 400);
}

$data = readData();
$items = $data['records'][$collection] ?? [];

if ($botId !== '') {
    $items = array_values(array_filter($items, fn($item) => ($item['botId'] ?? '') === $botId));
}

$fields = collectionFields($collection);
$items = array_map(function ($item) use ($fields) {
    foreach ($fields as $key => $def) {
        if (!empty($def['secret'])) {
            $raw = $item[$key] ?? '';
            $item[$key . 'Set'] = $raw !== '';
            $item[$key . 'Hint'] = $raw !== '' ? keyHint($raw) : null;
            unset($item[$key]);
        }
    }
    return $item;
}, $items);

sendJson(['success' => true, 'items' => $items]);
