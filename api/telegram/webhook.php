<?php
// Endpoint PÚBLICO: lo llama Telegram, no un usuario logueado. Su única
// autenticación es el secret_token que Telegram repite en cada request
// (X-Telegram-Bot-Api-Secret-Token), generado al conectar el bot.
require_once __DIR__ . '/../../includes/storage.php';
require_once __DIR__ . '/../../includes/telegram.php';
require_once __DIR__ . '/../../includes/ai_engine.php';

set_time_limit(90);

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
$chatId = $message['chat']['id'] ?? null;

// Confirma rápido a Telegram aunque no haya texto (stickers, fotos, etc.)
if ($chatId === null || $text === '') {
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

$fromName = trim(($message['from']['first_name'] ?? '') . ' ' . ($message['from']['last_name'] ?? ''));

$history = getChatHistory($data, $botId, $chatIdStr);
$reply = runAgent($apiKey, $bot['aiModel'] ?? 'gpt-4o-mini', $bot, $botId, $data, $history, $text);

pushChatHistory($data, $botId, $chatIdStr, 'user', $text);
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
    'summary' => "Cliente: {$text}\nBot: {$reply}",
    'createdBy' => 'telegram-webhook',
    'createdAt' => date('c'),
    'updatedAt' => date('c'),
];

writeData($data);

http_response_code(200);
