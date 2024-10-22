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
        
        $projectRoot = dirname(dirname(__DIR__));
        
        try {
            $dotenv = Dotenv::create($projectRoot);
            $dotenv->load();
            
            $dotenv->required([
                'TWILIO_ACCOUNT_SID',
                'TWILIO_AUTH_TOKEN',
                'WHATSAPP_FROM_NUMBER',
                'TEST_PHONE_NUMBER'
            ]);
        } catch (\Exception $e) {
            $this->markTestSkipped('Environment file not found or invalid: ' . $e->getMessage());
            return;
        }

        // ensure no.  starts with + and contains only digits after
        $phoneNumber = getenv('TEST_PHONE_NUMBER');
        if (strpos($phoneNumber, '+') !== 0) {
            $phoneNumber = '+' . $phoneNumber;
        }
        $this->validPhoneNumber = $phoneNumber;

        $this->whatsapp = new WhatsApp([
            'account_sid' => getenv('TWILIO_ACCOUNT_SID'),
            'auth_token' => getenv('TWILIO_AUTH_TOKEN'),
            'from_number' => getenv('WHATSAPP_FROM_NUMBER'),
            'timeout' => 30,
        ]);
    }

   


    /**
     * @test
     */
    // check correct no. format
    public function test_invalid_phone_number_format_no_plus()
    {
        $this->expectException(WhatsAppException::class);
        $this->expectExceptionMessage('Invalid phone number format.');
        
        $this->whatsapp->sendMessage('12345', 'Test message');
    }


     /**
     * @test
     */
    public function test_sends_message_with_maximum_length()
    {
        $message = str_repeat('a', 1600); // twilio's maximum message length
        
        try {
            $response = $this->whatsapp->sendMessage($this->validPhoneNumber, $message);
            $this->assertEquals('success', $response['status']);
        } catch (WhatsAppException $e) {
            $this->fail('Failed to send maximum length message: ' . $e->getMessage());
        }
    }

     /**
     * @test
     */
    public function test_sends_message_with_special_characters()
    {
        $message = 'Test message with special chars: áéíóú ñ € § @ # $ % & *';
        
        try {
            $response = $this->whatsapp->sendMessage($this->validPhoneNumber, $message);
            $this->assertEquals('success', $response['status']);
            $this->assertArrayHasKey('message_sid', $response);
        } catch (WhatsAppException $e) {
            $this->fail('Failed to send message with special characters: ' . $e->getMessage());
        }
    }

    /**
     * @test
     */
    public function test_invalid_phone_number_format_with_letters()
    {
        $this->expectException(WhatsAppException::class);
        $this->expectExceptionMessage('Invalid phone number format.');
        
        $this->whatsapp->sendMessage('+1234abc5678', 'Test message');
    }

    /**
     * @test
     */
    public function test_invalid_phone_number_format_double_plus()
    {
        $this->expectException(WhatsAppException::class);
        $this->expectExceptionMessage('Invalid phone number format.');
        
        $this->whatsapp->sendMessage('++1234567890', 'Test message');
    }

    /**
     * @test
     */
    public function test_invalid_phone_number_too_short()
    {
        $this->expectException(WhatsAppException::class);
        $this->expectExceptionMessage('Invalid phone number format.');
        
        $this->whatsapp->sendMessage('+123', 'Test message');
    }

    /**
     * @test
     */
    // webhook payload tests
     public function test_handles_webhook_with_minimal_data()
    {
        $payload = [
            'Body' => 'Simple message'
        ];

        $result = $this->whatsapp->handleWebhook($payload);
        
        $this->assertEquals('success', $result['status']);
        $this->assertNull($result['message'][0]['message_id']);
        $this->assertEquals('Simple message', $result['message'][0]['text']);
    }

    /**
     * @test
     */
    public function test_handles_webhook_with_complete_data()
    {
        $payload = [
            'MessageSid' => 'MSG123456789',
            'From' => 'whatsapp:+1234567890',
            'To' => 'whatsapp:+0987654321',
            'Body' => 'Complete webhook test message',
            'NumMedia' => '0',
            'NumSegments' => '1',
            'SmsStatus' => 'received'
        ];

        $result = $this->whatsapp->handleWebhook($payload);
        
        $this->assertEquals('success', $result['status']);
        $this->assertEquals('MSG123456789', $result['message'][0]['message_id']);
        $this->assertEquals('+1234567890', $result['message'][0]['from']);
        $this->assertEquals('Complete webhook test message', $result['message'][0]['text']);
    }

    
    /**
     * @test
     */
    public function test_webhook_handles_missing_optional_fields()
    {
        $payloads = [
            ['Body' => 'Test 1'],
            ['Body' => 'Test 2', 'From' => 'whatsapp:+1234567890'],
            ['Body' => 'Test 3', 'MessageSid' => 'MSG123']
        ];

        foreach ($payloads as $payload) {
            $result = $this->whatsapp->handleWebhook($payload);
            $this->assertEquals('success', $result['status']);
            $this->assertArrayHasKey('message', $result);
            $this->assertArrayHasKey('text', $result['message'][0]);
            $this->assertEquals($payload['Body'], $result['message'][0]['text']);
        }
    }

       /**
     * @test
     */
    public function test_handles_empty_webhook_payload()
    {
        $result = $this->whatsapp->handleWebhook([]);
        $this->assertEquals('no_messages', $result['status']);
    }

      /**
     * @test
     */
    // whitespace handling in msgs
    public function test_handles_whitespace_in_message()
    {
        $messages = [
            "  Padded with spaces  ",
            "\nNew lines\n\n",
            "\tTabbed\tContent\t",
            " Mixed   Spacing   Content \n\t"
        ];

        foreach ($messages as $message) {
            try {
                $response = $this->whatsapp->sendMessage($this->validPhoneNumber, $message);
                $this->assertEquals('success', $response['status']);
            } catch (WhatsAppException $e) {
                $this->fail('Failed to handle whitespace in message: ' . $e->getMessage());
            }
        }
    }

    protected function tearDown(): void
    {
        $this->whatsapp = null;
        parent::tearDown();
    }
}