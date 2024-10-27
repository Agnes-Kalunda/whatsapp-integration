<?php

namespace Tests\Unit;

use Chat\WhatsappIntegration\WhatsApp;
use Chat\WhatsappIntegration\Exceptions\WhatsAppException;
use Mockery;
use PHPUnit\Framework\TestCase;
use Twilio\Rest\Client;
use Twilio\Security\RequestValidator;
use Twilio\Exceptions\RestException;

class WhatsAppMockTest extends TestCase
{
    protected $mockClient;
    protected $mockValidator;
    protected $whatsapp;

    protected function setUp(): void
    {
        parent::setUp();
        
        // mock Twilio Client
        $this->mockClient = Mockery::mock(Client::class);
        
        // mock RequestValidator
        $this->mockValidator = Mockery::mock(RequestValidator::class);

        // config for WhatsApp instance
        $config = [
            'account_sid' => 'ACXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
            'auth_token' => 'your_auth_token',
            'from_number' => '+1234567890'
        ];
        
        // create WhatsApp instance with mocked dependencies
        $this->whatsapp = new WhatsApp($config, $this->mockClient, $this->mockValidator);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testSendMessage()
    {
        $to = '+0987654321';
        $message = 'Hello, this is a test message.';
        $expectedResponse = Mockery::mock('Twilio\Rest\Api\V2010\Account\MessageInstance');

        $this->mockClient->messages = Mockery::mock('Twilio\Rest\Api\V2010\Account\MessageList');
        $this->mockClient->messages
            ->shouldReceive('create')
            ->once()
            ->with('whatsapp:' . $to, [
                'from' => 'whatsapp:+1234567890',
                'body' => $message
            ])
            ->andReturn($expectedResponse);

        $response = $this->whatsapp->sendMessage($to, $message);

        $this->assertInstanceOf('Twilio\Rest\Api\V2010\Account\MessageInstance', $response);
    }

    public function testSendMessageThrowsWhatsAppException()
    {
        $to = '+0987654321';
        $message = 'Hello, this is a test message.';

        $this->mockClient->messages = Mockery::mock('Twilio\Rest\Api\V2010\Account\MessageList');
        $this->mockClient->messages
            ->shouldReceive('create')
            ->once()
            ->with('whatsapp:' . $to, [
                'from' => 'whatsapp:+1234567890',
                'body' => $message
            ])
            ->andThrow(new RestException('An error occurred', 500));

        $this->expectException(WhatsAppException::class);
        $this->expectExceptionMessage('An error occurred');

        $this->whatsapp->sendMessage($to, $message);
    }

    public function testValidateWebhookSignature()
    {
        $signature = 'valid_signature';
        $url = 'https://example.com/webhook';
        $params = ['key' => 'value'];

        $this->mockValidator
            ->shouldReceive('validate')
            ->once()
            ->with($signature, $url, $params)
            ->andReturn(true);

        $result = $this->whatsapp->validateWebhookSignature($signature, $url, $params);
        
        $this->assertTrue($result);
    }

    public function testValidateWebhookSignatureThrowsWhatsAppException()
    {
        $signature = 'invalid_signature';
        $url = 'https://example.com/webhook';
        $params = ['key' => 'value'];

        $this->mockValidator
            ->shouldReceive('validate')
            ->once()
            ->with($signature, $url, $params)
            ->andReturn(false);

        $this->expectException(WhatsAppException::class);
        $this->expectExceptionMessage('Invalid Twilio webhook signature.');

        $this->whatsapp->validateWebhookSignature($signature, $url, $params);
    }

    public function testHandleWebhook()
    {
        $requestData = [
            'MessageSid' => 'SMXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
            'From' => 'whatsapp:+0987654321',
            'To' => 'whatsapp:+1234567890',
            'Body' => 'Hello, this is a test message.',
            'Status' => 'delivered',
            'NumMedia' => '1',
            'MediaUrl0' => 'https://example.com/media.jpg'
        ];
        $url = 'https://example.com/webhook';
        $signature = 'valid_signature';

        $this->mockValidator
            ->shouldReceive('validate')
            ->once()
            ->with($signature, $url, $requestData)
            ->andReturn(true);

        $result = $this->whatsapp->handleWebhook($requestData, $url, $signature);

        $this->assertEquals($requestData['MessageSid'], $result['MessageSid']);
        $this->assertEquals($requestData['From'], $result['From']);
        $this->assertEquals($requestData['To'], $result['To']);
        $this->assertEquals($requestData['Body'], $result['Body']);
        $this->assertEquals($requestData['Status'], $result['Status']);
        $this->assertCount(1, $result['MediaUrls']);
    }

    public function testHandleWebhookInvalidSignature()
    {
        $requestData = [
            'MessageSid' => 'SMXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
            'From' => 'whatsapp:+0987654321',
            'To' => 'whatsapp:+1234567890',
            'Body' => 'Hello, this is a test message.'
        ];
        $url = 'https://example.com/webhook';
        $signature = 'invalid_signature';

        $this->mockValidator
            ->shouldReceive('validate')
            ->once()
            ->with($signature, $url, $requestData)
            ->andReturn(false);

        $this->expectException(WhatsAppException::class);
        $this->expectExceptionMessage('Invalid Twilio webhook signature.');

        $this->whatsapp->handleWebhook($requestData, $url, $signature);
    }
}