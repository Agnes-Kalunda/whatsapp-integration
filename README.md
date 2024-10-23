# whatsapp-integration



**WhatsApp Integration** is a package that allows you to send WhatsApp messages using the Twilio API in your Laravel 5.8+ applications. It provides a simple API for sending text messages, handling incoming messages, and interacting with the WhatsApp API seamlessly.

[![MIT Licensed](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

## Menu

- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
  - [Environment Variables](#environment-variables)
  - [Publishing Config](#publishing-config)
- [Features](#features)
- [Usage](#usage)
  - [Sending Messages](#sending-messages)
    - [Simple Message](#sending-messages)
    - [Messages with Special Characters](#sending-messages-with-special-characters)
    - [Long Messages](#sending-long-messages)
  - [Webhook Handling](#webhook-handling)
    - [Basic Webhook Setup](#webhook-handling)
    - [Processing Messages](#processing-incoming-messages)
    
- [Error Handling](#error-handling)
  - [Common Errors](#common-errors)
  - [Exception Handling](#exception-handling)
  
- [Testing](#testing)
  - [Running Tests](#running-tests)
  - [Test Environment Setup](#test-environment-setup)
- [Contributing](#contributing)
- [License](#license)


## Requirements

- PHP 7.2 or higher
- Laravel 5.8 or higher
- Twilio Account with WhatsApp capabilities
- Composer

## Installation

You can install the package via Composer:

```bash
composer require chat/whatsapp-integration
```



## Configuration

### Publishing Config

Publish the configuration file:

```bash
php artisan vendor:publish --provider="Chat\WhatsappIntegration\WhatsAppIntegrationServiceProvider" --tag=whatsapp-config
```

### Environment Variables

Add the following to your `.env` file:

```env
# mandatory settings
TWILIO_ACCOUNT_SID=your_twilio_account_sid
TWILIO_AUTH_TOKEN=your_twilio_auth_token
WHATSAPP_FROM_NUMBER=your_whatsapp_number  # Format: +1234567890

# optional settings
WHATSAPP_TIMEOUT=30  # API request timeout in seconds
```



## Features

- Use WhatsApp API to send WhatsApp messages with Twilio as the Service Provider.
- Handle incoming WhatsApp messages through webhooks
- Robust error handling and input validation
- Support for special characters and unicode messages
- Extensive test coverage
- Maximum message length support (up to 1600 characters)
- Whitespace handling
- Phone number validation and formatting

## Usage

### Sending Messages

Basic message sending:

```php
use Chat\WhatsappIntegration\WhatsApp;

public function sendWhatsAppMessage(WhatsApp $whatsapp)
{
    try {
        $response = $whatsapp->sendMessage(
            '+1234567890',  // recipient's number with country code
            'Hello from Laravel!'
        );
        
        if ($response['status'] === 'success') {
            // message sent successfully
            $messageSid = $response['message_sid'];
        }
    } catch (WhatsAppException $e) {
        // handle error
        report($e);
    }
}
```

### Sending Messages with Special Characters

```php
$message = 'Special characters: áéíóú ñ € § @ # $ % & *';
$response = $whatsapp->sendMessage('+1234567890', $message);
```

### Sending Long Messages

```php
// messages up to 1600 characters are supported
$longMessage = str_repeat('Your long message here. ', 50);
$response = $whatsapp->sendMessage('+1234567890', $longMessage);
```

### Webhook Handling

Setting up webhook handling:
```php
public function handleIncomingMessage(Request $request, WhatsApp $whatsapp)
{
    try {
        $response = $whatsapp->handleWebhook($request->all());
        
        if ($response['status'] === 'success') {
            $message = $response['message'][0];
            $text = $message['text'];
            $sender = $message['from'];
            // process the message
        }
    } catch (WhatsAppException $e) {
        // handle error
        report($e);
    }
}
```

## Error Handling

The package throws `WhatsAppException` for various error conditions:

- Invalid phone number format
- Empty messages
- API errors
- Configuration errors
- Rate limiting (429 error code)

```php
try {
    $response = $whatsapp->sendMessage($to, $message);
} catch (WhatsAppException $e) {
    switch ($e->getCode()) {
        case 429:
            // handle rate limiting
            break;
        default:
            // handle other errors
            break;
    }
}
```


