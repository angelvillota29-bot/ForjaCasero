<?php
require_once __DIR__ . '/storage.php';

function isHttpsRequest(): bool {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    // Easypanel/Traefik terminan TLS y reenvían por HTTP plano al contenedor,
    // así que hay que mirar también la cabecera que pone el proxy.
    return strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

function startAppSession(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'samesite' => 'Lax',
            'httponly' => true,
            'secure' => isHttpsRequest(),
        ]);
        session_start();
    }
}

function currentUserEmail(): ?string {
    startAppSession();
    return $_SESSION['user_email'] ?? null;
}

function requireAuth(): string {
    $email = currentUserEmail();
    if ($email === null) {
        sendJson(['success' => false, 'error' => 'No autenticado'], 401);
    }
    return $email;
}

// Verifica un ID token de Google Identity Services contra el endpoint público
// tokeninfo de Google (sin necesitar client secret ni librerías de JWT).
// Devuelve el payload decodificado o null si no es válido.
function verifyGoogleIdToken(string $idToken): ?array {
    $clientId = getenv('GOOGLE_CLIENT_ID') ?: '';
    if ($clientId === '' || $idToken === '') {
        return null;
    }
    $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken);
    $context = stream_context_create(['http' => ['timeout' => 8]]);
    $raw = @file_get_contents($url, false, $context);
    if ($raw === false) {
        return null;
    }
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        return null;
    }
    if (($payload['aud'] ?? '') !== $clientId) {
        return null;
    }
    if (($payload['email_verified'] ?? 'false') !== 'true') {
        return null;
    }
    if (empty($payload['email'])) {
        return null;
    }
    return $payload;
}
