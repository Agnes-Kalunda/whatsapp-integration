<?php 

return [
    'account_sid' => env('WHATSAPP_ACCOUNT_SID', 'default-account-sid'),
    'auth_token' => env('WHATSAPP_AUTH_TOKEN', 'default-auth-token'),
    'from_number' => env('WHATSAPP_FROM_NUMBER', '+1234567890'),
    'timeout' => env('WHATSAPP_TIMEOUT', 30),
];

