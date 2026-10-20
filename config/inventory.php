<?php

return [
    'role_permissions' => [
        'warehouse' => ['inventory.view', 'inventory.dispatch', 'inventory.return', 'inventory.departure.issue', 'inventory.departure.revoke', 'events.view', 'events.prepare', 'purchases.view', 'purchases.receive'],
        'purchasing' => ['inventory.view', 'purchases.view', 'purchases.create', 'purchases.receive'],
        'technician' => ['events.view', 'mobile.receive', 'mobile.record', 'mobile.correct_own', 'mobile.incident', 'mobile.return', 'mobile.attach', 'mobile.close'],
        'user' => ['mobile.receive', 'mobile.record', 'mobile.correct_own', 'mobile.incident', 'mobile.return', 'mobile.attach', 'mobile.close'],
        'supervisor' => ['inventory.view', 'inventory.adjust', 'inventory.dispatch', 'inventory.return', 'inventory.departure.issue', 'inventory.departure.revoke', 'inventory.override_protected', 'events.view', 'events.prepare', 'audit.view'],
        'management' => ['sales.view', 'inventory.view', 'purchases.view', 'events.view', 'audit.view'],
        'sales' => ['sales.view', 'sales.manage', 'inventory.view', 'events.view', 'events.manage'],
    ],
    'scoring' => ['base' => 100, 'high_stock' => 20, 'near_value' => 15, 'protected' => -30, 'low_stock' => -20, 'outside_value' => -15, 'variety' => 10, 'rotation' => 10],
];
