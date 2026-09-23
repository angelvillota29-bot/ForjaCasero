<?php
require_once __DIR__ . '/../../includes/auth.php';

$input = jsonInput();
$idToken = $input['credential'] ?? '';

$payload = verifyGoogleIdToken($idToken);
if ($payload === null) {
    sendJson(['success' => false, 'error' => 'Token de Google inválido'], 401);
}

$email = strtolower($payload['email']);
$data = readData();
$allowed = array_map('strtolower', $data['settings']['allowedEmails']);

if (!in_array($email, $allowed, true)) {
    sendJson(['success' => false, 'error' => 'Tu correo (' . $email . ') no tiene acceso a este panel. Pídele a un administrador que lo agregue en Accesos.'], 403);
}

startAppSession();
session_regenerate_id(true);
$_SESSION['user_email'] = $email;
$_SESSION['user_name'] = $payload['name'] ?? $email;
$_SESSION['user_picture'] = $payload['picture'] ?? '';

sendJson(['success' => true, 'email' => $email, 'name' => $_SESSION['user_name']]);
