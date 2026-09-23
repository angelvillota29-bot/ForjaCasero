<?php
require_once __DIR__ . '/../../includes/auth.php';
$userEmail = requireAuth();

$input = jsonInput();
$data = readData();

if (array_key_exists('sharedApiKey', $input)) {
    $data['settings']['sharedApiKey'] = trim($input['sharedApiKey']);
}

if (array_key_exists('googleClientSecret', $input)) {
    $data['settings']['googleClientSecret'] = trim($input['googleClientSecret']);
}

if (array_key_exists('addEmail', $input) && trim($input['addEmail']) !== '') {
    $email = strtolower(trim($input['addEmail']));
    if (!in_array($email, $data['settings']['allowedEmails'], true)) {
        $data['settings']['allowedEmails'][] = $email;
    }
}

if (array_key_exists('removeEmail', $input) && trim($input['removeEmail']) !== '') {
    $email = strtolower(trim($input['removeEmail']));
    if ($email === $userEmail) {
        sendJson(['success' => false, 'error' => 'No puedes quitarte el acceso a ti mismo'], 400);
    }
    $data['settings']['allowedEmails'] = array_values(array_filter(
        $data['settings']['allowedEmails'],
        fn($e) => $e !== $email
    ));
}

if (!writeData($data)) {
    sendJson(['success' => false, 'error' => 'No se pudo guardar'], 500);
}

sendJson(['success' => true]);
