<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Chat\WhatsappIntegration\WhatsApp;
use Chat\WhatsappIntegration\Exceptions\WhatsAppException;
use Twilio\Exceptions\RestException;
use Twilio\Security\RequestValidator;

class WhatsAppIntegrationTest extends TestCase
{
    protected $whatsapp;
    protected $testPhoneNumber;
    private $messageTracker = [];
    private $authToken;

    protected function setUp(): void
    {
        parent::setUp();

       
        if (file_exists(__DIR__ . '/../../.env')) {
            $dotenv = \Dotenv\Dotenv::create(__DIR__ . '/../../');
            $dotenv->load();
        }

        $this->authToken = getenv('TWILIO_AUTH_TOKEN');
        
       
        $config = [
            'account_sid' => getenv('TWILIO_ACCOUNT_SID'),
            'auth_token'  => $this->authToken,
            'from_number' => getenv('TWILIO_FROM_NUMBER')
        ];

      
        foreach ($config as $key => $value) {
            if (empty($value)) {
                $this->markTestSkipped("Missing required environment variable for: {$key}");
            }
        }

        $this->whatsapp = new WhatsApp($config);
        $this->testPhoneNumber = '+254707606316';
    }

    /**
     * @test
     * @group api
     */
    public function it_sends_basic_message_successfully()
    {
        $message = "Test message from WhatsApp Integration " . date('Y-m-d H:i:s');

        try {
            $result = $this->whatsapp->sendMessage($this->testPhoneNumber, $message);
            
    
            $this->messageTracker[] = $result->sid;

            
            $this->assertNotNull($result->sid);
            $this->assertTrue(in_array($result->status, ['queued', 'sent', 'delivered']));
            
           
            fwrite(STDERR, "\nMessage sent successfully - SID: " . $result->sid . "\n");
            
            return $result->sid;
        } catch (WhatsAppException $e) {
            $this->fail("Failed to send message: " . $e->getMessage());
        }
    }

    /**
     * @test
     * @group api
     */
    public function it_sends_template_message_successfully()
    {
        $contentSid = "HX350d429d32e64a552466cafecbe95f3c";
        $message = "Your order has been confirmed. Your delivery is scheduled for 12/1 at 3pm.";
        $contentVars = json_encode(['1' => '12/1', '2' => '3pm']);

        try {
            $result = $this->whatsapp->sendMessage(
                $this->testPhoneNumber,
                $message,
                $contentSid,
                $contentVars
            );

            
            $this->messageTracker[] = $result->sid;

            $this->assertNotNull($result->sid);
            $this->assertTrue(in_array($result->status, ['queued', 'sent', 'delivered']));

            fwrite(STDERR, "\nTemplate message sent successfully - SID: " . $result->sid . "\n");
        } catch (WhatsAppException $e) {
            $this->fail("Failed to send template message: " . $e->getMessage());
        }
    }

    /**
     * @test
     * @group api
     */
    public function it_handles_rate_limiting_gracefully()
    {
        $message = "Rate limit test message";
        $attempts = 0;
        $maxAttempts = 3;
        $successCount = 0;
        $failCount = 0;

        while ($attempts < $maxAttempts) {
            try {
                $result = $this->whatsapp->sendMessage($this->testPhoneNumber, $message);
                $this->messageTracker[] = $result->sid;
                $successCount++;
                
              
                sleep(1);
            } catch (WhatsAppException $e) {
                $failCount++;
                if ($e->getCode() === 429) {
                    fwrite(STDERR, "\nRate limit detected as expected\n");
                    break;
                } else {
                    throw $e;
                }
            }
            $attempts++;
        }

        fwrite(STDERR, "\nRate limit test complete - Successes: $successCount, Failures: $failCount\n");
        $this->assertTrue($successCount > 0 || $failCount > 0);
    }

    /**
     * @test
     * @group api
     */
    public function it_handles_authentication_error()
    {
        // setup whatsApp with invalid credentials
        $invalidConfig = [
            'account_sid' => 'invalid_account_sid',
            'auth_token'  => 'invalid_auth_token',
            'from_number' => getenv('TWILIO_FROM_NUMBER')
        ];

        $whatsapp = new WhatsApp($invalidConfig);

        $this->expectException(WhatsAppException::class);
        
        $whatsapp->sendMessage($this->testPhoneNumber, "Test message");
    }

    /**
     * @test
     * @group api
     */
    public function it_validates_webhook_signature()
    {
        
        $url = 'https://example.com/webhook';
        $params = [
            'MessageSid' => 'SMXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
            'From' => 'whatsapp:+1234567890',
            'To' => 'whatsapp:' . $this->testPhoneNumber,
            'Body' => 'Test webhook message',
        ];

        // valid signature using Twilio's RequestValidator
        $validator = new RequestValidator($this->authToken);
        $signature = $validator->computeSignature($url, $params);

        try {
            $result = $this->whatsapp->validateWebhookSignature($signature, $url, $params);
            $this->assertTrue($result);
        } catch (WhatsAppException $e) {
            $this->fail("Webhook validation failed: " . $e->getMessage());
        }
    }

    /**
     * @test
     * @group api
     */
    public function it_handles_webhook_data()
    {
        $url = 'https://example.com/webhook';
        $requestData = [
            'MessageSid' => 'SMXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
            'From' => 'whatsapp:+1234567890',
            'To' => 'whatsapp:' . $this->testPhoneNumber,
            'Body' => 'Test webhook message',
            'NumMedia' => 2,
            'MediaUrl0' => 'https://example.com/media1.jpg',
            'MediaUrl1' => 'https://example.com/media2.jpg',
        ];

        // valid signature using Twilio's RequestValidator
        $validator = new RequestValidator($this->authToken);
        $signature = $validator->computeSignature($url, $requestData);

        $result = $this->whatsapp->handleWebhook($requestData, $url, $signature);

        $this->assertEquals($requestData['MessageSid'], $result['MessageSid']);
        $this->assertEquals($requestData['From'], $result['From']);
        $this->assertEquals($requestData['To'], $result['To']);
        $this->assertEquals($requestData['Body'], $result['Body']);
        $this->assertCount(2, $result['MediaUrls']);
    }

    /**
     * @test
     * @group api
     */
    public function it_handles_invalid_webhook_signature()
    {
        $url = 'https://example.com/webhook';
        $requestData = [
            'MessageSid' => 'SMXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
            'Body' => 'Test message'
        ];

        $this->expectException(WhatsAppException::class);
        $this->expectExceptionMessage('Invalid Twilio webhook signature.');

        // invalid signature
        $invalidSignature = 'invalid_signature_string';
        $this->whatsapp->handleWebhook($requestData, $url, $invalidSignature);
    }

    /**
     * @test
     * @group api
     */
    public function it_handles_missing_webhook_data()
    {
        $url = 'https://example.com/webhook';
        $requestData = [
            'MessageSid' => 'SMXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
        
        ];

        // valid signature
        $validator = new RequestValidator($this->authToken);
        $signature = $validator->computeSignature($url, $requestData);

        $result = $this->whatsapp->handleWebhook($requestData, $url, $signature);

       
        $this->assertNull($result['Body']);
        $this->assertNull($result['From']);
        $this->assertNull($result['To']);
        $this->assertEmpty($result['MediaUrls']);
    }

    protected function tearDown(): void
    {
        // log tracked messages
        foreach ($this->messageTracker as $messageSid) {
            fwrite(STDERR, "\nTracked message SID: $messageSid\n");
        }

        parent::tearDown();
    }
}