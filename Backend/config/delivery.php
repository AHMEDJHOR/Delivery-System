<?php

return [
    'service_fee' => '20.00',

    'distance_rate_per_km' => '20.00',

    'maximum_delivery_fee' => '1020.00',

    'routing' => [
        'base_url' => env(
            'DELIVERY_ROUTING_BASE_URL',
            'https://router.project-osrm.org'
        ),
        'timeout_seconds' => 5,
    ],
];
