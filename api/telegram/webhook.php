<?php
// Endpoint PÚBLICO: lo llama Telegram, no un usuario logueado. Su única
// autenticación es el secret_token que Telegram repite en cada request
// (X-Telegram-Bot-Api-Secret-Token), generado al conectar el bot.
require_once __DIR__ . '/../../includes/storage.php';
require_once __DIR__ . '/../../includes/telegram.php';
require_once __DIR__ . '/../../includes/ai_engine.php';

set_time_limit(170);

$botId = $_GET['bot'] ?? '';
$data = readData();

$bot = null;
foreach ($data['bots'] as $b) {
    if ($b['id'] === $botId) { $bot = $b; break; }
}
if ($bot === null || empty($bot['telegramToken']) || empty($bot['telegramWebhookSecret'])) {
    http_response_code(404);
    exit;
}

$headerSecret = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
if (!hash_equals($bot['telegramWebhookSecret'], $headerSecret)) {
    http_response_code(403);
    exit;
}

$update = json_decode(file_get_contents('php://input'), true);
$message = $update['message'] ?? null;
$text = trim($message['text'] ?? '');
$caption = trim($message['caption'] ?? '');
$photos = $message['photo'] ?? null;
$voice = $message['voice'] ?? $message['audio'] ?? null;
$chatId = $message['chat']['id'] ?? null;

if ($chatId === null || ($text === '' && !$photos && !$voice)) {
    http_response_code(200);
    exit;
}
$chatIdStr = (string) $chatId;

$apiKey = resolveOpenAiKey($bot, $data['settings']);
if ($apiKey === '') {
    telegramApiPost($bot['telegramToken'], 'sendMessage', [
        'chat_id' => $chatId,
        'text' => 'Este bot todavía no tiene una llave de IA configurada. Avísale al dueño.',
    ]);
    http_response_code(200);
    exit;
}

$userText = $text;
$userContent = null; // content multimodal (texto + imagen) para el turno actual

if ($voice) {
    $fileUrl = telegramFileUrl($bot['telegramToken'], $voice['file_id']);
    $bytes = $fileUrl ? downloadFileBytes($fileUrl) : null;
    $transcribed = $bytes !== null ? openaiTranscribeAudio($apiKey, $bytes, 'audio.ogg', $voice['mime_type'] ?? 'audio/ogg') : null;
    if ($transcribed === null || trim($transcribed) === '') {
        telegramApiPost($bot['telegramToken'], 'sendMessage', [
            'chat_id' => $chatId,
            'text' => 'No pude escuchar bien esa nota de voz, ¿puedes escribirlo?',
        ]);
        http_response_code(200);
        exit;
    }
    $userText = trim($transcribed);
} elseif ($photos) {
    $largest = end($photos); // Telegram manda el array de menor a mayor resolución
    $fileUrl = telegramFileUrl($bot['telegramToken'], $largest['file_id']);
    $bytes = $fileUrl ? downloadFileBytes($fileUrl) : null;
    if ($bytes === null) {
        telegramApiPost($bot['telegramToken'], 'sendMessage', [
            'chat_id' => $chatId,
            'text' => 'No pude descargar esa imagen, ¿puedes intentar de nuevo?',
        ]);
        http_response_code(200);
        exit;
    }
    $dataUrl = 'data:image/jpeg;base64,' . base64_encode($bytes);
    $userText = $caption !== '' ? $caption : '[Imagen]';
    $userContent = [
        ['type' => 'text', 'text' => $caption !== '' ? $caption : 'Describe o interpreta esta imagen y responde de forma útil.'],
        ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]],
    ];
}

$fromName = trim(($message['from']['first_name'] ?? '') . ' ' . ($message['from']['last_name'] ?? ''));

$history = getChatHistory($data, $botId, $chatIdStr);
$reply = runAgent($apiKey, $bot['aiModel'] ?? 'gpt-4o-mini', $bot, $botId, $data, $history, $userText, $userContent);

pushChatHistory($data, $botId, $chatIdStr, 'user', $userText);
pushChatHistory($data, $botId, $chatIdStr, 'assistant', $reply);

telegramApiPost($bot['telegramToken'], 'sendMessage', [
    'chat_id' => $chatId,
    'text' => $reply,
]);

$data['records']['conversations'][] = [
    'id' => bin2hex(random_bytes(8)),
    'botId' => $botId,
    'customer' => $fromName !== '' ? $fromName : ('Telegram #' . $chatIdStr),
    'channel' => 'telegram',
    'sentiment' => 'neutral',
    'summary' => "Cliente: {$userText}\nBot: {$reply}",
    'createdBy' => 'telegram-webhook',
    'createdAt' => date('c'),
    'updatedAt' => date('c'),
];

writeData($data);

http_response_code(200);
