<?php 

namespace Chat\WhatsappIntegration;

use Chat\WhatsappIntegration\Exceptions\WhatsAppException;
use GuzzleHttp\Client;

class WhatsApp{
    protected $client;
    protected $apiKey;

    protected $baseUrl ="";


    // constructor
    public function __construct($apiKey = null){
        $this->apiKey = $apiKey ?? config('whatsapp.api_key');
        $this->initializeClient();
    }


    // init. GuzzleHttp Client

    protected function initializeClient(){
        $this->client = new Client([
            'base_uri'=> $this->baseUrl,
            'headers'=>[
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],

            'timeout' =>config('whatsapp.timeout',30)
            ]);
    }


    // send whatsapp msg

    public function sendMessage($to $message){
        try{
            $phoneId = config('whatsapp.phone_number_id');

            $response = $this->client->post('{$phoneId}/messages', [
                'json'=> [
                    'messaging_product' => 'whatsapp',
                    'to' => $this->formatPhoneNumber($to),
                    'type' => 'text',
                    'text' => [
                        'body' => $message

                    ]
            ]
            ]);

            return json_decode($response->getBody(), true);
        }
        catch(\Exception $e){
            throw new WhatsAppException(
                'Failed to send WhatsApp message:'. $e->getMessage(),
                $e->getCode(),
            );

    }

    }

    protected function formatPhoneNumber($number){
        return preg_replace('/[^0-9]/','', $number);

    }

    // handle incoming webhook

    public function handleWebhook($payload){
        try{
            if(!isset($payload['entry'][0]['changes'][0]['value']['messages'])){
                return ['status' => 'no_messages'];
        }

        $messages = $payload['entry'][0]['changes'][0]['value']['messages'];
        return[
            'status' =>'success',
            'message'=> array_map(function ($message){
                return[
                    'message_id' =>$message['id'],
                    'from'=>$message['from'],
                    'timestamp'=> $message['timestamp'],
                    'text'=> $message['text']['body'] ?? null,
                    'type' =>$message['type']
                ];

            },$messages),
        ];
    } catch(\Exception $e){
        throw new WhatsAppException(
            'Failed to process webhook:'. $e->getMessage(),
            $e->getCode(),
        );
    }
}




};


