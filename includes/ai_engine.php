<?php
require_once __DIR__ . '/google_api.php';

// El "cerebro" del bot: prompt estructurado (como el de Forja: Rol, Info del
// negocio, Frenos, Estilo), memoria de conversación por chat, y tools que la
// IA puede llamar (buscar conocimiento, capturar lead, escalar a humano) --
// leyendo/escribiendo directo en los módulos que ya tiene Forja Casero.

const MAX_HISTORY_MESSAGES = 16; // ~8 turnos, para no disparar el costo/tokens
const MAX_TOOL_ROUNDS = 3;

function buildSystemPrompt(array $bot): string {
    $lines = [];
    $lines[] = "Eres el asistente de '{$bot['name']}'" .
        (!empty($bot['niche']) ? ", un negocio de tipo {$bot['niche']}." : '.');
    if (!empty($bot['description'])) {
        $lines[] = $bot['description'];
    }

    $lines[] = "";
    date_default_timezone_set('America/Bogota');
    $lines[] = "Fecha y hora actual (America/Bogota): " . date('l j \d\e F Y, H:i');
    if (!empty($bot['googleRefreshToken'])) {
        $lines[] = "Tienes acceso a Google Calendar del dueño (crear_evento_calendario, consultar_calendario). Usa fechas ISO 8601 con offset -05:00.";
    }

    $lines[] = "";
    $lines[] = "INFO DEL NEGOCIO (única fuente de verdad para horarios, precios, ubicación, etc.):";
    $lines[] = !empty($bot['businessInfo']) ? $bot['businessInfo'] : "(el dueño todavía no cargó esta información)";

    if (!empty($bot['aiInstructions'])) {
        $lines[] = "";
        $lines[] = "INSTRUCCIONES DEL DUEÑO (se SUMAN a las reglas de abajo, no las reemplazan):";
        $lines[] = $bot['aiInstructions'];
    }

    $lines[] = "";
    $lines[] = "ESTILO:";
    $lines[] = "- Responde en español, breve y natural, como parte del equipo del negocio, no como un robot genérico.";
    $lines[] = "- Nunca uses asteriscos, negritas ni símbolos de formato Markdown, ni emojis en exceso.";

    $lines[] = "";
    $lines[] = "FRENOS (anti-alucinación, no negociables):";
    $lines[] = "- NUNCA inventes precios, horarios, disponibilidad ni datos que no estén en 'INFO DEL NEGOCIO' o en lo que devuelva buscar_conocimiento.";
    $lines[] = "- Si no sabes algo con certeza, dilo claramente ('no tengo ese dato, déjame confirmarlo') en vez de adivinar.";
    $lines[] = "- Si el cliente pide hablar con una persona, se queja fuerte, o preguntas algo que no puedes resolver en 2-3 intentos, usa la herramienta escalar_a_humano.";
    $lines[] = "- Si el cliente muestra interés real (quiere comprar, agendar, cotizar), usa la herramienta capturar_lead con su nombre y contacto.";
    $lines[] = "- Antes de responder algo que no sepas de memoria sobre el negocio, usa buscar_conocimiento.";

    return implode("\n", $lines);
}

function toolDefinitions(array $bot): array {
    $tools = [
        [
            'type' => 'function',
            'function' => [
                'name' => 'buscar_conocimiento',
                'description' => 'Busca en la base de conocimiento del negocio (documentos que el dueño cargó) información para responder con precisión.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['consulta' => ['type' => 'string', 'description' => 'Qué buscar, en pocas palabras']],
                    'required' => ['consulta'],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'capturar_lead',
                'description' => 'Guarda a un cliente interesado (lead) para que el dueño le dé seguimiento después.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'nombre' => ['type' => 'string'],
                        'contacto' => ['type' => 'string', 'description' => 'Teléfono o correo si lo dio'],
                        'nota' => ['type' => 'string', 'description' => 'Qué le interesa'],
                    ],
                    'required' => ['nombre'],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'escalar_a_humano',
                'description' => 'Crea un ticket para que el dueño o el equipo atienda personalmente esta conversación.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'motivo' => ['type' => 'string'],
                        'resumen' => ['type' => 'string', 'description' => 'Resumen breve de la conversación hasta ahora'],
                        'prioridad' => ['type' => 'string', 'enum' => ['baja', 'media', 'alta']],
                    ],
                    'required' => ['motivo'],
                ],
            ],
        ],
    ];

    if (!empty($bot['googleRefreshToken'])) {
        $tools[] = [
            'type' => 'function',
            'function' => [
                'name' => 'crear_evento_calendario',
                'description' => 'Crea un evento en el Google Calendar del dueño.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'titulo' => ['type' => 'string'],
                        'inicio' => ['type' => 'string', 'description' => 'Fecha y hora de inicio en formato ISO 8601 con zona horaria, ej. 2026-09-24T15:00:00-05:00'],
                        'fin' => ['type' => 'string', 'description' => 'Fecha y hora de fin en formato ISO 8601 con zona horaria'],
                        'descripcion' => ['type' => 'string'],
                    ],
                    'required' => ['titulo', 'inicio', 'fin'],
                ],
            ],
        ];
        $tools[] = [
            'type' => 'function',
            'function' => [
                'name' => 'consultar_calendario',
                'description' => 'Consulta los próximos eventos del Google Calendar del dueño.',
                'parameters' => ['type' => 'object', 'properties' => new stdClass()],
            ],
        ];
    }

    return $tools;
}

function searchKnowledge(array $data, string $botId, string $query): string {
    $docs = array_values(array_filter($data['records']['knowledge'] ?? [], fn($d) => ($d['botId'] ?? '') === $botId));
    if (!$docs) {
        return 'La base de conocimiento de este bot todavía está vacía.';
    }
    $terms = array_filter(preg_split('/\s+/', mb_strtolower($query)), fn($t) => mb_strlen($t) > 2);
    if (!$terms) {
        $terms = [mb_strtolower($query)];
    }
    $scored = [];
    foreach ($docs as $doc) {
        $haystack = mb_strtolower(($doc['title'] ?? '') . ' ' . ($doc['tags'] ?? '') . ' ' . ($doc['content'] ?? ''));
        $score = 0;
        foreach ($terms as $term) {
            if ($term !== '' && mb_strpos($haystack, $term) !== false) {
                $score++;
            }
        }
        if ($score > 0) {
            $scored[] = ['score' => $score, 'doc' => $doc];
        }
    }
    if (!$scored) {
        return 'No encontré nada relevante en la base de conocimiento sobre eso.';
    }
    usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
    $top = array_slice($scored, 0, 3);
    $out = [];
    foreach ($top as $t) {
        $content = mb_substr($t['doc']['content'] ?? '', 0, 600);
        $out[] = "### {$t['doc']['title']}\n{$content}";
    }
    return implode("\n\n", $out);
}

function addRecord(array &$data, string $collection, array $fields): void {
    $data['records'][$collection][] = array_merge($fields, [
        'id' => bin2hex(random_bytes(8)),
        'createdBy' => 'ai-agent',
        'createdAt' => date('c'),
        'updatedAt' => date('c'),
    ]);
}

function executeTool(string $name, array $args, string $botId, array &$data, array $bot): string {
    if ($name === 'crear_evento_calendario') {
        $token = getGoogleAccessToken($bot, $data['settings']);
        if ($token === null) {
            return 'El dueño todavía no conectó Google Calendar.';
        }
        $result = calendarCreateEvent($token, $args['titulo'] ?? 'Evento', $args['inicio'] ?? '', $args['fin'] ?? '', $args['descripcion'] ?? '');
        if (!empty($result['id'])) {
            return 'Evento creado: ' . ($args['titulo'] ?? '') . ' (' . ($args['inicio'] ?? '') . ').';
        }
        return 'No se pudo crear el evento: ' . ($result['error']['message'] ?? 'error desconocido');
    }
    if ($name === 'consultar_calendario') {
        $token = getGoogleAccessToken($bot, $data['settings']);
        if ($token === null) {
            return 'El dueño todavía no conectó Google Calendar.';
        }
        $events = calendarListUpcoming($token, 8);
        if (!$events) {
            return 'No hay eventos próximos.';
        }
        $lines = [];
        foreach ($events as $ev) {
            $when = $ev['start']['dateTime'] ?? $ev['start']['date'] ?? '';
            $lines[] = "- {$ev['summary']} ({$when})";
        }
        return implode("\n", $lines);
    }
    if ($name === 'buscar_conocimiento') {
        return searchKnowledge($data, $botId, $args['consulta'] ?? '');
    }
    if ($name === 'capturar_lead') {
        addRecord($data, 'leads', [
            'botId' => $botId,
            'name' => $args['nombre'] ?? 'Sin nombre',
            'contact' => $args['contacto'] ?? '',
            'channel' => 'telegram',
            'status' => 'nuevo',
            'note' => $args['nota'] ?? '',
        ]);
        return 'Lead guardado correctamente.';
    }
    if ($name === 'escalar_a_humano') {
        addRecord($data, 'tickets', [
            'botId' => $botId,
            'subject' => $args['motivo'] ?? 'Escalado por el bot',
            'customer' => '',
            'priority' => in_array($args['prioridad'] ?? '', ['baja', 'media', 'alta']) ? $args['prioridad'] : 'media',
            'status' => 'abierto',
            'description' => $args['resumen'] ?? '',
        ]);
        return 'Listo, un humano va a revisar esta conversación.';
    }
    return 'Herramienta desconocida.';
}

function historyKey(string $botId, string $chatId): string {
    return $botId . ':' . $chatId;
}

function getChatHistory(array $data, string $botId, string $chatId): array {
    return $data['chatHistory'][historyKey($botId, $chatId)] ?? [];
}

function pushChatHistory(array &$data, string $botId, string $chatId, string $role, string $content): void {
    $key = historyKey($botId, $chatId);
    $data['chatHistory'][$key] = $data['chatHistory'][$key] ?? [];
    $data['chatHistory'][$key][] = ['role' => $role, 'content' => $content];
    if (count($data['chatHistory'][$key]) > MAX_HISTORY_MESSAGES) {
        $data['chatHistory'][$key] = array_slice($data['chatHistory'][$key], -MAX_HISTORY_MESSAGES);
    }
}

// Corre el loop de tool-calling de OpenAI. Devuelve el texto final para el
// cliente; muta $data si alguna tool escribió algo (el caller debe guardar).
function runAgent(string $apiKey, string $model, array $bot, string $botId, array &$data, array $history, string $userMessage, ?array $userContent = null): string {
    // $userContent, si viene, es un array de "content parts" estilo OpenAI
    // vision (texto + imagen) para el turno actual -- $userMessage sigue
    // siendo el texto plano que se guarda en el historial/log.
    $messages = array_merge(
        [['role' => 'system', 'content' => buildSystemPrompt($bot)]],
        $history,
        [['role' => 'user', 'content' => $userContent ?? $userMessage]]
    );

    $tools = toolDefinitions($bot);

    for ($round = 0; $round < MAX_TOOL_ROUNDS; $round++) {
        $result = httpPostJson('https://api.openai.com/v1/chat/completions', [
            'model' => $model !== '' ? $model : 'gpt-4o-mini',
            'messages' => $messages,
            'tools' => $tools,
            'temperature' => 0.6,
        ], ['Authorization: Bearer ' . $apiKey]);

        $message = $result['body']['choices'][0]['message'] ?? null;
        if ($message === null) {
            return 'Perdón, tuve un problema pensando la respuesta. ¿Puedes intentar de nuevo?';
        }

        $toolCalls = $message['tool_calls'] ?? null;
        if (!$toolCalls) {
            return trim($message['content'] ?? '') !== '' ? $message['content'] : 'Perdón, ¿puedes reformular tu pregunta?';
        }

        $messages[] = $message;
        foreach ($toolCalls as $call) {
            $fnName = $call['function']['name'] ?? '';
            $fnArgs = json_decode($call['function']['arguments'] ?? '{}', true) ?: [];
            $toolResult = executeTool($fnName, $fnArgs, $botId, $data, $bot);
            $messages[] = [
                'role' => 'tool',
                'tool_call_id' => $call['id'],
                'content' => $toolResult,
            ];
        }
    }

    return 'Perdón, esto se complicó más de la cuenta. Un momento que te ayuda alguien del equipo.';
}
