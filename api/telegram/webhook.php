<?php
// Endpoint PÚBLICO: lo llama Telegram, no un usuario logueado. Su única
// autenticación es el secret_token que Telegram repite en cada request
// (X-Telegram-Bot-Api-Secret-Token), generado al conectar el bot.
require_once __DIR__ . '/../../includes/storage.php';
require_once __DIR__ . '/../../includes/telegram.php';

set_time_limit(60);

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
$systemPrompt = "Eres el asistente de '{$bot['name']}'" .
    (!empty($bot['description']) ? ", un negocio de tipo {$bot['niche']}. {$bot['description']}" : '.') .
    (!empty($bot['aiInstructions']) ? "\n\nInstrucciones adicionales:\n{$bot['aiInstructions']}" : '') .
    "\n\nResponde en español, de forma breve y natural, como si fueras parte del negocio.";

$reply = openaiChat($apiKey, $bot['aiModel'] ?? 'gpt-4o-mini', [
    ['role' => 'system', 'content' => $systemPrompt],
    ['role' => 'user', 'content' => $text],
]);

if ($reply === null || $reply === '') {
    $reply = 'Perdón, tuve un problema pensando la respuesta. ¿Puedes intentar de nuevo?';
}

telegramApiPost($bot['telegramToken'], 'sendMessage', [
    'chat_id' => $chatId,
    'text' => $reply,
]);

// Deja registro en el módulo Conversaciones del bot (best-effort, no bloquea la respuesta).
$data = readData();
foreach ($data['bots'] as $b2) {
    if ($b2['id'] !== $botId) continue;
    $data['records']['conversations'][] = [
        'id' => bin2hex(random_bytes(8)),
        'botId' => $botId,
        'customer' => $fromName !== '' ? $fromName : ('Telegram #' . $chatId),
        'channel' => 'telegram',
        'sentiment' => 'neutral',
        'summary' => "Cliente: {$text}\nBot: {$reply}",
        'createdBy' => 'telegram-webhook',
        'createdAt' => date('c'),
        'updatedAt' => date('c'),
    ];
    writeData($data);
    break;
}

http_response_code(200);
