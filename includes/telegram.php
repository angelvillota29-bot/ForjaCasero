<?php
// Helpers para hablar con la API de Telegram y con OpenAI por HTTPS puro
// (file_get_contents + stream context), sin depender de la extensión curl.

function httpPostJson(string $url, array $body, array $headers = [], int $timeout = 25): array {
    $payload = json_encode($body);
    $headerLines = array_merge(['Content-Type: application/json'], $headers);
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headerLines),
            'content' => $payload,
            'timeout' => $timeout,
            'ignore_errors' => true,
        ],
    ]);
    $raw = @file_get_contents($url, false, $context);
    $status = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $status = (int) $m[1];
    }
    $decoded = $raw !== false ? json_decode($raw, true) : null;
    return ['status' => $status, 'body' => is_array($decoded) ? $decoded : [], 'raw' => $raw];
}

function httpGetJson(string $url, int $timeout = 15): array {
    $context = stream_context_create(['http' => ['timeout' => $timeout, 'ignore_errors' => true]]);
    $raw = @file_get_contents($url, false, $context);
    $decoded = $raw !== false ? json_decode($raw, true) : null;
    return is_array($decoded) ? $decoded : [];
}

function telegramApiGet(string $token, string $method, array $params = []): array {
    $url = "https://api.telegram.org/bot{$token}/{$method}";
    if ($params) {
        $url .= '?' . http_build_query($params);
    }
    return httpGetJson($url);
}

function telegramApiPost(string $token, string $method, array $params = []): array {
    $url = "https://api.telegram.org/bot{$token}/{$method}";
    $result = httpPostJson($url, $params);
    return $result['body'];
}

function resolveOpenAiKey(array $bot, array $settings): string {
    if (($bot['keyMode'] ?? 'shared') === 'own') {
        return $bot['ownApiKey'] ?? '';
    }
    return $settings['sharedApiKey'] ?? '';
}

function openaiChat(string $apiKey, string $model, array $messages): ?string {
    $result = httpPostJson('https://api.openai.com/v1/chat/completions', [
        'model' => $model !== '' ? $model : 'gpt-4o-mini',
        'messages' => $messages,
        'temperature' => 0.6,
    ], ['Authorization: Bearer ' . $apiKey]);

    return $result['body']['choices'][0]['message']['content'] ?? null;
}

// Telegram: resuelve un file_id a la URL real de descarga del archivo.
function telegramFileUrl(string $token, string $fileId): ?string {
    $info = telegramApiGet($token, 'getFile', ['file_id' => $fileId]);
    $path = $info['result']['file_path'] ?? null;
    return $path ? "https://api.telegram.org/file/bot{$token}/{$path}" : null;
}

function downloadFileBytes(string $url, int $timeout = 25): ?string {
    $context = stream_context_create(['http' => ['timeout' => $timeout, 'ignore_errors' => true]]);
    $raw = @file_get_contents($url, false, $context);
    return $raw !== false ? $raw : null;
}

// multipart/form-data manual (sin curl) para subir archivos -- lo usa Whisper.
function httpPostMultipart(string $url, array $fields, string $fileField, string $fileContent, string $fileName, string $mimeType, array $headers = [], int $timeout = 30): array {
    $boundary = '----ForjaCasero' . bin2hex(random_bytes(8));
    $body = '';
    foreach ($fields as $key => $value) {
        $body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$key}\"\r\n\r\n{$value}\r\n";
    }
    $body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$fileField}\"; filename=\"{$fileName}\"\r\n";
    $body .= "Content-Type: {$mimeType}\r\n\r\n{$fileContent}\r\n";
    $body .= "--{$boundary}--\r\n";

    $headerLines = array_merge(["Content-Type: multipart/form-data; boundary={$boundary}"], $headers);
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headerLines),
            'content' => $body,
            'timeout' => $timeout,
            'ignore_errors' => true,
        ],
    ]);
    $raw = @file_get_contents($url, false, $context);
    $decoded = $raw !== false ? json_decode($raw, true) : null;
    return is_array($decoded) ? $decoded : [];
}

function openaiTranscribeAudio(string $apiKey, string $audioBytes, string $fileName, string $mimeType): ?string {
    $result = httpPostMultipart(
        'https://api.openai.com/v1/audio/transcriptions',
        ['model' => 'whisper-1'],
        'file',
        $audioBytes,
        $fileName,
        $mimeType,
        ['Authorization: Bearer ' . $apiKey],
        40
    );
    return $result['text'] ?? null;
}
