# WhatsApp Integration

This is a Composer package that integrates basic WhatsApp features into laravel apps using WhatsApp API with Twilio as the Business provider.This package handles the functionalities for sending WhatsApp messages and handling incoming messages in Laravel 5.8+ applications.

[![MIT Licensed](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
  - [Sending Messages](#sending-messages)
  - [Handling Webhooks](#handling-webhooks)
- [Error Handling](#error-handling)
- [Testing](#testing)
- [Contributing](#contributing)
- [License](#license)

## Requirements

- PHP 7.2 or higher
- Laravel 5.8 or higher
- Twilio Account with WhatsApp capabilities
- Composer

## Installation

1. Install the package via Composer:

```bash
composer require chat/whatsapp-integration
```

## Configuration

1. Publish the configuration file:

```bash
php artisan vendor:publish --provider="Chat\WhatsappIntegration\WhatsAppIntegrationServiceProvider" --tag=whatsapp-config
```

2. Add the following to your `.env` file:

```env
TWILIO_ACCOUNT_SID=your_twilio_account_sid
TWILIO_AUTH_TOKEN=your_twilio_auth_token
TWILIO_FROM_NUMBER=your_whatsapp_number  # Format: +1234567890
WHATSAPP_TIMEOUT=30  # Optional: API timeout in seconds
```

3. Configuration file structure (`config/whatsapp.php`):

```php
return [
    'account_sid' => env('TWILIO_ACCOUNT_SID'),
    'auth_token' => env('TWILIO_AUTH_TOKEN'),
    'from_number' => env('TWILIO_FROM_NUMBER'),
    'timeout' => env('WHATSAPP_TIMEOUT', 30)
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
        $response = $whatsapp->sendMessage(
            '+1234567890',  // recipient's number (E.164 format)
            'Hello from Laravel!'
        );
        
        // Success response structure:
        // [
        //     'status' => 'success',
        //     'message' => 'SM...', // Twilio message SID
        //     'to' => '+1234567890',
        //     'from' => '+0987654321'
        // ]
        
        if ($response['status'] === 'success') {
            $messageSid = $response['message'];
            $recipientNumber = $response['to'];
            // Process success...
        }
    } catch (WhatsAppException $e) {
        // Handle error...
        report($e);
    }
}
```

### Handling Webhooks

Process incoming WhatsApp messages:

```php
use Illuminate\Http\Request;

public function handleWebhook(Request $request, WhatsApp $whatsapp)
{
    try {
        $response = $whatsapp->handleWebhook($request->all());
        
        // Successful webhook response structure:
        // [
        //     'status' => 'success',
        //     'message' => [
        //         [
        //             'message_id' => 'SM...',
        //             'from' => '+1234567890',
        //             'to' => '+0987654321',
        //             'timestamp' => 1234567890,
        //             'text' => 'Message content',
        //             'type' => 'text'
        //         ]
        //     ]
        // ]
        
        if ($response['status'] === 'success') {
            $message = $response['message'][0];
            $text = $message['text'];
            $sender = $message['from'];
            $messageId = $message['message_id'];
            
            // Process the message...
        }
    } catch (WhatsAppException $e) {
        report($e);
        // Handle error...
    }
}
```

## Error Handling

The package throws `WhatsAppException` with specific error codes and messages:

```php
try {
    $response = $whatsapp->sendMessage($to, $message);
} catch (WhatsAppException $e) {
    switch ($e->getCode()) {
        case 429:
            // Rate limit exceeded
            Log::warning('WhatsApp rate limit exceeded');
            break;
        case 401:
            // Authentication failed
            Log::error('WhatsApp authentication failed');
            break;
        case 404:
            // Number not registered with WhatsApp
            Log::error('Recipient not on WhatsApp');
            break;
        case 400:
            // Invalid request (e.g., unverified number)
            Log::error('Invalid WhatsApp request');
            break;
        default:
            Log::error('WhatsApp error: ' . $e->getMessage());
            break;
    }
}
```

Common error scenarios:
- Invalid phone number format (must be E.164: +1234567890)
- Empty or too long messages (max 1600 characters)
- Authentication errors (invalid credentials)
- Rate limiting
- Unverified recipient numbers
- Network or API errors

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

3. Run tests:
```bash
vendor/bin/phpunit
```

To run Integral tests, run the command below
```bash
./vendor/bin/phpunit tests/Integration/WhatsAppIntegrationTest.php

```

To run the mock tests, run the command below
```bash
./vendor/bin/phpunit tests/Unit/WhatsAppMockTest.php

```

Example test:
```php
use Tests\TestCase;
use Chat\WhatsappIntegration\WhatsApp;

class WhatsAppTest extends TestCase
{
    /** @test */
    public function it_can_send_a_message()
    {
        $whatsapp = new WhatsApp([
            'account_sid' => 'test_sid',
            'auth_token' => 'test_token',
            'from_number' => '+1234567890'
        ]);

        $response = $whatsapp->sendMessage(
            '+1234567890',
            'Test message'
        );

        $this->assertEquals('success', $response['status']);
        $this->assertNotEmpty($response['message']);
    }
}
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

## Credits

- [Twilio SDK](https://github.com/twilio/twilio-php)

## Author

- **Agnes** - [agypeter97@gmail.com](mailto:agypeter97@gmail.com)

