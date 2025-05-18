<?php
return [
    // System Settings
    'system' => [
        'barangay_name' => '',
        'city_municipality' => '',
        'province' => '',
        'region' => '',
        'notifications' => [
            'email' => true,
            'sms' => true,
            'push' => false
        ],
        'backup' => [
            'frequency' => 'weekly',
            'location' => '',
            'auto_backup' => true
        ]
    ],

    // SMS Gateway Settings
    'sms_gateway' => [
        'provider' => 'semaphore',
        'semaphore' => [
            'api_key' => '',
            'sender_name' => ''
        ],
        'twilio' => [
            'account_sid' => '',
            'auth_token' => '',
            'phone_number' => ''
        ],
        'enabled' => true,
        'test_mode' => false,
        'test_number' => ''
    ],

    // User Profile Settings
    'profile' => [
        'first_name' => '',
        'last_name' => '',
        'email' => '',
        'position' => '',
        'phone' => '',
        'photo' => ''
    ],

    // Security Settings
    'security' => [
        'password_requirements' => [
            'min_length' => 8,
            'require_uppercase' => true,
            'require_lowercase' => true,
            'require_numbers' => true,
            'require_special_chars' => true
        ],
        '2fa_enabled' => false
    ]
];
