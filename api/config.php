<?php
// Endpoint público (sin sesión): la pantalla de login necesita el Client ID
// de Google para poder mostrar el botón "Iniciar sesión con Google".
require_once __DIR__ . '/../includes/storage.php';

sendJson([
    'googleClientId' => getenv('GOOGLE_CLIENT_ID') ?: '',
]);
