<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Chat\WhatsappIntegration\WhatsApp;
use Chat\WhatsappIntegration\Exceptions\WhatsAppException;
use Twilio\Rest\Client;
use Twilio\Rest\Api\V2010\Account\MessageList;
use Twilio\Rest\Api\V2010\Account\MessageInstance;
use Twilio\Exceptions\RestException;
use Mockery;

class WhatsAppMockTest extends TestCase
{
    protected $whatsapp;
    protected $mockTwilioClient;
    protected $mockMessageList;
    private $config;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockTwilioClient = Mockery::mock(Client::class);
        $this->mockMessageList = Mockery::mock(MessageList::class);
        
        $this->config = [
            'account_sid' => 'AC_test_account_sid',
            'auth_token' => 'test_auth_token',
            'from_number' => '+1234567890'
        ];
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_successfully_sends_a_message()
    {
        $mockMessageInstance = Mockery::mock(MessageInstance::class);
        $mockMessageInstance->sid = 'SM123';
        $mockMessageInstance->to = 'whatsapp:+254707606316';
        $mockMessageInstance->from = 'whatsapp:+1234567890';
        
        $this->mockTwilioClient->messages = $this->mockMessageList;
        
        
        $this->mockMessageList->shouldReceive('create')
            ->with(
                'whatsapp:254707606316',  
                [
                    'from' => 'whatsapp:+1234567890',
                    'body' => 'Test message'
                ]
            )
            ->once()
            ->andReturn($mockMessageInstance);
            
        $whatsapp = $this->createWhatsAppWithMockedClient();
        $response = $whatsapp->sendMessage('+254707606316', 'Test message');
        
        $this->assertEquals('success', $response['status']);
        $this->assertEquals('SM123', $response['message']);
        $this->assertEquals('whatsapp:+254707606316', $response['to']);
        $this->assertEquals('whatsapp:+1234567890', $response['from']);
    }

    /** @test */
    public function it_handles_rate_limit_error()
    {
        $this->expectException(WhatsAppException::class);
        $this->expectExceptionMessage('Rate limit exceeded');
        $this->expectExceptionCode(429);

        $exception = Mockery::mock(RestException::class);
        $exception->shouldReceive('getCode')->andReturn(429);
        $exception->shouldReceive('getMessage')->andReturn('Too many requests');
        $exception->shouldReceive('getStatusCode')->andReturn(429);

        $this->mockTwilioClient->messages = $this->mockMessageList;
        $this->mockMessageList->shouldReceive('create')
            ->once()
            ->andThrow($exception);

        $whatsapp = $this->createWhatsAppWithMockedClient();
        $whatsapp->sendMessage('+254707606316', 'Test message');
    }

    /** @test */
    public function it_handles_authentication_error()
    {
        $this->expectException(WhatsAppException::class);
        $this->expectExceptionMessage('Authentication failed');
        $this->expectExceptionCode(401);

        $exception = Mockery::mock(RestException::class);
        $exception->shouldReceive('getCode')->andReturn(401);
        $exception->shouldReceive('getMessage')->andReturn('Invalid authentication');
        $exception->shouldReceive('getStatusCode')->andReturn(401);

        $this->mockTwilioClient->messages = $this->mockMessageList;
        $this->mockMessageList->shouldReceive('create')
            ->once()
            ->andThrow($exception);

        $whatsapp = $this->createWhatsAppWithMockedClient();
        $whatsapp->sendMessage('+254707606316', 'Test message');
    }

    /** @test */
   

    /** @test */
    public function it_validates_webhook_payload_successfully()
    {
        $whatsapp = $this->createWhatsAppWithMockedClient();
        
        $payload = [
            'MessageSid' => 'SM123',
            'From' => '+1234567890',
            'To' => '+254707606316',
            'Body' => 'Test webhook message'
        ];

        $response = $whatsapp->handleWebhook($payload);

        $this->assertEquals('success', $response['status']);
        $this->assertIsArray($response['message']);
        $this->assertEquals('Test webhook message', $response['message'][0]['text']);
        $this->assertEquals('text', $response['message'][0]['type']);
        $this->assertEquals('+1234567890', $response['message'][0]['from']);
        $this->assertEquals('+254707606316', $response['message'][0]['to']);
    }

    /** @test */
    public function it_handles_empty_webhook_body()
    {
        $whatsapp = $this->createWhatsAppWithMockedClient();
        
        $payload = [
            'MessageSid' => 'SM123',
            'From' => '+1234567890',
            'To' => '+254707606316'
        ];

        $response = $whatsapp->handleWebhook($payload);

        $this->assertEquals('no_messages', $response['status']);
    }

    protected function createWhatsAppWithMockedClient()
    {
        $whatsapp = new class($this->config, $this->mockTwilioClient) extends WhatsApp {
            private $mockedClient;
            
            public function __construct(array $config, $mockedClient)
            {
                $this->mockedClient = $mockedClient;
                parent::__construct($config);
            }
            
            protected function initializeClient($accountSid, $authToken)
            {
                $this->client = $this->mockedClient;
            }
        };
        
        return $whatsapp;
    }
}