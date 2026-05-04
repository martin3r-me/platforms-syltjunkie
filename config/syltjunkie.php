<?php

return [
    'routing' => [
        'mode' => env('SYLTJUNKIE_MODE', 'path'),
        'prefix' => 'syltjunkie',
    ],

    'guard' => 'web',

    'navigation' => [
        'route' => 'syltjunkie.dashboard',
        'icon'  => 'heroicon-o-globe-alt',
        'order' => 110,
    ],

    'sidebar' => [
        [
            'group' => 'Entity Graph',
            'items' => [
                [
                    'label' => 'Dashboard',
                    'route' => 'syltjunkie.dashboard',
                    'icon'  => 'heroicon-o-globe-alt',
                ],
                [
                    'label' => 'Entities',
                    'route' => 'syltjunkie.entities.index',
                    'icon'  => 'heroicon-o-building-storefront',
                ],
                [
                    'label' => 'Entity Types',
                    'route' => 'syltjunkie.entity-types.index',
                    'icon'  => 'heroicon-o-tag',
                ],
            ],
        ],
    ],
];
