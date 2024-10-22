<?php

namespace Tests\Integration;

use Chat\WhatsappIntegration\Exceptions\WhatsAppException;
use Chat\WhatsappIntegration\WhatsApp;
use PHPUnit\Framework\TestCase;
use Dotenv\Dotenv;

class WhatsAppIntegrationTest extends TestCase
{
    protected $whatsapp;
    protected $validPhoneNumber;

    protected function setUp(): void
    {

        $dotenv = Dotenv::create(__DIR__ . '/..');
        $dotenv->load();

        
        $this->printEnvVariables();

        
        $this->validPhoneNumber = getenv('TEST_PHONE_NUMBER');

        // initialize WhatsApp class with env.variables
        $this->whatsapp = new WhatsApp([
            'account_sid' => getenv('TWILIO_ACCOUNT_SID'),
            'auth_token' => getenv('TWILIO_AUTH_TOKEN'),
            'from_number' => getenv('WHATSAPP_FROM_NUMBER'),
            'timeout' => 30,
        ]);
    }

    protected function printEnvVariables()
    {
        echo "TWILIO_ACCOUNT_SID: " . getenv('TWILIO_ACCOUNT_SID') . PHP_EOL;
        echo "TWILIO_AUTH_TOKEN: " . getenv('TWILIO_AUTH_TOKEN') . PHP_EOL;
        echo "WHATSAPP_FROM_NUMBER: " . getenv('WHATSAPP_FROM_NUMBER') . PHP_EOL;
        echo "TEST_PHONE_NUMBER: " . getenv('TEST_PHONE_NUMBER') . PHP_EOL;
    }

    public function test_sends_message_to_valid_number()
    {
        $message = 'Hello from Twilio WhatsApp API!';

        try {
            $response = $this->whatsapp->sendMessage($this->validPhoneNumber, $message);
            $this->assertArrayHasKey('message_sid', $response);
            $this->assertEquals('success', $response['status']);
        } catch (WhatsAppException $e) {
            $this->fail('Failed to send message: ' . $e->getMessage());
        }
    }

    public function test_throws_exception_for_invalid_phone_number()
    {
        $this->expectException(WhatsAppException::class);
        $this->expectExceptionMessage('Invalid phone number format.');

        $this->whatsapp->sendMessage('invalid-phone-number', 'Test message');
    }

    public function test_throws_exception_for_empty_message()
    {
        $this->expectException(WhatsAppException::class);
        $this->expectExceptionMessage('Message cannot be empty');

        $this->whatsapp->sendMessage($this->validPhoneNumber, '');
    }

    public function test_handles_webhook_data()
    {
        $payload = [
            'MessageSid' => 'MSG123',
            'From' => 'whatsapp:' . $this->validPhoneNumber,
            'Body' => 'Hello!',
        ];

        $result = $this->whatsapp->handleWebhook($payload);

        $this->assertEquals('success', $result['status']);
        $this->assertCount(1, $result['message']);
        $this->assertEquals('Hello!', $result['message'][0]['text']);
    }

    public function tearDown(): void
    {
    
        parent::tearDown();
    }
}
