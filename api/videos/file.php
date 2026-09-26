<?php
// Sirve clips crudos y videos renderizados con autenticación (viven fuera
// del docroot, nunca por URL directa). Soporta Range para que el <video>
// del panel pueda reproducir/adelantar sin descargar todo de una vez.
require_once __DIR__ . '/../../includes/auth.php';
requireAuth();

$projectId = $_GET['project'] ?? '';
$kind = $_GET['kind'] ?? '';
$clipId = $_GET['clipId'] ?? '';

$data = readData();
$project = null;
foreach ($data['videoProjects'] as $p) {
    if ($p['id'] === $projectId) { $project = $p; break; }
}
if ($project === null) {
    http_response_code(404);
    exit;
}

$path = null;
if ($kind === 'output' && !empty($project['outputPath'])) {
    $path = $project['outputPath'];
} elseif ($kind === 'clip') {
    foreach ($project['clips'] as $c) {
        if ($c['id'] === $clipId) { $path = $c['path']; break; }
    }
}

if ($path === null || !file_exists($path)) {
    http_response_code(404);
    exit;
}

$size = filesize($path);
$start = 0;
$end = $size - 1;
header('Content-Type: video/mp4');
header('Accept-Ranges: bytes');

if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
    if ($m[1] !== '') $start = (int) $m[1];
    if ($m[2] !== '') $end = (int) $m[2];
    $end = min($end, $size - 1);
    http_response_code(206);
    header("Content-Range: bytes {$start}-{$end}/{$size}");
}

header('Content-Length: ' . ($end - $start + 1));

$fp = fopen($path, 'rb');
fseek($fp, $start);
$remaining = $end - $start + 1;
while ($remaining > 0 && !feof($fp)) {
    $chunk = min(8192, $remaining);
    echo fread($fp, $chunk);
    $remaining -= $chunk;
    flush();
}
fclose($fp);
