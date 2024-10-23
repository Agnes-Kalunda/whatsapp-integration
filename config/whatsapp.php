<?php 

return [
    'account_sid' => env('TWILIO_ACCOUNT_SID', 'default-account-sid'),
    'auth_token' => env('TWILIO_AUTH_TOKEN', 'default-auth-token'),
    'from_number' => env('TWILIO_FROM_NUMBER', '+1234567890'),
    'timeout' => env('WHATSAPP_TIMEOUT', 30),
];

