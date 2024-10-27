# WhatsApp Integration

This is a Composer package that integrates basic WhatsApp features into Laravel apps using WhatsApp API with Twilio as the Business provider. This package handles the functionalities for sending WhatsApp messages and handling incoming messages in Laravel 5.8+ applications.

[![MIT Licensed](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
  - [Sending Messages](#sending-messages)
  - [Template Messages](#template-messages)
  - [Handling Webhooks](#handling-webhooks)
  - [API Endpoints](#api-endpoints)
- [Error Handling](#error-handling)
- [Testing](#testing)
- [Contributing](#contributing)
- [License](#license)

## Requirements

- PHP 7.1.3 or higher
- Laravel 5.8 or higher
- Twilio Account with WhatsApp capabilities
- Composer

## Installation

1. Install the package via Composer:

```bash
composer require chat/whatsapp-integration
```

2. Add the service provider in `config/app.php` (Laravel will auto-discover it):

```php
'providers' => [
    // ...
    Chat\WhatsappIntegration\WhatsAppIntegrationServiceProvider::class,
]
```

## Configuration

1. Publish the configuration file:

```bash
php artisan vendor:publish --provider="Chat\WhatsappIntegration\WhatsAppIntegrationServiceProvider" --tag="whatsapp-config"
```

2. Add the following to your `.env` file:

```env
TWILIO_ACCOUNT_SID=your_twilio_account_sid
TWILIO_AUTH_TOKEN=your_twilio_auth_token
TWILIO_FROM_NUMBER=your_whatsapp_number  # Format: +1234567890
```

3. Configuration file structure (`config/whatsapp.php`):

```php
return [
    /*
    |--------------------------------------------------------------------------
    | Twilio Configuration
    |--------------------------------------------------------------------------
    */
    'account_sid' => env('TWILIO_ACCOUNT_SID'),
    'auth_token' => env('TWILIO_AUTH_TOKEN'),
    'from_number' => env('TWILIO_FROM_NUMBER'),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */
    'rate_limit' => [
        'enabled' => true,
        'max_requests_per_minute' => 60,
        'window' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    */
    'webhook' => [
        'signature_header' => 'X-Twilio-Signature',
        'validate_signature' => true,
    ],
];
```

## Usage

### Sending Messages

Basic message sending:

```php
use Chat\WhatsappIntegration\WhatsApp;
use Chat\WhatsappIntegration\Exceptions\WhatsAppException;

public function sendMessage(WhatsApp $whatsapp)
{
    try {
        $result = $whatsapp->sendMessage(
            '+1234567890',  // recipient's number (E.164 format)
            'Hello from Laravel!'
        );
        
        // Success - $result is a Twilio MessageInstance
        $messageSid = $result->sid;
        $status = $result->status; // 'queued', 'sent', or 'delivered'
        
    } catch (WhatsAppException $e) {
        // Handle error...
        logger()->error('WhatsApp Error: ' . $e->getMessage());
    }
}
```

### Template Messages

Sending template messages:

```php
try {
    $result = $whatsapp->sendMessage(
        '+1234567890',
        'Your order has been confirmed',
        'HX350d429d32e64a552466cafecbe95f3c', // template ID
        json_encode(['1' => 'today', '2' => '3pm']) // variables
    );
    
    // Process response...
} catch (WhatsAppException $e) {
    // Handle error...
}
```

### Handling Webhooks

Process incoming WhatsApp messages:

```php
use Illuminate\Http\Request;

public function handleWebhook(Request $request, WhatsApp $whatsapp)
{
    try {
        $result = $whatsapp->handleWebhook(
            $request->all(),
            $request->fullUrl(),
            $request->header('X-Twilio-Signature')
        );
        
        // Process the message
        $messageBody = $result['Body'];
        $fromNumber = $result['From'];
        $mediaUrls = $result['MediaUrls'];
        
        // Handle media if present
        foreach ($mediaUrls as $media) {
            $url = $media['url'];
            $contentType = $media['contentType'];
            // Process media...
        }
        
        return response()->json(['status' => 'success']);
    } catch (WhatsAppException $e) {
        logger()->error('Webhook Error: ' . $e->getMessage());
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage()
        ], 400);
    }
}
```

### API Endpoints

The package provides ready-to-use API endpoints. Add these routes to your `routes/api.php`:

```php
Route::prefix('whatsapp')->group(function () {
    Route::post('/send', 'WhatsAppController@sendMessage');
    Route::post('/send-template', 'WhatsAppController@sendTemplateMessage');
    Route::post('/webhook', 'WhatsAppController@handleWebhook');
    Route::get('/status', 'WhatsAppController@getStatus');
});
```

API Examples (using Postman):

1. Send Basic Message:
```http
POST /api/whatsapp/send
Content-Type: application/json

{
    "to": "+1234567890",
    "message": "Hello from WhatsApp Integration Test!"
}
```

2. Send Template Message:
```http
POST /api/whatsapp/send-template
Content-Type: application/json

{
    "to": "+1234567890",
    "message": "Your order is confirmed",
    "template_id": "HX350d429d32e64a552466cafecbe95f3c",
    "variables": "{\"1\": \"today\", \"2\": \"3pm\"}"
}
```

3. Check Status:
```http
GET /api/whatsapp/status
```

## Error Handling

The package provides specific exceptions:

```php
try {
    $result = $whatsapp->sendMessage($to, $message);
} catch (ValidationException $e) {
    // Handle validation errors (invalid phone number, template, etc.)
} catch (RateLimitException $e) {
    // Handle rate limiting
} catch (ConnectionException $e) {
    // Handle Twilio API connection issues
} catch (WhatsAppException $e) {
    // Handle other WhatsApp-related errors
}
```

Common error scenarios:
- Invalid phone number format (must be E.164: +1234567890)
- Invalid template ID format
- Invalid template variables
- Rate limiting exceeded
- Authentication errors
- Invalid webhook signatures

## Testing

1. Set up your test environment:
```bash
cp .env.example .env.testing
```

2. Configure test credentials in `.env.testing`:
```env
TWILIO_ACCOUNT_SID=your_test_sid
TWILIO_AUTH_TOKEN=your_test_token
TWILIO_FROM_NUMBER=your_test_number
```

3. Run all tests:
```bash
vendor/bin/phpunit
```

Run specific test suites:
```bash
# integration tests
vendor/bin/phpunit tests/Integration/WhatsAppIntegrationTest.php

#mock tests
vendor/bin/phpunit tests/Unit/WhatsAppMockTest.php
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

## Credits

- [Twilio SDK](https://github.com/twilio/twilio-php)

## Author

- **Agnes** - [agypeter97@gmail.com](mailto:agypeter97@gmail.com)