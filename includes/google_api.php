<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/telegram.php';

// Integración con Calendar/Sheets/Gmail vía OAuth2 "de servidor" (authorization
// code + refresh token) -- distinto del login con Google (que solo usa el
// Client ID público). Esto SÍ necesita el Client Secret, guardado en
// settings.googleClientSecret, y un refresh token por bot.

const GOOGLE_OAUTH_SCOPES = 'https://www.googleapis.com/auth/calendar https://www.googleapis.com/auth/spreadsheets https://www.googleapis.com/auth/gmail.send https://www.googleapis.com/auth/gmail.modify openid email';

function googleRedirectUri(): string {
    $base = rtrim((isHttpsRequest() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? ''), '/');
    return $base . '/api/google/oauth-callback.php';
}

function buildGoogleAuthUrl(string $clientId, string $state): string {
    $params = [
        'client_id' => $clientId,
        'redirect_uri' => googleRedirectUri(),
        'response_type' => 'code',
        'scope' => GOOGLE_OAUTH_SCOPES,
        'access_type' => 'offline',
        'prompt' => 'consent',
        'state' => $state,
    ];
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
}

// El endpoint de token de Google espera x-www-form-urlencoded (no JSON).
function httpPostForm(string $url, array $fields, int $timeout = 20): array {
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($fields),
            'timeout' => $timeout,
            'ignore_errors' => true,
        ],
    ]);
    $raw = @file_get_contents($url, false, $context);
    $decoded = $raw !== false ? json_decode($raw, true) : null;
    return is_array($decoded) ? $decoded : [];
}

function exchangeGoogleCode(string $clientId, string $clientSecret, string $code): array {
    return httpPostForm('https://oauth2.googleapis.com/token', [
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'code' => $code,
        'grant_type' => 'authorization_code',
        'redirect_uri' => googleRedirectUri(),
    ]);
}

function refreshGoogleAccessToken(string $clientId, string $clientSecret, string $refreshToken): ?string {
    $body = httpPostForm('https://oauth2.googleapis.com/token', [
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'refresh_token' => $refreshToken,
        'grant_type' => 'refresh_token',
    ]);
    return $body['access_token'] ?? null;
}

function googleOAuthClientId(): string {
    // Cliente OAuth SEPARADO del de "Iniciar sesión con Google" (ese es
    // solo para login, sin secret ni redirect URI). Este necesita ambos.
    return getenv('GOOGLE_OAUTH_CLIENT_ID') ?: '';
}

function getGoogleAccessToken(array $bot, array $settings): ?string {
    $clientId = googleOAuthClientId();
    $clientSecret = $settings['googleClientSecret'] ?? '';
    $refreshToken = $bot['googleRefreshToken'] ?? '';
    if ($clientId === '' || $clientSecret === '' || $refreshToken === '') {
        return null;
    }
    return refreshGoogleAccessToken($clientId, $clientSecret, $refreshToken);
}

function calendarCreateEvent(string $accessToken, string $summary, string $startIso, string $endIso, string $description = ''): array {
    $result = httpPostJson('https://www.googleapis.com/calendar/v3/calendars/primary/events', [
        'summary' => $summary,
        'description' => $description,
        'start' => ['dateTime' => $startIso],
        'end' => ['dateTime' => $endIso],
    ], ['Authorization: Bearer ' . $accessToken], 20);
    return $result['body'];
}

function calendarListUpcoming(string $accessToken, int $maxResults = 8): array {
    $params = http_build_query([
        'timeMin' => gmdate('c'),
        'orderBy' => 'startTime',
        'singleEvents' => 'true',
        'maxResults' => $maxResults,
    ]);
    $result = httpGetJsonAuth('https://www.googleapis.com/calendar/v3/calendars/primary/events?' . $params, $accessToken);
    return $result['items'] ?? [];
}

function httpGetJsonAuth(string $url, string $accessToken): array {
    $context = stream_context_create([
        'http' => [
            'header' => 'Authorization: Bearer ' . $accessToken,
            'timeout' => 20,
            'ignore_errors' => true,
        ],
    ]);
    $raw = @file_get_contents($url, false, $context);
    $decoded = $raw !== false ? json_decode($raw, true) : null;
    return is_array($decoded) ? $decoded : [];
}
