<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Chat\WhatsappIntegration\WhatsApp;
use Chat\WhatsappIntegration\Exceptions\WhatsAppException;

class WhatsAppIntegrationTest extends TestCase
{
    protected $whatsapp;

    protected function setUp(): void
    {
        
        if (file_exists(__DIR__ . '/../../.env')) {
            $dotenv = \Dotenv\Dotenv::create(__DIR__ . '/../../');
            $dotenv->load();
        }

        
        $config = [
            'account_sid' => getenv('TWILIO_ACCOUNT_SID'),
            'auth_token'  => getenv('TWILIO_AUTH_TOKEN'),
            'from_number' => getenv('TWILIO_FROM_NUMBER')
        ];

        
        $this->whatsapp = new WhatsApp($config);
    }

    /** @test */
    public function it_can_send_a_whatsapp_message()
    {
        $to = '+254707606316'; 
        $message = "Hello from PHPUnit test!";

        $response = $this->whatsapp->sendMessage($to, $message);

        $this->assertIsArray($response);
        $this->assertEquals('success', $response['status']);
        $this->assertNotEmpty($response['message']);
        $this->assertEquals($to, str_replace('whatsapp:', '', $response['to']));
    }

    /** @test */
    public function it_throws_exception_for_invalid_phone_number()
    {
        $this->expectException(WhatsAppException::class);
        $this->expectExceptionMessage('Invalid recipient phone number format');

        $invalidPhone = '12345'; 
        $this->whatsapp->sendMessage($invalidPhone, 'Invalid number test');
    }

    /** @test */
    public function it_throws_exception_for_missing_recipient()
    {
        $this->expectException(WhatsAppException::class);
        $this->expectExceptionMessage('Recipient phone number is required');

        $this->whatsapp->sendMessage(null, 'Test message');
    }

    /** @test */
    public function it_throws_exception_for_missing_message()
    {
        $this->expectException(WhatsAppException::class);
        $this->expectExceptionMessage('Message content is required');

        $this->whatsapp->sendMessage('+254707606316', null);
    }

    /** @test */
    public function it_throws_exception_for_empty_message()
    {
        $this->expectException(WhatsAppException::class);
        $this->expectExceptionMessage('Message content cannot be empty');

        $this->whatsapp->sendMessage('+254707606316', '');
    }

    /** @test */
    public function it_throws_exception_for_missing_configuration()
    {
        $this->expectException(WhatsAppException::class);
        $this->expectExceptionMessage('WhatsApp configuration is required');

        $whatsapp = new WhatsApp(null);
    }

    /** @test */
    public function it_throws_exception_for_empty_configuration_field()
    {
        $this->expectException(WhatsAppException::class);
        $this->expectExceptionMessage("Configuration field 'account_sid' cannot be empty");

        $config = [
            'account_sid' => '',
            'auth_token' => 'valid_auth_token',
            'from_number' => 'valid_from_number'
        ];

        new WhatsApp($config);
    }

    /** @test */
    public function it_can_handle_webhook_payload()
{
    
        $fromNumber = getenv('TWILIO_FROM_NUMBER');
    
    
        $validRecipientNumber = '+254707606316'; 

        $payload = [
            'MessageSid' => 'SMXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
            'From' => $fromNumber, 
            'To' => $validRecipientNumber, 
            'Body' => 'Hello',
    ];

        $response = $this->whatsapp->handleWebhook($payload);

        $this->assertEquals('success', $response['status']);
        $this->assertIsArray($response['message']);
        $this->assertEquals('Hello', $response['message'][0]['text']);
        $this->assertEquals('text', $response['message'][0]['type']);
}
    /** @test */
    public function it_throws_exception_for_invalid_webhook_payload()
    {
        $this->expectException(WhatsAppException::class);
        $this->expectExceptionMessage('Webhook payload is required');

        $this->whatsapp->handleWebhook(null); 
    }

    /** @test */
    public function it_throws_exception_for_invalid_recipient_phone_number_in_webhook()
    {
        $this->expectException(WhatsAppException::class);
        $this->expectExceptionMessage('Invalid recipient phone number format in webhook payload. Must be E.164 format (e.g., +1234567890)');

        
        $fromNumber = getenv('TWILIO_FROM_NUMBER');

    
        $payload = [
            'MessageSid' => 'SMXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
            'From' => $fromNumber, 
            'To' => 'invalid_phone_number', 
            'Body' => 'Hello',
        ];

        $this->whatsapp->handleWebhook($payload);
    }

    /** @test */
    public function it_throws_exception_for_invalid_sender_phone_number_in_webhook()
    {
        $this->expectException(WhatsAppException::class);
        $this->expectExceptionMessage('Invalid sender phone number format in webhook payload. Must be E.164 format (e.g., +1234567890)');

        
        $fromNumber = getenv('TWILIO_FROM_NUMBER');

    
        $payload = [
            'MessageSid' => 'SMXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
            'From' => 'invalid_phone_number', 
            'To' => '+254707606316', 
            'Body' => 'Hello',
        ];

        $this->whatsapp->handleWebhook($payload);
    }

    protected function tearDown(): void
    {
        // Clean up after each test if necessary
        $this->whatsapp = null;
    }
}
