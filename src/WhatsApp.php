<?php 

namespace Chat\WhatsappIntegration;

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

    

    }

};


