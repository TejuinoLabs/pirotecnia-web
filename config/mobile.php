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
        'version' => 2,
        'title' => 'Bitácora operativa',
        'steps' => [
            ['milestone_code' => 'arrived', 'requires_notes' => true, 'title' => 'Llegada al lugar', 'instructions' => 'Registrar la llegada del responsable asignado.', 'required' => true],
            ['validation_rule' => 'receipt_review', 'title' => 'Recepción revisada', 'instructions' => 'Registrar la revisión administrativa de la recepción y sus diferencias.', 'required' => true],
            ['milestone_code' => 'installed', 'requires_notes' => true, 'title' => 'Instalación terminada', 'instructions' => 'Registrar que la instalación ya fue terminada y sus observaciones.', 'required' => true],
            ['milestone_code' => 'detonated', 'requires_notes' => true, 'title' => 'Detonación registrada', 'instructions' => 'Registrar el hecho ya realizado y sus observaciones. Este registro no activa equipos.', 'required' => true],
            ['milestone_code' => 'finished', 'requires_notes' => true, 'title' => 'Evento finalizado', 'instructions' => 'Registrar la conclusión de actividades y los comentarios finales.', 'required' => true],
        ],
    ],
];
