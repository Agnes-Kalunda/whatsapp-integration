<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Chat\WhatsappIntegration\WhatsApp;
use Chat\WhatsappIntegration\Exceptions\WhatsAppException;
use Chat\WhatsappIntegration\Exceptions\ValidationException;
use Chat\WhatsappIntegration\Exceptions\RateLimitException;
use Twilio\Exceptions\RestException;
use Twilio\Security\RequestValidator;
use Illuminate\Cache\CacheManager;
use Illuminate\Container\Container;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;

class WhatsAppIntegrationTest extends TestCase
{
    protected $whatsapp;
    protected $testPhoneNumber;
    private $messageTracker = [];
    private $authToken;
    private $cache;

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

        // create simple cache instance for testing
        $store = new ArrayStore();
        $this->cache = new Repository($store);

        $this->whatsapp = new WhatsApp($config, null, null, $this->cache);
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
    public function it_validates_phone_number_format()
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid phone number format');
        
        $this->whatsapp->sendMessage('invalid-number', 'Test message');
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
    public function it_validates_template_sid_format()
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid template SID format');
        
        $this->whatsapp->sendMessage(
            $this->testPhoneNumber,
            'Test message',
            'invalid-template-sid',
            json_encode(['1' => 'test'])
        );
    }

    /**
     * @test
     * @group api
     */
    public function it_validates_template_variables_format()
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid template variables format');
        
        $this->whatsapp->sendMessage(
            $this->testPhoneNumber,
            'Test message',
            'HX350d429d32e64a552466cafecbe95f3c',
            'invalid-json'
        );
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
            } catch (RateLimitException $e) {
                $failCount++;
                fwrite(STDERR, "\nRate limit detected as expected\n");
                break;
            } catch (WhatsAppException $e) {
                $this->fail("Unexpected error: " . $e->getMessage());
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
    public function it_validates_config_requirements()
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Missing required configuration key');

        new WhatsApp([
            'account_sid' => 'test',
            // missing auth_token
            'from_number' => '+1234567890'
        ]);
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
            'MediaContentType0' => 'image/jpeg',
            'MediaUrl1' => 'https://example.com/media2.jpg',
            'MediaContentType1' => 'image/jpeg',
        ];

        $validator = new RequestValidator($this->authToken);
        $signature = $validator->computeSignature($url, $requestData);

        $result = $this->whatsapp->handleWebhook($requestData, $url, $signature);

        $this->assertEquals($requestData['MessageSid'], $result['MessageSid']);
        $this->assertEquals($requestData['From'], $result['From']);
        $this->assertEquals($requestData['To'], $result['To']);
        $this->assertEquals($requestData['Body'], $result['Body']);
        $this->assertCount(2, $result['MediaUrls']);
        $this->assertEquals('image/jpeg', $result['MediaUrls'][0]['contentType']);
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

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid webhook signature');

        $invalidSignature = 'invalid_signature_string';
        $this->whatsapp->handleWebhook($requestData, $url, $invalidSignature);
    }

    /**
     * @test
     * @group api
     */
    public function it_validates_required_webhook_data()
    {
        $url = 'https://example.com/webhook';
        $requestData = [
            // missing MessageSid
            'Body' => 'Test message'
        ];

        $validator = new RequestValidator($this->authToken);
        $signature = $validator->computeSignature($url, $requestData);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Missing MessageSid in webhook data');

        $this->whatsapp->handleWebhook($requestData, $url, $signature);
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
        
        foreach ($this->messageTracker as $messageSid) {
            fwrite(STDERR, "\nTracked message SID: $messageSid\n");
        }

        parent::tearDown();
    }
}