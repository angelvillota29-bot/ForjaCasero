<?php
require_once __DIR__ . '/../../includes/auth.php';
requireAuth();

$data = readData();
$bots = array_map(function ($bot) {
    $bot['ownApiKeySet'] = !empty($bot['ownApiKey']);
    $bot['ownApiKeyHint'] = !empty($bot['ownApiKey']) ? keyHint($bot['ownApiKey']) : null;
    $bot['telegramConnected'] = !empty($bot['telegramToken']);
    $bot['googleConnected'] = !empty($bot['googleRefreshToken']);
    unset($bot['ownApiKey'], $bot['telegramToken'], $bot['telegramWebhookSecret'], $bot['googleRefreshToken']);
    return $bot;
}, $data['bots']);

sendJson(['success' => true, 'bots' => $bots]);
