<?php

return [
    'currency' => 'GHS',
    'billing_period_months' => 1,

    'plans' => [
        'starter' => [
            'name' => 'Starter',
            'price_minor' => 9900,
            'event_limit' => 3,
            'participant_limit' => 500,
            'description' => 'For smaller organisations running focused events.',
            'features' => [
                'Up to 3 active events',
                'Up to 500 participants per event',
                'QR and manual check-in',
                'Daily attendance reports',
                'Excel participant import',
            ],
        ],
        'business' => [
            'name' => 'Business',
            'price_minor' => 29900,
            'event_limit' => 15,
            'participant_limit' => 5000,
            'featured' => true,
            'description' => 'For growing teams that need more control and visibility.',
            'features' => [
                'Up to 15 active events',
                'Up to 5,000 participants per event',
                'Multiple manager accounts',
                'Advanced summary exports',
                'Priority support',
            ],
        ],
        'enterprise' => [
            'name' => 'Enterprise',
            'price_minor' => 79900,
            'event_limit' => 50,
            'participant_limit' => 25000,
            'description' => 'For large organisations with advanced operating needs.',
            'features' => [
                'Up to 50 active events',
                'Up to 25,000 participants per event',
                'Multi-location support',
                'Tailored onboarding',
                'Priority support',
            ],
        ],
    ],
];
