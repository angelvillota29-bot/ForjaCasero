<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/telegram.php';
require_once __DIR__ . '/../../includes/google_api.php';
requireAuth();

$botId = $_GET['botId'] ?? '';
if ($botId === '') {
    sendJson(['success' => false, 'error' => 'Falta el bot'], 400);
}

$clientId = googleOAuthClientId();
if ($clientId === '') {
    sendJson(['success' => false, 'error' => 'Falta GOOGLE_OAUTH_CLIENT_ID en el servidor'], 400);
}

$data = readData();
if (empty($data['settings']['googleClientSecret'])) {
    sendJson(['success' => false, 'error' => 'Falta configurar el Google Client Secret en Conexiones'], 400);
}

// El "state" lleva el botId + un firma corta para que el callback (público)
// sepa a cuál bot atar el refresh token sin que cualquiera lo falsifique.
$secret = $data['settings']['googleClientSecret'];
$signature = substr(hash_hmac('sha256', $botId, $secret), 0, 16);
$state = $botId . '.' . $signature;

sendJson(['success' => true, 'url' => buildGoogleAuthUrl($clientId, $state)]);
