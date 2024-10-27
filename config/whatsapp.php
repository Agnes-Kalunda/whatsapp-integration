<?php

return [
   
    'account_sid' => env('TWILIO_ACCOUNT_SID'),
    'auth_token' => env('TWILIO_AUTH_TOKEN'),
    'from_number' => env('TWILIO_FROM_NUMBER'),

    //rate limiting
    'rate_limit' => [
        'enabled' => true,
        'max_requests_per_minute' => 60,
        'window' => 60,
    ],

    //webhook config
    'webhook' => [
        'signature_header' => 'X-Twilio-Signature',
        'validate_signature' => true,
    ],
];