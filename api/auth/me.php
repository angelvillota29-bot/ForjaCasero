<?php
require_once __DIR__ . '/../../includes/auth.php';

startAppSession();
$email = currentUserEmail();

if ($email === null) {
    sendJson(['authenticated' => false]);
}

sendJson([
    'authenticated' => true,
    'email' => $email,
    'name' => $_SESSION['user_name'] ?? $email,
    'picture' => $_SESSION['user_picture'] ?? '',
    'googleClientId' => getenv('GOOGLE_CLIENT_ID') ?: '',
]);
