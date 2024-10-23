<?php 

namespace Chat\WhatsappIntegration;

use Chat\WhatsappIntegration\Exceptions\WhatsAppException;
use Twilio\Exceptions\RestException;
// use GuzzleHttp\Client;
use Twilio\Rest\Client;
use Twilio\Exceptions\TwilioException;

class WhatsApp {
    protected $client;
    protected $fromNumber;
    // protected $apiKey;

    protected $timeout = 30;
    protected const MAX_MESSAGE_LENGTH =1600;
    protected const RATE_LIMIT_ERROR_CODE = 429;
    protected const AUTH_ERROR_CODES = [401,403];
    

    // constructor
    
    public function __construct(array $config) {
        $this->validateConfig($config);
        $this->fromNumber = $config['from_number'];
        $this->timeout = $config['timeout'] ?? 30;
        $this->initializeClient($config['account_sid'], $config['auth_token']);
    }

    protected function validateConfig(array $config) {
        $required = ['account_sid', 'auth_token', 'from_number'];
        $missing = array_diff($required, array_keys($config));

        if (!empty($missing)) {
            throw new WhatsAppException(
                "Missing required configuration: " . implode(', ', $missing),
                400
            );
        }

        if (!$this->isValidPhoneNumber($config['from_number'])) {
            throw new WhatsAppException(
                "Invalid 'from_number' format in configuration. Must be E.164 format (e.g., +1234567890)",
                400
            );
        }
    }



    // init. Twilio Client
    protected function initializeClient($accountSid, $authToken) {
        try {
            $this->client = new Client($accountSid, $authToken);
        } catch (TwilioException $e) {
            throw new WhatsAppException(
                'Failed to initialize Twilio client: Invalid credentials',
                401
            );
        } catch (\Exception $e) {
            throw new WhatsAppException(
                'Failed to initialize Twilio client: ' . $e->getMessage(),
                500
            );
        }
    }


    // send whatsapp msg -twilio
    public function sendMessage($to, $message) {
        if (!$this->isValidPhoneNumber($to)) {
            throw new WhatsAppException('Invalid recipient phone number format.', 400);
        }

        $this->validateMessage($message);
        try{
            $response = $this->client->messages->create(
                'whatsapp:' . $this->formatPhoneNumber($to),
                [
                    'from' =>'whatsapp:' . $this->fromNumber,
                    'body' => $message

                ]
            );

            return [
                'status'=> 'success',
                'message' => $response->sid,
                'to' => $response->to,
                'from' => $response->from

            ];
        } catch(RestException $e) {
            $this->handleTwilioError($e);
        } catch (\Exception $e) {
            throw new WhatsAppException(
                'Unexpected error while sending WhatsApp message:' . $e->getMessage(), 500);

        }
    }


    protected function validateMessage($message){
        if (empty(trim($message))) {
            throw new WhatsAppException('Message content cannot be empty', 400);
        }

        if (mb_strlen($message) > self::MAX_MESSAGE_LENGTH) {
            throw new WhatsAppException(
                'Message exceeds maximum length of ' . self::MAX_MESSAGE_LENGTH . ' characters',
                400
            );
        }

        // Check for invalid encoding
        if (!mb_check_encoding($message, 'UTF-8')) {
            throw new WhatsAppException(
                'Message contains invalid characters or encoding',
                400
            );
        }
    

    }

    protected function handleTwilioError(RestException $e) {
        $code = $e->getCode() ?: $e->getStatusCode();
        
        switch ($code) {
            case self::RATE_LIMIT_ERROR_CODE:
                throw new WhatsAppException(
                    'Rate limit exceeded. Please wait before sending more messages.',
                    429
                );
            
            case in_array($code, self::AUTH_ERROR_CODES):
                throw new WhatsAppException(
                    'Authentication failed. Please check your credentials.',
                    401
                );
            
            case 404:
                throw new WhatsAppException(
                    'The recipient number is not registered with WhatsApp or is invalid.',
                    404
                );
                
            case 400:
                if (strpos($e->getMessage(), 'unverified') !== false) {
                    throw new WhatsAppException(
                        'The recipient number is not verified in your Twilio console.',
                        403
                    );
                }
                throw new WhatsAppException(
                    'Invalid request: ' . $e->getMessage(),
                    400
                );
                
            default:
                throw new WhatsAppException(
                    'WhatsApp API error: ' . $e->getMessage(),
                    $code ?: 500
                );
        }
    }


    protected function isValidPhoneNumber($number) {
        return (bool) preg_match('/^\+[1-9]\d{9,14}$/', $number);
    }

    protected function formatPhoneNumber($number) {
        return preg_replace('/[^0-9]/', '', $number);
    }

    // handle incoming webhook
    public function handleWebhook($payload) {
        if (!is_array($payload)) {
            throw new WhatsAppException('Invalid webhook payload format', 400);
        }

        try {
            if (!isset($payload['Body'])) {
                return ['status' => 'no_messages'];
            }

            return [
                'status' => 'success',
                'message' => [
                    [
                        'message_id' => $payload['MessageSid'] ?? null,
                        'from' => str_replace('whatsapp:', '', $payload['From'] ?? ''),
                        'timestamp' => time(),
                        'text' => $payload['Body'],
                        'type' => 'text'
                    ]
                ]
            ];
        } catch (\Exception $e) {
            throw new WhatsAppException(
                'Failed to process webhook: ' . $e->getMessage(),
                500
            );
        }
    }
    
    public function setClient($client) {
        $this->client = $client;
        return $this;
    }
}