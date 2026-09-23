<?php
// PÚBLICO: Google redirige aquí al navegador del usuario tras el consentimiento.
// No hay sesión de Forja Casero confiable en esta navegación entre dominios,
// así que la autenticidad viene del "state" firmado (ver auth-url.php).
require_once __DIR__ . '/../../includes/storage.php';
require_once __DIR__ . '/../../includes/telegram.php';
require_once __DIR__ . '/../../includes/google_api.php';

function htmlPage(string $title, string $body): void {
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>{$title}</title>
    <style>body{background:#0a0a0b;color:#ececee;font-family:system-ui;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}
    .box{max-width:420px;text-align:center;padding:24px;}</style></head>
    <body><div class='box'>{$body}</div></body></html>";
    exit;
}

$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';
$error = $_GET['error'] ?? '';

if ($error !== '') {
    htmlPage('Cancelado', "<h2>Conexión cancelada</h2><p>No se autorizó el acceso. Puedes cerrar esta pestaña.</p>");
}

[$botId, $signature] = array_pad(explode('.', $state, 2), 2, '');
$data = readData();
$clientSecret = $data['settings']['googleClientSecret'] ?? '';

if ($botId === '' || $clientSecret === '' || !hash_equals(substr(hash_hmac('sha256', $botId, $clientSecret), 0, 16), $signature)) {
    htmlPage('Error', "<h2>Enlace inválido</h2><p>Este enlace no es válido o expiró. Vuelve a intentar desde Forja Casero.</p>");
}

$clientId = googleOAuthClientId();
$tokens = exchangeGoogleCode($clientId, $clientSecret, $code);

if (empty($tokens['refresh_token']) && empty($tokens['access_token'])) {
    htmlPage('Error', "<h2>No se pudo conectar</h2><p>Google no devolvió un token. " . htmlspecialchars($tokens['error_description'] ?? '') . "</p>");
}

$found = false;
foreach ($data['bots'] as &$bot) {
    if ($bot['id'] !== $botId) continue;
    $found = true;
    if (!empty($tokens['refresh_token'])) {
        $bot['googleRefreshToken'] = $tokens['refresh_token'];
    }
    $bot['googleConnectedAt'] = date('c');
    $bot['updatedAt'] = date('c');
    break;
}
unset($bot);

if (!$found) {
    htmlPage('Error', "<h2>Bot no encontrado</h2>");
}

writeData($data);

htmlPage('Conectado', "<h2>✓ Google conectado</h2><p>Ya puedes cerrar esta pestaña y volver a Forja Casero.</p>");
