<?php

return [
    'rate_limits' => [
        'register' => [
            'max_attempts' => 5,
            'decay_minutes' => 1,
        ],
        'login' => [
            'max_attempts' => 5,
            'decay_minutes' => 1,
        ],
    ],

    'pagination' => [
        'default_per_page' => 20,
        'max_per_page' => 100,
    ],
];
