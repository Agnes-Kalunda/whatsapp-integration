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

         //init. WhatsApp class with config array
        $this->whatsapp = new WhatsApp([
            'account_sid' => 'test-account-sid',
            'auth_token' => 'test-auth-token',
            'from_number' =>'+1234567890',
            'timeout' => 30
        ]);

        $this->whatsapp->setClient($this->mockClient);
    }

    /**
     * @test
     */

    public function it_sends_a_message_successfully()
    {
        // successful response
        $this->mockClient->shouldReceive('post')
            ->once()
            ->with('test-phone-number-id/messages', [
                'json' => [
                    'messaging_product' => 'whatsapp',
                    'to' => '1234567890',
                    'type' => 'text',
                    'text' => [
                        'body' => 'Test message'
                    ],
                ]
            ])
            ->andReturn(new Response(200, [], json_encode(['status' => 'success'])));

        $response = $this->whatsapp->sendMessage('+1234567890', 'Test message');

        // assert response 
        $this->assertEquals('success', $response['status']);
    }

    /**
     * @test
     */
    public function it_throws_exception_if_sending_message_fails()
    {
        // failed API response
        $this->mockClient->shouldReceive('post')
            ->once()
            ->andThrow(new \Exception('API error', 500));

    
        $this->expectException(\Chat\WhatsappIntegration\Exceptions\WhatsAppException::class);
        $this->expectExceptionMessage('Failed to send WhatsApp message: API error');

        $this->whatsapp->sendMessage('+1234567890', 'Test message');
    }

    /**
     * @test
     */
    public function it_uses_default_timeout(){
        $whatsapp = new WhatsApp([
            'api_key' => 'test-api-key',
            'phone_number_id' => 'test-phone-number-id'
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
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'messages' => [
                                    [
                                        'id' => 'message-id-123',
                                        'from' => '1234567890',
                                        'timestamp' => '1609459200',
                                        'text' => ['body' => 'Hello!'],
                                        'type' => 'text'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $result = $this->whatsapp->handleWebhook($payload);

        // assert webhook handled correctly
        $this->assertEquals('success', $result['status']);
        $this->assertCount(1, $result['message']);
        $this->assertEquals('Hello!', $result['message'][0]['text']);
    }

     /**
     * @test
     */
    public function it_returns_no_messages_when_webhook_is_empty()
    {
        $payload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => []
                        ]
                    ]
                ]
            ]
        ];

        $result = $this->whatsapp->handleWebhook($payload);

        // assert no messages in payload
        $this->assertEquals('no_messages', $result['status']);
    }

    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
