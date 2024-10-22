<?php

namespace Tests\Unit;

use Chat\WhatsappIntegration\WhatsApp;
use Chat\WhatsappIntegration\Exceptions\WhatsAppException;
use Twilio\Rest\Client;
use Twilio\Rest\Api\V2010\Account\MessageList;
use Twilio\Rest\Api\V2010\Account\MessageInstance;
use Mockery;
use PHPUnit\Framework\TestCase;

class WhatsAppTest extends TestCase
{
    protected $whatsapp;
    protected $mockClient;
    protected $mockMessages;

    public function setUp(): void
    {
        parent::setUp();

        $this->mockMessages = Mockery::mock(MessageList::class);
        $this->mockClient = Mockery::mock(Client::class);
        $this->mockClient->messages = $this->mockMessages;

        // init. WhatsApp class with config array
        $this->whatsapp = new WhatsApp([
            'account_sid' => 'test-account-sid',
            'auth_token' => 'test-auth-token',
            'from_number' => '+1234567890',
            'timeout' => 30
        ]);

        $this->whatsapp->setClient($this->mockClient);
    }

    /**
     * @test
     */
    public function it_sends_a_message_successfully()
    {
        $mockResponse = Mockery::mock(MessageInstance::class);
        $mockResponse->sid = 'TEST123';
        $mockResponse->to = 'whatsapp:+1234567890';
        $mockResponse->from = 'whatsapp:+0987654321';

        $this->mockMessages->shouldReceive('create')
            ->once()
            ->with(
                'whatsapp:1234567890',
                [
                    'from' => 'whatsapp:+1234567890',
                    'body' => 'Test message'
                ]
            )
            ->andReturn($mockResponse);

        $response = $this->whatsapp->sendMessage('+1234567890', 'Test message');

        $this->assertEquals('success', $response['status']);
        $this->assertEquals('TEST123', $response['message_sid']);
    }

    /**
     * @test
     */
    public function it_throws_exception_if_sending_message_fails()
    {
        // failed API response
        $this->mockMessages->shouldReceive('create')
            ->once()
            ->andThrow(new \Exception('API error', 500));

        $this->expectException(WhatsAppException::class);
        $this->expectExceptionMessage('Failed to send WhatsApp message: API error');

        $this->whatsapp->sendMessage('+1234567890', 'Test message');
    }

    /**
     * @test
     */
    public function it_uses_default_timeout()
    {
        $whatsapp = new WhatsApp([
            'account_sid' => 'test-account-sid',
            'auth_token' => 'test-auth-token',
            'from_number' => '+1234567890'
        ]);

        // use reflection to access private 'timeout' property
        $reflectionClass = new \ReflectionClass($whatsapp);
        $timeoutProperty = $reflectionClass->getProperty('timeout');
        $timeoutProperty->setAccessible(true);

        $this->assertEquals(30, $timeoutProperty->getValue($whatsapp));
    }

    /**
     * @test
     */
    public function it_handles_webhook_messages_successfully()
    {
        $payload = [
            'MessageSid' => 'MSG123',
            'From' => 'whatsapp:+1234567890',
            'Body' => 'Hello!'
        ];

        $result = $this->whatsapp->handleWebhook($payload);

        $this->assertEquals('success', $result['status']);
        $this->assertCount(1, $result['message']);
        $this->assertEquals('Hello!', $result['message'][0]['text']);
    }

    /**
     * @test
     */
    public function it_returns_no_messages_when_webhook_is_empty()
    {
        $payload = [];

        $result = $this->whatsapp->handleWebhook($payload);

        $this->assertEquals('no_messages', $result['status']);
    }

    /**
     * @test
     */
    public function it_validates_required_config()
    {
        $this->expectException(WhatsAppException::class);
        $this->expectExceptionMessage('Missing required configuration: account_sid');

        new WhatsApp([
            'auth_token' => 'test-auth-token',
            'from_number' => '+1234567890'
        ]);
    }

    /**
     * @test
     */
    public function it_throws_exception_if_quota_is_exceeded()
    {
        $this->mockMessages->shouldReceive('create')
            ->once()
            ->andThrow(new \Twilio\Exceptions\RestException('Quota exceeded', 429, 429));

        $this->expectException(WhatsAppException::class);
        $this->expectExceptionMessage('Quota exceeded: Too many requests. Please try again later.');

        $this->whatsapp->sendMessage('+1234567890', 'Test message');
    }

    /**
     * @test
     */
    public function it_throws_exception_if_phone_number_is_invalid()
    {
        $this->expectException(WhatsAppException::class);
        $this->expectExceptionMessage('Invalid phone number format.');

        $this->whatsapp->sendMessage('invalid-phone-number', 'Test message');
    }

    // cleans up after tests
    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
