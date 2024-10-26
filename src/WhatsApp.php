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
    protected $testMode = false;
    
    protected function sendErrorResponse($message, $code) {
        if ($this->testMode) {
            throw new WhatsAppException($message, $code);
        }
        
        http_response_code($code);
        return [
            'status' => 'error',
            'message' => $message,
            'code' => $code
        ];
    }

    public function enableTestMode() {
        $this->testMode = true;
    }

    public function __construct(array $config = null) {
        if ($config === null) {
            throw new WhatsAppException("WhatsApp configuration is required", 400);
        }
        
        try {
            $this->validateConfig($config);
            
            $this->fromNumber = $config['from_number'];
            $this->timeout = $config['timeout'] ?? 30;
            
            
            if (defined('PHPUNIT_COMPOSER_INSTALL') || defined('__PHPUNIT_PHAR__')) {
                $this->enableTestMode();
            }

            $this->initializeClient($config['account_sid'], $config['auth_token']);
        } catch (WhatsAppException $e) {
            throw $e;
        }
    }

    protected function validateConfig(array $config) {
        $required = ['account_sid', 'auth_token', 'from_number'];
        $missing = array_diff($required, array_keys($config));

        if (!empty($missing)) {
            throw new WhatsAppException("Missing required configuration: " . implode(', ', $missing), 400);
        }

        foreach ($required as $field) {
            if (empty(trim($config[$field]))) {
                throw new WhatsAppException("Configuration field '{$field}' cannot be empty", 400);
            }
        }

        if (!$this->isValidPhoneNumber($config['from_number'])) {
            throw new WhatsAppException("Invalid 'from_number' format in configuration", 400);
        }
    }

    protected function initializeClient($accountSid, $authToken) {
        try {
            $this->client = new Client($accountSid, $authToken);
        } catch (TwilioException $e) {
            throw new WhatsAppException("Authentication failed. Please check your credentials.", 401);
        }
    }

    protected function validateMessage($message) {
        if ($message === null) {
            throw new WhatsAppException("Message content is required", 400);
        }
        
        if (empty(trim($message))) {
            throw new WhatsAppException("Message content cannot be empty", 400);
        }

        if (mb_strlen($message) > self::MAX_MESSAGE_LENGTH) {
            throw new WhatsAppException(
                'Message exceeds maximum length of ' . self::MAX_MESSAGE_LENGTH . ' characters',
                400
            );
        }

        if (!mb_check_encoding($message, 'UTF-8')) {
            throw new WhatsAppException('Message contains invalid characters or encoding', 400);
        }
    }

    protected function isValidPhoneNumber($number) {
        return (bool) preg_match('/^\+[1-9]\d{9,14}$/', $number);
    }

    protected function formatPhoneNumber($number) {
        // remove all non-digit characters except the plus sign
        $formatted = preg_replace('/[^\d+]/', '', $number);
        return $formatted;
    }

    public function sendMessage($to = null, $message = null) {
        try {
            if ($to === null) {
                throw new WhatsAppException("Recipient phone number is required", 400);
            }

            if (!$this->isValidPhoneNumber($to)) {
                throw new WhatsAppException("Invalid recipient phone number format", 400);
            }

            $this->validateMessage($message);
            
            $response = $this->client->messages->create(
                'whatsapp:' . $this->formatPhoneNumber($to),
                [
                    'from' => 'whatsapp:' . $this->formatPhoneNumber($this->fromNumber),
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
            $error = $this->handleTwilioError($e);
            throw new WhatsAppException($error['message'], $error['code']);
        } catch (TwilioException $e) {
            throw new WhatsAppException('Authentication failed. Please check your credentials.', 401);
        } catch (WhatsAppException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new WhatsAppException('Unexpected error while sending WhatsApp message: ' . $e->getMessage(), 500);
        }
    }

    protected function handleTwilioError(RestException $e) {
        $code = $e->getCode() ?: $e->getStatusCode();
        $message = $e->getMessage();
        
        switch ($code) {
            case self::RATE_LIMIT_ERROR_CODE:
                return [
                    'message' => 'Rate limit exceeded. Please wait before sending more messages.',
                    'code' => 429
                ];
            case in_array($code, self::AUTH_ERROR_CODES):
                return [
                    'message' => 'Authentication failed. Please check your credentials.',
                    'code' => 401
                ];
            case 404:
                return [
                    'message' => 'The recipient number is not registered with WhatsApp or is invalid.',
                    'code' => 404
                ];
            case 400:
                if (strpos($message, 'unverified') !== false) {
                    return [
                        'message' => 'The recipient number is not verified in your Twilio console.',
                        'code' => 403
                    ];
                }
                return [
                    'message' => 'Invalid request: ' . $message,
                    'code' => 400
                ];
            default:
                return [
                    'message' => 'WhatsApp API error: ' . $message,
                    'code' => $code ?: 500
                ];
        }
    }

    public function handleWebhook($payload = null) {
        try {
            if ($payload === null) {
                throw new WhatsAppException("Webhook payload is required", 400);
            }

            if (!is_array($payload)) {
                throw new WhatsAppException("Invalid webhook payload format", 400);
            }

            if (!isset($payload['Body'])) {
                return ['status' => 'no_messages'];
            }

            $fromNumber = $payload['From'] ?? '';
            $toNumber = $payload['To'] ?? '';

            
            $fromNumber = str_replace('whatsapp:', '', $fromNumber);
            $toNumber = str_replace('whatsapp:', '', $toNumber);

            if (!$this->isValidPhoneNumber($fromNumber)) {
                throw new WhatsAppException("Invalid sender phone number format in webhook payload. Must be E.164 format (e.g., +1234567890)", 400);
            }

            if (!$this->isValidPhoneNumber($toNumber)) {
                throw new WhatsAppException("Invalid recipient phone number format in webhook payload. Must be E.164 format (e.g., +1234567890)", 400);
            }

            return [
                'status' => 'success',
                'message' => [
                    [
                        'message_id' => $payload['MessageSid'] ?? null,
                        'from' => $fromNumber,
                        'to' => $toNumber,
                        'timestamp' => time(),
                        'text' => $payload['Body'],
                        'type' => 'text'
                    ]
                ]
            ];
        } catch (WhatsAppException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new WhatsAppException('Failed to process webhook: ' . $e->getMessage(), 500);
        }
    }
}