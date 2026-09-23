<?php
require_once __DIR__ . '/../../includes/auth.php';
requireAuth();

$data = readData();
$settings = $data['settings'];

sendJson([
    'success' => true,
    'settings' => [
        'sharedApiKeySet' => !empty($settings['sharedApiKey']),
        'sharedApiKeyHint' => !empty($settings['sharedApiKey']) ? keyHint($settings['sharedApiKey']) : null,
        'allowedEmails' => $settings['allowedEmails'],
    ],
]);
