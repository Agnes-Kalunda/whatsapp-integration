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
    // protected $timeout = 30;
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

    // send whatsapp msg
    public function sendMessage($to, $message) {
        try {
            $response = $this->client->post($this->phoneNumberId .'/messages', [
                'json' => [
                    'messaging_product' => 'whatsapp',
                    'to' => $this->formatPhoneNumber($to),
                    'type' => 'text',
                    'text' => [
                        'body' => $message
                    ]
                ]
            ]);

            return json_decode($response->getBody(), true);
        } catch(\Exception $e) {
            throw new WhatsAppException(
                'Failed to send WhatsApp message: ' . $e->getMessage(), // Added space after colon
                $e->getCode()
            );
        }
    }
    protected function formatPhoneNumber($number) {
        return preg_replace('/[^0-9]/', '', $number);
    }

    // handle incoming webhook
    public function handleWebhook($payload) {
        try {
            if(!isset($payload['entry'][0]['changes'][0]['value']['messages'])) {
                return ['status' => 'no_messages'];
            }

            // iterate over msgs and format into a usable structure
            $messages = $payload['entry'][0]['changes'][0]['value']['messages'];
            return [
                'status' => 'success',
                'message' => array_map(function ($message) {
                    return [
                        'message_id' => $message['id'],
                        'from' => $message['from'],
                        'timestamp' => $message['timestamp'],
                        'text' => $message['text']['body'] ?? null,
                        'type' => $message['type']
                    ];
                }, $messages)
            ];
        } catch(\Exception $e) {
            throw new WhatsAppException(
                'Failed to process webhook:'. $e->getMessage(),
                $e->getCode()
            );
        }
    }

    // set client
    public function setClient($client) {
        $this->client = $client;
    }
}