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

    'keyword_discovery' => [
        'seed_keywords' => [
            'sylt', 'sylt urlaub', 'sylt restaurant', 'sylt hotel',
            'sylt ferienwohnung', 'sylt strand', 'sylt aktivitäten',
            'sylt shopping', 'sylt veranstaltungen', 'sylt anreise',
            'sylt wetter', 'sylt tipps', 'westerland', 'kampen sylt',
            'list sylt', 'hörnum', 'keitum',
            'sylt brunch', 'sylt fahrrad', 'sylt hund', 'sylt kinder',
            'sylt nachtleben', 'sylt kunst', 'sylt museum', 'sylt wellness',
            'rantum', 'morsum', 'wenningstedt', 'sylt nordsee',
        ],
        'min_volume' => 50,
        'max_keywords_per_seed' => 100,
        'opportunity_min_volume' => 200,
        'intent_patterns' => [
            'transactional' => ['buchen', 'kaufen', 'bestellen', 'mieten', 'reservieren', 'ticket', 'preis', 'kosten', 'günstig', 'angebot'],
            'commercial' => ['beste', 'top', 'vergleich', 'empfehlung', 'bewertung', 'test', 'erfahrung'],
            'navigational' => ['öffnungszeiten', 'adresse', 'telefon', 'kontakt', 'anfahrt', 'karte', 'standort'],
        ],
        'topic_patterns' => [
            'gastronomie' => ['restaurant', 'café', 'cafe', 'bar', 'essen', 'fisch', 'brötchen', 'frühstück', 'dining'],
            'unterkunft' => ['hotel', 'ferienwohnung', 'fewo', 'pension', 'camping', 'übernachtung', 'zimmer'],
            'strand_natur' => ['strand', 'düne', 'watt', 'meer', 'wandern', 'radfahren', 'natur', 'kliff'],
            'aktivitäten' => ['surf', 'yoga', 'golf', 'reiten', 'spa', 'wellness', 'sport', 'tauchen'],
            'events' => ['veranstaltung', 'konzert', 'festival', 'markt', 'silvester', 'weihnacht'],
            'shopping' => ['shopping', 'boutique', 'mode', 'outlet', 'laden', 'geschäft'],
            'anreise' => ['autozug', 'fähre', 'flug', 'bahn', 'anreise', 'parken'],
        ],
        'google_trends' => [
            'min_volume_for_trends' => 200,
            'time_range' => 'past_12_months',
            'surge_threshold' => 0.5,
            'surge_action_threshold' => 1.0,
        ],
    ],

    'shop' => [
        'enabled' => env('SYLTJUNKIE_SHOP_ENABLED', true),
        'currency' => 'EUR',
        'order_number_prefix' => 'SJ',
        'shipping_flat_cents' => 490,
        'free_shipping_from_cents' => 5000,
    ],

    'weather' => [
        'enabled' => env('SYLTJUNKIE_WEATHER_ENABLED', true),
        'api_base_url' => 'https://api.brightsky.dev',
        'forecast_days' => 7,
        'request_delay_ms' => 1000,
        'retention_days' => 365,
    ],

    'owner_auth' => [
        'enabled' => env('SYLTJUNKIE_OWNER_AUTH_ENABLED', true),
        'token_ttl_minutes' => 30,
        'session_ttl_hours' => 24,
        'rate_limit_per_hour' => 3,
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
