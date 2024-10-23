<?php

namespace Chat\WhatsappIntegration;

use Chat\WhatsappIntegration\Exceptions\WhatsAppException;
use Twilio\Exceptions\RestException;
use Twilio\Rest\Client;
use Twilio\Exceptions\TwilioException;

class WhatsApp {
    protected $client;
    protected $fromNumber;
    protected $timeout = 30;
    protected const MAX_MESSAGE_LENGTH = 1600;
    protected const RATE_LIMIT_ERROR_CODE = 429;
    protected const AUTH_ERROR_CODES = [401, 403];

    public function __construct(array $config = null) {
        if ($config === null) {
            throw new WhatsAppException(
                'WhatsApp configuration is required',
                400
            );
        }
        
        $this->validateConfig($config);
        $this->fromNumber = $config['from_number'];
        $this->timeout = $config['timeout'] ?? 30;
        $this->initializeClient($config['account_sid'], $config['auth_token']);
    }

    protected function initializeClient($accountSid, $authToken) {
        try {
            $this->client = new Client($accountSid, $authToken);
        } catch (TwilioException $e) {
            throw new WhatsAppException('Failed to initialize Twilio client: ' . $e->getMessage(), 401);
        }
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

        foreach ($required as $field) {
            if (empty(trim($config[$field]))) {
                throw new WhatsAppException(
                    "Configuration field '$field' cannot be empty",
                    400
                );
            }
        }

        if (!$this->isValidPhoneNumber($config['from_number'])) {
            throw new WhatsAppException(
                "Invalid 'from_number' format in configuration. Must be E.164 format (e.g., +1234567890)",
                400
            );
        }
    }


    public function sendMessage($to = null, $message = null) {
        // Pre-validate parameters before any API calls
        if ($to === null) {
            throw new WhatsAppException('Recipient phone number is required', 400);
        }

        if ($message === null) {
            throw new WhatsAppException('Message content is required', 400);
        }

        if (!$this->isValidPhoneNumber($to)) {
            throw new WhatsAppException(
                'Invalid recipient phone number format. Must be E.164 format (e.g., +1234567890)',
                400
            );
        }

        $this->validateMessage($message);
        
        try {
            $response = $this->client->messages->create(
                'whatsapp:' . $this->formatPhoneNumber($to),
                [
                    'from' => 'whatsapp:' . $this->fromNumber,
                    'body' => $message
                ]
            );

            return [
                'status' => 'success',
                'message' => $response->sid,
                'to' => $response->to,
                'from' => $response->from
            ];
        } catch (RestException $e) {
            $this->handleTwilioError($e);
        } catch (TwilioException $e) {
            throw new WhatsAppException('Twilio authentication error: ' . $e->getMessage(), 401);
        } catch (\Exception $e) {
            throw new WhatsAppException('Unexpected error while sending WhatsApp message: ' . $e->getMessage(), 500);
        }
    }

    protected function validateMessage($message) {
        if ($message === null || empty(trim($message))) {
            throw new WhatsAppException('Message content cannot be empty', 400);
        }

        if (mb_strlen($message) > self::MAX_MESSAGE_LENGTH) {
            throw new WhatsAppException('Message exceeds maximum length of ' . self::MAX_MESSAGE_LENGTH . ' characters', 400);
        }

        if (!mb_check_encoding($message, 'UTF-8')) {
            throw new WhatsAppException('Message contains invalid characters or encoding', 400);
        }
    }

    protected function handleTwilioError(RestException $e) {
        $code = $e->getCode() ?: $e->getStatusCode();

        switch ($code) {
            case self::RATE_LIMIT_ERROR_CODE:
                throw new WhatsAppException('Rate limit exceeded. Please wait before sending more messages.', 429);
            case in_array($code, self::AUTH_ERROR_CODES):
                throw new WhatsAppException('Authentication failed. Please check your credentials.', 401);
            case 404:
                throw new WhatsAppException('The recipient number is not registered with WhatsApp or is invalid.', 404);
            case 400:
                if (strpos($e->getMessage(), 'unverified') !== false) {
                    throw new WhatsAppException('The recipient number is not verified in your Twilio console.', 403);
                }
                throw new WhatsAppException('Invalid request: ' . $e->getMessage(), 400);
            default:
                throw new WhatsAppException('WhatsApp API error: ' . $e->getMessage(), $code ?: 500);
        }
    }

    protected function isValidPhoneNumber($number) {
        return (bool) preg_match('/^\+[1-9]\d{9,14}$/', $number);
    }

    protected function formatPhoneNumber($number) {
        return preg_replace('/[^0-9]/', '', $number);
    }

    public function handleWebhook($payload = null) {
        if ($payload === null) {
            throw new WhatsAppException('Webhook payload is required', 400);
        }
    
        if (!is_array($payload)) {
            throw new WhatsAppException('Invalid webhook payload format', 400);
        }
    
        try {
            
            if (!isset($payload['Body'])) {
                return ['status' => 'no_messages'];
            }
    
            
            $fromNumber = $payload['From'] ?? '';
            if (!$this->isValidPhoneNumber($fromNumber)) {
                throw new WhatsAppException(
                    'Invalid sender phone number format in webhook payload. Must be E.164 format (e.g., +1234567890)',
                    400
                );
            }
    
            
            $toNumber = $payload['To'] ?? ''; 
            if (!$this->isValidPhoneNumber($toNumber)) {
                throw new WhatsAppException(
                    'Invalid recipient phone number format in webhook payload. Must be E.164 format (e.g., +1234567890)',
                    400
                );
            }
    
            return [
                'status' => 'success',
                'message' => [
                    [
                        'message_id' => $payload['MessageSid'] ?? null,
                        'from' => str_replace('whatsapp:', '', $fromNumber),
                        'to' => str_replace('whatsapp:', '', $toNumber), 
                        'timestamp' => time(),
                        'text' => $payload['Body'],
                        'type' => 'text'
                    ]
                ]
            ];
        } catch (\Exception $e) {
            throw new WhatsAppException('Failed to process webhook: ' . $e->getMessage(), 500);
        }
    }
}    