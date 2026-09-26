<?php
// Todo el estado vive en un único data/data.json, igual que el patrón que
// ya usas en The-House-Club. Lectura/escritura con flock para evitar que
// dos peticiones simultáneas se pisen.

function dataFilePath(): string {
    // Fuera del docroot a propósito (ver Dockerfile) para que data.json
    // -- que guarda llaves API en texto plano -- nunca sea descargable
    // por HTTP. DATA_DIR permite apuntar a otro sitio si hace falta.
    $dir = getenv('DATA_DIR') ?: '/var/www/data';
    return rtrim($dir, '/') . '/data.json';
}

function defaultData(): array {
    $seedEmail = getenv('SEED_ADMIN_EMAIL') ?: '';
    return [
        'settings' => [
            'sharedApiKey' => '',
            'allowedEmails' => $seedEmail !== '' ? [$seedEmail] : [],
            'googleClientSecret' => '',
        ],
        'bots' => [],
        'records' => defaultRecords(),
        'chatHistory' => [],
        'videoAssets' => [
            'logoPath' => '',
            'musicPath' => '',
        ],
        'videoProjects' => [],
    ];
}

// Carpeta de medios (clips crudos, assets de marca, videos renderizados).
// Fuera del docroot igual que data.json; se sirve por api/videos/file.php
// con autenticación, nunca por URL directa.
function mediaDir(): string {
    $dir = getenv('DATA_DIR') ?: '/var/www/data';
    return rtrim($dir, '/') . '/media';
}

function defaultRecords(): array {
    require_once __DIR__ . '/collections.php';
    $records = [];
    foreach (array_keys(collectionSchemas()) as $key) {
        $records[$key] = [];
    }
    return $records;
}

function readData(): array {
    $file = dataFilePath();
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    if (!file_exists($file)) {
        $data = defaultData();
        writeData($data);
        return $data;
    }
    $raw = file_get_contents($file);
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return defaultData();
    }
    // Rellena llaves que pudieran faltar si el archivo es de una versión anterior.
    $data += defaultData();
    $data['settings'] = ($data['settings'] ?? []) + defaultData()['settings'];
    $data['bots'] = $data['bots'] ?? [];
    $data['records'] = ($data['records'] ?? []) + defaultRecords();
    foreach (defaultRecords() as $key => $empty) {
        $data['records'][$key] = $data['records'][$key] ?? [];
    }
    $data['chatHistory'] = $data['chatHistory'] ?? [];
    $data['videoAssets'] = ($data['videoAssets'] ?? []) + defaultData()['videoAssets'];
    $data['videoProjects'] = $data['videoProjects'] ?? [];
    return $data;
}

function writeData(array $data): bool {
    $file = dataFilePath();
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $fp = fopen($file, 'c+');
    if (!$fp) {
        return false;
    }
    $ok = false;
    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        rewind($fp);
        $ok = fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
    return $ok;
}

function jsonInput(): array {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function sendJson($payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function keyHint(string $value): ?string {
    if ($value === '') {
        return null;
    }
    $len = strlen($value);
    return $len <= 4 ? str_repeat('•', $len) : '••••' . substr($value, -4);
}
