<?php

return [
    'contacts' => [
        'phone' => env('SCHOOL_PHONE'),
        'email' => env('SCHOOL_EMAIL'),
        'address' => env('SCHOOL_ADDRESS'),
        'hours' => env('SCHOOL_HOURS'),
    ],
    'currency' => env('SCHOOL_CURRENCY', 'KZT'),
    'pass_percent' => 80,
];
