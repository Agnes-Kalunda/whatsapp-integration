<?php 

namespace Chat\WhatsappIntegration;

use Chat\WhatsappIntegration\Exceptions\WhatsAppException;
// use GuzzleHttp\Client;
use Twilio\Rest\Client;

class WhatsApp {
    protected $client;
    protected $fromNumber;
    protected $apiKey;
    // protected $phoneNumberId;
    protected $timeout = 30;
    // protected $baseUrl = "https://graph.whatsapp.com/v1/";

    // constructor
    
    public function __construct(array $config) {
        $this->validateConfig($config);
        $this->fromNumber = $config['from_number'];
        $this->timeout = $config['timeout'] ?? 30;
        $this->initializeClient($config['account_sid'], $config['auth_token']);
    }

    protected function validateConfig(array $config) {
        $required = ['account_sid', 'auth_token', 'from_number'];
        foreach ($required as $field) {
            if (!isset($config[$field])) {
                throw new WhatsAppException("Missing required configuration: {$field}");
            }
        }
    }


    // init. Twilio Client
    protected function initializeClient($accountSid, $authToken) {
        try {
            $this->client = new Client($accountSid, $authToken);
        } catch (\Exception $e) {
            throw new WhatsAppException(
                'Failed to initialize Twilio client: ' . $e->getMessage(),
                $e->getCode()
            );
        }
    }

    // send whatsapp msg -twilio
    public function sendMessage($to, $message) {
        if (!$this->isValidPhoneNumber($to)) {
            throw new WhatsAppException('Invalid phone number format.');
        }

        if(empty(trim($message))) {
            throw new WhatsAppException('Message cannot be empty');
        }
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
                'message_sid' => $response->sid,
                'to' => $response->to,
                'from' => $response->from
            ];
        } catch (\Twilio\Exceptions\RestException $e) {
            if($e->getStatusCode() == 429) {
                throw new WhatsAppException('Quota exceeded: Too many requests. Please try again later.', $e->getCode());
            }
            throw new WhatsAppException('Failed to send WhatsApp message: ' . $e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            throw new WhatsAppException('Failed to send WhatsApp message: ' . $e->getMessage(), $e->getCode());
        }
    }
        
    

    protected function isValidPhoneNumber($number) {
        return preg_match('/^\+\d{1,3}\d{1,14}(\s\d{1,13})?$/', $number);
    }
    protected function formatPhoneNumber($number) {
        return preg_replace('/[^0-9]/', '', $number);
    }

    // handle incoming webhook
    public function handleWebhook($payload) {
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
                $e->getCode()
            );
        }
    }
    
    // set client
    public function setClient($client) {
        $this->client = $client;
    }
}