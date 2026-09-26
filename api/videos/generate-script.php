<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/telegram.php';

requireAuth();

$input = jsonInput();
$id = $input['id'] ?? '';
$data = readData();

$project = null;
foreach ($data['videoProjects'] as &$p) {
    if ($p['id'] === $id) { $project = &$p; break; }
}
unset($p);
if ($project === null) {
    sendJson(['success' => false, 'error' => 'Proyecto no encontrado'], 404);
}

$apiKey = $data['settings']['sharedApiKey'] ?? '';
if ($apiKey === '') {
    sendJson(['success' => false, 'error' => 'Configura la llave de OpenAI en Conexiones primero'], 400);
}

$pillarLabels = [
    'dolor' => 'el dolor/problema que tiene el dueño de negocio (mensajes sin responder, desorganización, etc.)',
    'demo' => 'una demostración de un bot o automatización funcionando en tiempo real',
    'educativo' => 'un tip educativo corto sobre cuándo/por qué automatizar',
    'resultado' => 'un resultado o beneficio concreto logrado con una automatización',
    'cta' => 'una invitación directa a agendar una demo gratis',
];
$pillarDesc = $pillarLabels[$project['pillar']] ?? $pillarLabels['dolor'];

$system = <<<EOT
Eres copywriter de marketing para "Ángel Automatizaciones", un negocio que crea
chatbots con IA y automatizaciones (WhatsApp, Telegram, Gmail, Google Calendar)
para pymes. Escribes en español neutro/colombiano, tono cercano y directo, cero
relleno corporativo. El video es corto (formato Reels/TikTok, 15-30s) y combina
screen-recordings reales de los bots funcionando.

Responde SOLO un JSON con esta forma exacta, sin texto extra:
{"hook": "...", "script": "...", "cta": "..."}

- hook: la frase de la tarjeta de apertura (máx 12 palabras, con gancho fuerte).
- script: 2-3 frases que el dueño puede decir o poner como texto mientras se ve
  el screen-recording (explica el problema y la solución).
- cta: la frase de la tarjeta final (máx 10 palabras, invita a escribir por WhatsApp).
EOT;

$userMsg = "El video trata sobre: {$pillarDesc}.";
if (!empty($project['title'])) {
    $userMsg .= " Título de referencia del post: \"{$project['title']}\".";
}

$reply = openaiChat($apiKey, 'gpt-4o-mini', [
    ['role' => 'system', 'content' => $system],
    ['role' => 'user', 'content' => $userMsg],
], 40);

$parsed = null;
if ($reply) {
    $clean = trim($reply);
    $clean = preg_replace('/^```(json)?\s*|```$/m', '', $clean);
    $parsed = json_decode(trim($clean), true);
}

if (!is_array($parsed) || empty($parsed['hook'])) {
    sendJson(['success' => false, 'error' => 'La IA no devolvió un guion válido, intenta de nuevo'], 502);
}

$project['hook'] = trim($parsed['hook']);
$project['script'] = trim($parsed['script'] ?? '');
$project['cta'] = trim($parsed['cta'] ?? '');
$project['updatedAt'] = date('c');

writeData($data);

sendJson(['success' => true, 'hook' => $project['hook'], 'script' => $project['script'], 'cta' => $project['cta']]);
