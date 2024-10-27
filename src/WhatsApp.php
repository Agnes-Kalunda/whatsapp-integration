<?php

namespace Chat\WhatsappIntegration;

use Twilio\Rest\Client;
use Twilio\Security\RequestValidator;
use Chat\WhatsappIntegration\Exceptions\WhatsAppException;
use Twilio\Exceptions\RestException;

class WhatsApp
{
    protected $client;
    protected $config;
    protected $validator;
    
    public function __construct(array $config, Client $client = null)
    {
        $this->config = $config;
        $this->client = $client ?? new Client($config['account_sid'], $config['auth_token']);
        $this->validator = new RequestValidator($config['auth_token']);
    }
    
    public function sendMessage($to, $message, $contentSid = null, $contentVariables = null)
    {
        try {
            $messageParams = [
                'from' => 'whatsapp:' . $this->config['from_number'],
                'body' => $message
            ];

            if ($contentSid) {
                $messageParams['contentSid'] = $contentSid;
            }
            
            if ($contentVariables) {
                $messageParams['contentVariables'] = $contentVariables;
            }

            return $this->client->messages->create(
                'whatsapp:' . $to,
                $messageParams
            );
        } catch (RestException $e) {
            throw new WhatsAppException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function validateWebhookSignature($signature, $url, $params)
{
    $isValid = $this->validator->validate($signature, $url, $params);
    
    if (!$isValid) {
        throw new WhatsAppException('Invalid Twilio webhook signature.');
    }

    return true; 
}


    public function handleWebhook(array $requestData, string $url, string $signature)
    {
        // validate webhook signature
        if (!$this->validateWebhookSignature($signature, $url, $requestData)) {
            throw new WhatsAppException('Invalid Twilio webhook signature.');
        }

        // process incoming message
        return [
            'MessageSid' => $requestData['MessageSid'] ?? null,
            'From' => $requestData['From'] ?? null,
            'To' => $requestData['To'] ?? null,
            'Body' => $requestData['Body'] ?? null,
            'Status' => $requestData['Status'] ?? null,
            'MediaUrls' => $this->extractMediaUrls($requestData),
        ];
    }

    private function extractMediaUrls(array $data): array
    {
        $mediaUrls = [];
        $numMedia = (int) ($data['NumMedia'] ?? 0);

        for ($i = 0; $i < $numMedia; $i++) {
            $mediaUrls[] = $data["MediaUrl$i"] ?? null;
        }

        return array_filter($mediaUrls);
    }
}
