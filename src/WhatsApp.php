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

};


