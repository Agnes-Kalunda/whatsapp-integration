<?php 

return [
    'api_key' => env('WHATSAPP_API_KEY'),
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    'timeout' => env('WHATSAPP_TIMEOUT', 30),
    'webhook_verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
];

