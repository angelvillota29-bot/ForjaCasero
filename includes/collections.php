<?php
// Definición única (server + cliente la consume vía api/collections/schema.php)
// de cada módulo tipo Forja+ que Forja Casero replica con código propio,
// sin depender de ninguna licencia de terceros. Cada registro pertenece a
// un bot (botId).

function collectionSchemas(): array {
    return [
        'conversations' => [
            'label' => 'Conversaciones',
            'icon' => 'chat',
            'description' => 'Registro manual de conversaciones con clientes (resumen, no el chat en vivo del bot).',
            'fields' => [
                'customer'  => ['label' => 'Cliente', 'type' => 'text', 'required' => true],
                'channel'   => ['label' => 'Canal', 'type' => 'select', 'options' => ['whatsapp' => 'WhatsApp', 'telegram' => 'Telegram', 'web' => 'Chat del sitio', 'otro' => 'Otro'], 'default' => 'whatsapp'],
                'sentiment' => ['label' => 'Estado de ánimo', 'type' => 'select', 'options' => ['contento' => 'Contento', 'neutral' => 'Neutral', 'frustrado' => 'Frustrado'], 'default' => 'neutral'],
                'summary'   => ['label' => 'Resumen', 'type' => 'textarea'],
            ],
        ],
        'leads' => [
            'label' => 'Leads',
            'icon' => 'user-plus',
            'description' => 'Clientes potenciales captados por este bot.',
            'fields' => [
                'name'    => ['label' => 'Nombre', 'type' => 'text', 'required' => true],
                'contact' => ['label' => 'Contacto (teléfono / correo)', 'type' => 'text'],
                'channel' => ['label' => 'Canal', 'type' => 'select', 'options' => ['whatsapp' => 'WhatsApp', 'telegram' => 'Telegram', 'web' => 'Chat del sitio', 'otro' => 'Otro'], 'default' => 'whatsapp'],
                'status'  => ['label' => 'Estado', 'type' => 'select', 'options' => ['nuevo' => 'Nuevo', 'contactado' => 'Contactado', 'ganado' => 'Ganado', 'perdido' => 'Perdido'], 'default' => 'nuevo'],
                'note'    => ['label' => 'Nota', 'type' => 'textarea'],
            ],
        ],
        'payments' => [
            'label' => 'Cobros',
            'icon' => 'dollar',
            'description' => 'Cobros y pagos pendientes o realizados asociados a este bot.',
            'fields' => [
                'concept'  => ['label' => 'Concepto', 'type' => 'text', 'required' => true],
                'customer' => ['label' => 'Cliente', 'type' => 'text'],
                'amount'   => ['label' => 'Monto', 'type' => 'number'],
                'currency' => ['label' => 'Moneda', 'type' => 'select', 'options' => ['$' => '$', '€' => '€', 'R$' => 'R$'], 'default' => '$'],
                'status'   => ['label' => 'Estado', 'type' => 'select', 'options' => ['pendiente' => 'Pendiente', 'pagado' => 'Pagado', 'vencido' => 'Vencido'], 'default' => 'pendiente'],
                'dueDate'  => ['label' => 'Fecha límite', 'type' => 'date'],
            ],
        ],
        'tickets' => [
            'label' => 'Tickets',
            'icon' => 'ticket',
            'description' => 'Casos que el bot no pudo resolver solo y quedaron pendientes de un humano.',
            'fields' => [
                'subject'     => ['label' => 'Asunto', 'type' => 'text', 'required' => true],
                'customer'    => ['label' => 'Cliente', 'type' => 'text'],
                'priority'    => ['label' => 'Prioridad', 'type' => 'select', 'options' => ['baja' => 'Baja', 'media' => 'Media', 'alta' => 'Alta'], 'default' => 'media'],
                'status'      => ['label' => 'Estado', 'type' => 'select', 'options' => ['abierto' => 'Abierto', 'en_progreso' => 'En progreso', 'cerrado' => 'Cerrado'], 'default' => 'abierto'],
                'description' => ['label' => 'Descripción', 'type' => 'textarea'],
            ],
        ],
        'reviews' => [
            'label' => 'Reseñas',
            'icon' => 'star',
            'description' => 'Opiniones de clientes sobre este bot o negocio.',
            'fields' => [
                'customer' => ['label' => 'Cliente', 'type' => 'text'],
                'rating'   => ['label' => 'Calificación', 'type' => 'select', 'options' => ['5' => '5 ★', '4' => '4 ★', '3' => '3 ★', '2' => '2 ★', '1' => '1 ★'], 'default' => '5'],
                'channel'  => ['label' => 'Canal', 'type' => 'select', 'options' => ['whatsapp' => 'WhatsApp', 'telegram' => 'Telegram', 'web' => 'Chat del sitio', 'otro' => 'Otro'], 'default' => 'whatsapp'],
                'comment'  => ['label' => 'Comentario', 'type' => 'textarea'],
            ],
        ],
        'campaigns' => [
            'label' => 'Campañas',
            'icon' => 'megaphone',
            'description' => 'Campañas de mensajes o promociones planeadas para este bot.',
            'fields' => [
                'name'      => ['label' => 'Nombre', 'type' => 'text', 'required' => true],
                'channel'   => ['label' => 'Canal', 'type' => 'select', 'options' => ['whatsapp' => 'WhatsApp', 'telegram' => 'Telegram', 'web' => 'Chat del sitio', 'otro' => 'Otro'], 'default' => 'whatsapp'],
                'status'    => ['label' => 'Estado', 'type' => 'select', 'options' => ['planeada' => 'Planeada', 'activa' => 'Activa', 'finalizada' => 'Finalizada'], 'default' => 'planeada'],
                'startDate' => ['label' => 'Inicio', 'type' => 'date'],
                'endDate'   => ['label' => 'Fin', 'type' => 'date'],
                'notes'     => ['label' => 'Notas', 'type' => 'textarea'],
            ],
        ],
        'templates' => [
            'label' => 'Plantillas',
            'icon' => 'file-text',
            'description' => 'Mensajes reutilizables para respuestas rápidas o campañas.',
            'fields' => [
                'name'     => ['label' => 'Nombre', 'type' => 'text', 'required' => true],
                'category' => ['label' => 'Categoría', 'type' => 'text'],
                'content'  => ['label' => 'Contenido del mensaje', 'type' => 'textarea', 'required' => true],
            ],
        ],
        'knowledge' => [
            'label' => 'Conocimiento',
            'icon' => 'book',
            'description' => 'Base de conocimiento del negocio: lo que el bot debería saber.',
            'fields' => [
                'title'   => ['label' => 'Título', 'type' => 'text', 'required' => true],
                'tags'    => ['label' => 'Etiquetas (separadas por coma)', 'type' => 'text'],
                'content' => ['label' => 'Contenido', 'type' => 'textarea', 'required' => true],
            ],
        ],
        'improvements' => [
            'label' => 'Mejoras',
            'icon' => 'sparkles',
            'description' => 'Cosas que el bot no supo responder o se podrían mejorar.',
            'fields' => [
                'text'   => ['label' => 'Qué mejorar', 'type' => 'textarea', 'required' => true],
                'status' => ['label' => 'Estado', 'type' => 'select', 'options' => ['pendiente' => 'Pendiente', 'aplicada' => 'Aplicada', 'descartada' => 'Descartada'], 'default' => 'pendiente'],
            ],
        ],
        'vault' => [
            'label' => 'Bóveda',
            'icon' => 'lock',
            'description' => 'Notas, credenciales o enlaces sensibles guardados para este bot. El valor nunca se vuelve a mostrar completo.',
            'fields' => [
                'title' => ['label' => 'Título', 'type' => 'text', 'required' => true],
                'type'  => ['label' => 'Tipo', 'type' => 'select', 'options' => ['nota' => 'Nota', 'credencial' => 'Credencial', 'enlace' => 'Enlace'], 'default' => 'nota'],
                'value' => ['label' => 'Valor', 'type' => 'text', 'secret' => true],
                'note'  => ['label' => 'Nota adicional', 'type' => 'textarea'],
            ],
        ],
    ];
}

function isValidCollection(string $key): bool {
    return array_key_exists($key, collectionSchemas());
}

function collectionFields(string $key): array {
    return collectionSchemas()[$key]['fields'] ?? [];
}
