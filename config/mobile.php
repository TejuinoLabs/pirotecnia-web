<?php

return [
    'event_timezone' => env('MOBILE_EVENT_TIMEZONE', 'America/Mexico_City'),
    'page_size' => 20,
    'pyrotechnic_product_role_ids' => [1],
    'attachment_max_kb' => 10240,
    'attachment_retention_days' => 30,
    'attachment_mimes' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
    // Administrative milestones only. This snapshot never describes equipment activation.
    'protocol' => [
        'version' => 1,
        'title' => 'Bitácora operativa',
        'steps' => [
            ['title' => 'Llegada al lugar', 'instructions' => 'Registrar la llegada del responsable asignado.', 'required' => true],
            ['title' => 'Recepción revisada', 'instructions' => 'Registrar la revisión administrativa de la recepción y sus diferencias.', 'required' => true],
            ['title' => 'Conclusión de actividades', 'instructions' => 'Registrar la conclusión administrativa y las observaciones del responsable.', 'required' => true],
        ],
    ],
];
