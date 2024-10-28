<?php

return [
   
    'account_sid' => env('TWILIO_ACCOUNT_SID'),
    'auth_token' => env('TWILIO_AUTH_TOKEN'),
    'from_number' => env('TWILIO_FROM_NUMBER'),

    //templates
    'templates' => [
        'order_confirmation' => [
            'sid' => 'HX350d429d32e64a552466cafecbe95f3c',
            'name' => 'order_confirmation',
            'language' => 'en',
            'content' => 'Your order has been confirmed. Your delivery is scheduled for {{1}} at {{2}}.',
            'components' => ['1' => 'date', '2' => 'time']
        ],
        'delivery_update' => [
            'sid' => 'HX123456789abcdef123456789abcdef12',
            'name' => 'delivery_update',
            'language' => 'en',
            'content' => 'Your delivery status has been updated to {{1}}.',
            'components' => ['1' => 'status']
        ],
        'payment_received' => [
            'sid' => 'HX987654321abcdef123456789abcdef12',
            'name' => 'payment_received',
            'language' => 'en',
            'content' => 'We have received your payment of {{1}}. Thank you!',
            'components' => ['1' => 'amount']
        ],
        'appointment_reminder' => [
            'sid' => 'HXabcdef123456789abcdef123456789ab',
            'name' => 'appointment_reminder',
            'language' => 'en',
            'content' => 'Reminder: Your appointment is scheduled for {{1}} at {{2}}.',
            'components' => ['1' => 'date', '2' => 'time']
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