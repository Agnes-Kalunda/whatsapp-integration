<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Chat\WhatsappIntegration\WhatsApp;
use Chat\WhatsappIntegration\Exceptions\WhatsAppException;
use Twilio\Rest\Client;
use Twilio\Rest\Api\V2010\Account\MessageList;
use Twilio\Rest\Api\V2010\Account\MessageInstance;
use Mockery;

class WhatsAppMockTest extends TestCase
{
    protected $whatsapp;
    protected $mockTwilioClient;
    protected $mockMessageList;

    protected function setUp(): void
    {
        parent::setUp();

        
        $this->mockTwilioClient = Mockery::mock(Client::class);
        $this->mockMessageList = Mockery::mock(MessageList::class);
        
        //config
        $this->config = [
            'account_sid' => 'test_account_sid',
            'auth_token'  => 'test_auth_token',
            'from_number' => '+1234567890'
        ];

        // init mock client
        $this->whatsapp = new WhatsApp($this->config, $this->mockTwilioClient);
    }

    /** @test */
    public function it_sends_message_successfully()
    {
        
        $to = '+254707606316';
        $message = "Test message";
        $expectedResponse = Mockery::mock(MessageInstance::class);
        $expectedResponse->shouldReceive('getSid')->andReturn('SMXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX');
        $expectedResponse->shouldReceive('getStatus')->andReturn('queued');

        
        $this->mockTwilioClient->messages = $this->mockMessageList;
        $this->mockMessageList->shouldReceive('create')
            ->with(
                'whatsapp:' . $to,
                [
                    'from' => 'whatsapp:' . $this->config['from_number'],
                    'body' => $message
                ]
            )
            ->once()
            ->andReturn($expectedResponse);
        $result = $this->whatsapp->sendMessage($to, $message);
        $this->assertEquals('SMXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX', $result->getSid());
        $this->assertEquals('queued', $result->getStatus());
    }

    /** @test */
    public function it_sends_message_with_template()
    {
        
        $to = '+254707606316';
        $message = "Test template message";
        $contentSid = "HXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXYYY";
        $contentVars = json_encode(['1' => 'var1', '2' => 'var2']);
        
        $expectedResponse = Mockery::mock(MessageInstance::class);
        $expectedResponse->shouldReceive('getSid')->andReturn('SMXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX');
        $expectedResponse->shouldReceive('getStatus')->andReturn('queued');

        
        $this->mockTwilioClient->messages = $this->mockMessageList;
        $this->mockMessageList->shouldReceive('create')
            ->with(
                'whatsapp:' . $to,
                [
                    'from' => 'whatsapp:' . $this->config['from_number'],
                    'body' => $message,
                    'contentSid' => $contentSid,
                    'contentVariables' => $contentVars
                ]
            )
            ->once()
            ->andReturn($expectedResponse);

        
        $result = $this->whatsapp->sendMessage($to, $message, $contentSid, $contentVars);
        $this->assertEquals('SMXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX', $result->getSid());
        $this->assertEquals('queued', $result->getStatus());
    }

    /** @test */
    public function it_handles_rate_limit_error()
    {
        
        $to = '+254707606316';
        $message = "Test message";

        $this->mockTwilioClient->messages = $this->mockMessageList;
        $this->mockMessageList->shouldReceive('create')
            ->andThrow(new \Twilio\Exceptions\RestException(
                'Rate limit exceeded for this number',
                429,
                429
            ));

        
        $this->expectException(WhatsAppException::class);
        $this->expectExceptionMessage('Rate limit exceeded for this number');
        $this->expectExceptionCode(429);

        $this->whatsapp->sendMessage($to, $message);
    }

    /** @test */
    public function it_processes_webhook_data_correctly()
    {
        // webhook data
        $webhookData = [
            'MessageSid' => 'SMXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
            'From' => 'whatsapp:+1234567890',
            'To' => 'whatsapp:+0987654321',
            'Body' => 'Test webhook message',
            'NumMedia' => '2',
            'MediaUrl0' => 'https://example.com/media1.jpg',
            'MediaUrl1' => 'https://example.com/media2.jpg',
            'Status' => 'delivered'
        ];

        $url = 'https://example.com/webhook';
        $signature = 'valid_signature';

        // mock signature validation
        $result = $this->whatsapp->handleWebhook($webhookData, $url, $signature);

        // assert webhook processing
        $this->assertEquals($webhookData['MessageSid'], $result['MessageSid']);
        $this->assertEquals($webhookData['From'], $result['From']);
        $this->assertEquals($webhookData['To'], $result['To']);
        $this->assertEquals($webhookData['Body'], $result['Body']);
        $this->assertEquals($webhookData['Status'], $result['Status']);
        $this->assertCount(2, $result['MediaUrls']);
        $this->assertEquals('https://example.com/media1.jpg', $result['MediaUrls'][0]);
        $this->assertEquals('https://example.com/media2.jpg', $result['MediaUrls'][1]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}