<?php

return [
    'name' => 'Issuance',
    'description' => 'Ausgabenverwaltung',
    'version' => '1.0.0',

    'routing' => [
        'prefix' => 'issuance',
        'middleware' => ['web', 'auth'],
    ],

    'guard' => 'web',

    'navigation' => [
        'main' => [
            'issuance' => [
                'title' => 'Ausgaben',
                'icon' => 'heroicon-o-archive-box',
                'route' => 'issuance.issues.index',
            ],
        ],
    ],
];
