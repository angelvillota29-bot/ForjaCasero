<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/telegram.php';
requireAuth();

$input = jsonInput();
$botId = $input['botId'] ?? '';
$token = trim($input['telegramToken'] ?? '');

if ($botId === '' || $token === '') {
    sendJson(['success' => false, 'error' => 'Falta el bot o el token'], 400);
}

$data = readData();
$found = false;
$connectedUsername = '';
foreach ($data['bots'] as &$bot) {
    if ($bot['id'] !== $botId) continue;
    $found = true;

    $me = telegramApiGet($token, 'getMe');
    if (empty($me['ok'])) {
        sendJson(['success' => false, 'error' => 'Telegram rechazó ese token: ' . ($me['description'] ?? 'inválido')], 400);
    }

    $webhookSecret = bin2hex(random_bytes(24));
    $publicBase = rtrim((!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? ''), '/');
    $webhookUrl = $publicBase . '/api/telegram/webhook.php?bot=' . urlencode($botId);

    $setResult = telegramApiGet($token, 'setWebhook', [
        'url' => $webhookUrl,
        'secret_token' => $webhookSecret,
    ]);
    if (empty($setResult['ok'])) {
        sendJson(['success' => false, 'error' => 'No se pudo registrar el webhook: ' . ($setResult['description'] ?? 'error')], 400);
    }

    $bot['telegramToken'] = $token;
    $bot['telegramWebhookSecret'] = $webhookSecret;
    $bot['telegramUsername'] = $me['result']['username'] ?? '';
    $bot['telegramConnectedAt'] = date('c');
    $bot['updatedAt'] = date('c');
    $connectedUsername = $bot['telegramUsername'];
    break;
}
unset($bot);

if (!$found) {
    sendJson(['success' => false, 'error' => 'Bot no encontrado'], 404);
}

if (!writeData($data)) {
    sendJson(['success' => false, 'error' => 'No se pudo guardar'], 500);
}

sendJson(['success' => true, 'username' => $connectedUsername]);
