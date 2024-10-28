# WhatsApp Integration Package

This is a Composer package that integrates basic WhatsApp features into laravel apps using WhatsApp API with Twilio as the Business provider.This package handles the functionalities for sending WhatsApp messages and handling incoming messages in Laravel 5.8+ applications.

[![Latest Stable Version](https://poser.pugx.org/chat/whatsapp-integration/v/stable)](https://packagist.org/packages/chat/whatsapp-integration)
[![License](https://poser.pugx.org/chat/whatsapp-integration/license)](https://packagist.org/packages/chat/whatsapp-integration)

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
  - [Basic Usage](#basic-usage)
  - [Template Messages](#template-messages)
  - [Handling Webhooks](#handling-webhooks)
- [API Integration Examples](#api-integration-examples)
- [Error Handling](#error-handling)
- [Testing](#testing)
- [License](#license)

## Features

- Send WhatsApp messages
- Template message support
- Media message handling
- Webhook processing
- Rate limiting
- Comprehensive error handling
- Validation
- Easy integration

## Requirements

- PHP >= 7.1.3
- Laravel >= 5.8
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
TWILIO_ACCOUNT_SID=your_account_sid
TWILIO_AUTH_TOKEN=your_auth_token
TWILIO_FROM_NUMBER=your_whatsapp_number  # Format: +1234567890
```

## Usage

### Basic Usage

```php
use Chat\WhatsappIntegration\WhatsApp;
use Chat\WhatsappIntegration\Exceptions\WhatsAppException;

public function sendMessage(WhatsApp $whatsapp)
{
    try {
        $result = $whatsapp->sendMessage(
            '+1234567890',  // recipient's number
            'Hello from Laravel!'
        );
        
        // Success
        $messageSid = $result->sid;
        $status = $result->status;
        
    } catch (WhatsAppException $e) {
        // Handle error
        logger()->error('WhatsApp Error: ' . $e->getMessage());
    }
}
```

### Template Messages

```php
try {
    $result = $whatsapp->sendMessage(
        '+1234567890',
        'Your order has been confirmed',
        'HX350d429d32e64a552466cafecbe95f3c', // template ID
        json_encode(['1' => 'today', '2' => '3pm']) // variables
    );
} catch (WhatsAppException $e) {
    // Handle error
}
```

### Handling Webhooks

```php
try {
    $result = $whatsapp->handleWebhook(
        $request->all(),
        $request->fullUrl(),
        $request->header('X-Twilio-Signature')
    );
    
    $messageBody = $result['Body'];
    $fromNumber = $result['From'];
    $mediaUrls = $result['MediaUrls'];
    
} catch (WhatsAppException $e) {
    // Handle error
}
```

## API Integration Examples

Here's how to integrate the package with your Laravel application's API:

1. Create a controller:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Chat\WhatsappIntegration\WhatsApp;
use Chat\WhatsappIntegration\Exceptions\WhatsAppException;

class WhatsAppController extends Controller
{
    protected $whatsapp;

    public function __construct(WhatsApp $whatsapp)
    {
        $this->whatsapp = $whatsapp;
    }

    public function sendMessage(Request $request)
    {
        $request->validate([
            'to' => 'required|string',
            'message' => 'required|string'
        ]);

        try {
            $result = $this->whatsapp->sendMessage(
                $request->to,
                $request->message
            );

            return response()->json([
                'success' => true,
                'message_sid' => $result->sid,
                'status' => $result->status
            ]);
        } catch (WhatsAppException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function handleWebhook(Request $request)
    {
        try {
            $result = $this->whatsapp->handleWebhook(
                $request->all(),
                $request->fullUrl(),
                $request->header('X-Twilio-Signature')
            );

            return response()->json(['status' => 'success']);
        } catch (WhatsAppException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
```

2. Define routes in `routes/api.php`:

```php
Route::prefix('whatsapp')->group(function () {
    Route::post('/send', [WhatsAppController::class, 'sendMessage']);
    Route::post('/webhook', [WhatsAppController::class, 'handleWebhook']);
});
```

## Error Handling

The package provides several exception types:

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
# Integration tests
vendor/bin/phpunit tests/Integration/WhatsAppIntegrationTest.php

# Unit tests with mocking
vendor/bin/phpunit tests/Unit/WhatsAppMockTest.php
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.


## Credits

- Agnes
- [Twilio SDK](https://github.com/twilio/twilio-php)

