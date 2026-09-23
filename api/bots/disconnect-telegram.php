<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/telegram.php';
requireAuth();

$input = jsonInput();
$botId = $input['botId'] ?? '';

$data = readData();
$found = false;
foreach ($data['bots'] as &$bot) {
    if ($bot['id'] !== $botId) continue;
    $found = true;

    if (!empty($bot['telegramToken'])) {
        telegramApiGet($bot['telegramToken'], 'deleteWebhook');
    }
    unset($bot['telegramToken'], $bot['telegramWebhookSecret'], $bot['telegramUsername'], $bot['telegramConnectedAt']);
    $bot['updatedAt'] = date('c');
    break;
}
unset($bot);

if (!$found) {
    sendJson(['success' => false, 'error' => 'Bot no encontrado'], 404);
}

if (!writeData($data)) {
    sendJson(['success' => false, 'error' => 'No se pudo guardar'], 500);
}

sendJson(['success' => true]);
