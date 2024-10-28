<?php

return [
   
    'account_sid' => env('TWILIO_ACCOUNT_SID'),
    'auth_token' => env('TWILIO_AUTH_TOKEN'),
    'from_number' => env('TWILIO_FROM_NUMBER'),

    //templates
    'templates' => [
        'verification_code' => [
            'sid' => 'HX229f5a04fd0510ce1b071852155d3e75',
            'name' => 'verification_code',
            'language' => 'en',
            'content' => '{{1}} is your verification code. For your security, do not share this code.',
            'components' => ['1' => 'code']
        ],
        'appointment_reminder' => [
            'sid' => 'HXb5b62575e6e4ff6129ad7c8efe1f983e',
            'name' => 'appointment_reminder',
            'content' => 'Your appointment is coming up on {{1}} at {{2}}',
            'components' => [
                '1' => 'date',
                '2' => 'time'
            ]
        ],
        'order_confirmation' => [
            'sid' => 'HX350d429d32e64a552466cafecbe95f3c',
            'name' => 'order_confirmation',
            'content' => 'Thank you for your order. Your delivery is scheduled for {{1}} at {{2}}. If you need to change it, please reply back and let us know.',
            'components' => [
                '1' => 'date',
                '2' => 'time'
        ]
        ]
    ],
    // rate limit
    'rate_limit' => [
        'enabled' => true,
        'max_requests_per_minute' => 60,
        'window' => 60,
    ],
    // webhook config
    'webhook' => [
        'signature_header' => 'X-Twilio-Signature',
        'validate_signature' => true,
    ],
];