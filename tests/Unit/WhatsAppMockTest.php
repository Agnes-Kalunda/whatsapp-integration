<?php

namespace Tests\Unit;

use Chat\WhatsappIntegration\WhatsApp;
use Chat\WhatsappIntegration\Exceptions\WhatsAppException;
use Chat\WhatsappIntegration\Exceptions\ValidationException;
use Chat\WhatsappIntegration\Exceptions\ConnectionException;
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
    protected $validPhoneNumber = '+12025550123'; 

    protected function setUp(): void
    {
        parent::setUp();
        
        
        $this->mockClient = Mockery::mock(Client::class);
        
        
        $this->mockValidator = Mockery::mock(RequestValidator::class);

        
        $config = [
            'account_sid' => 'ACXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
            'auth_token' => 'your_auth_token',
            'from_number' => '+12025550180' 
        ];
        
        // whatsApp instance with mocked dependencies
        $this->whatsapp = new WhatsApp($config, $this->mockClient, $this->mockValidator);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testSendMessage()
    {
        $message = 'Hello, this is a test message.';
        $expectedResponse = Mockery::mock('Twilio\Rest\Api\V2010\Account\MessageInstance');
        $expectedResponse->sid = 'MGXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX';
        $expectedResponse->status = 'queued';

        $this->mockClient->messages = Mockery::mock('Twilio\Rest\Api\V2010\Account\MessageList');
        $this->mockClient->messages
            ->shouldReceive('create')
            ->once()
            ->with('whatsapp:' . $this->validPhoneNumber, [
                'from' => 'whatsapp:+12025550180',
                'body' => $message
            ])
            ->andReturn($expectedResponse);

        $response = $this->whatsapp->sendMessage($this->validPhoneNumber, $message);

        $this->assertInstanceOf('Twilio\Rest\Api\V2010\Account\MessageInstance', $response);
        $this->assertEquals('MGXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX', $response->sid);
    }

    public function testSendMessageThrowsWhatsAppException()
    {
        $message = 'Hello, this is a test message.';

        $this->mockClient->messages = Mockery::mock('Twilio\Rest\Api\V2010\Account\MessageList');
        $this->mockClient->messages
            ->shouldReceive('create')
            ->once()
            ->with('whatsapp:' . $this->validPhoneNumber, [
                'from' => 'whatsapp:+12025550180',
                'body' => $message
            ])
            ->andThrow(new RestException('Authentication error', 20003));

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Failed to authenticate with Twilio API');

        $this->whatsapp->sendMessage($this->validPhoneNumber, $message);
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

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid webhook signature');

        $this->whatsapp->validateWebhookSignature($signature, $url, $params);
    }

    public function testHandleWebhook()
    {
        $requestData = [
            'MessageSid' => 'SMXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
            'From' => 'whatsapp:+12025550123',
            'To' => 'whatsapp:+12025550180',
            'Body' => 'Hello, this is a test message.',
            'Status' => 'delivered',
            'NumMedia' => '1',
            'MediaUrl0' => 'https://example.com/media.jpg',
            'MediaContentType0' => 'image/jpeg'
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
        $this->assertEquals('image/jpeg', $result['MediaUrls'][0]['contentType']);
    }

    public function testHandleWebhookInvalidSignature()
    {
        $requestData = [
            'MessageSid' => 'SMXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
            'From' => 'whatsapp:+12025550123',
            'To' => 'whatsapp:+12025550180',
            'Body' => 'Hello, this is a test message.'
        ];
        $url = 'https://example.com/webhook';
        $signature = 'invalid_signature';

        $this->mockValidator
            ->shouldReceive('validate')
            ->once()
            ->with($signature, $url, $requestData)
            ->andReturn(false);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid webhook signature');

        $this->whatsapp->handleWebhook($requestData, $url, $signature);
    }

    public function testInvalidPhoneNumberFormat()
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid phone number format: invalid-number');
        
        $this->whatsapp->sendMessage('invalid-number', 'Test message');
    }

    public function testMissingMessageSid()
    {
        $requestData = [
            'Body' => 'Test message'
        ];
        $url = 'https://example.com/webhook';
        $signature = 'valid_signature';

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Missing MessageSid in webhook data');

        $this->whatsapp->handleWebhook($requestData, $url, $signature);
    }
}