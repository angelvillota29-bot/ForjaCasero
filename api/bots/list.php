<?php
require_once __DIR__ . '/../../includes/auth.php';
requireAuth();

$data = readData();
$bots = array_map(function ($bot) {
    $bot['ownApiKeySet'] = !empty($bot['ownApiKey']);
    $bot['ownApiKeyHint'] = !empty($bot['ownApiKey']) ? keyHint($bot['ownApiKey']) : null;
    unset($bot['ownApiKey']);
    return $bot;
}, $data['bots']);

sendJson(['success' => true, 'bots' => $bots]);
