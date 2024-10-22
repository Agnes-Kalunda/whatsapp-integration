<?php 

namespace Chat\WhatsappIntegration;

use Chat\WhatsappIntegration\Exceptions\WhatsAppException;
use GuzzleHttp\Client;

class WhatsApp {
    protected $client;
    protected $apiKey;
    protected $phoneNumberId;
    protected $timeout = 30;
    protected $baseUrl = "https://graph.whatsapp.com/v1/";

    protected $provider;
    // constructor
    
    public function __construct(array $config, $provider = 'default') {

        $this->provider = $provider;

        if( $this->provider == 'twilio') {
            $this->accountSid = $config['account_sid'];
            $this->authToken = $config['auth_token'];
            $this->twilioPhoneNumber = $config['twilio_phone_number'];
            $this->initializeTwilioClient();
        } else{
            $this->apiKey = $config['api_key'];
            $this->phoneNumberId = $config['phone_number_id'];
            $this->timeout = $config['timeout'] ?? 30;
            $this->initializeDefaultClient();
        }
    }

        

    // init. GuzzleHttp Client - default provider
    protected function initializeDefaultClient() {
        $this->client = new  \GuzzleHttp\Client([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'timeout' => $this->timeout
        ]);
    }

    // init. Twilio Client
    protected function initializeTwilioClient() {
        $this->client = new TwilioClient($this->accountSid, $this->authToken);

    }

    // send message - default provider
    public function sendMessage($to, $message){
        if($this->provider === 'twilio'){
            return $this->sendMessage($to, $message);

        }
        return $this->sendMessage($to, $message);
    }

    // send whatsapp msg - Twilio API
    public function sendTwilioMessage($to, $message) {
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