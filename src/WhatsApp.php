<?php

namespace Chat\WhatsappIntegration;

use Twilio\Rest\Client;
use Twilio\Security\RequestValidator;
use Chat\WhatsappIntegration\Exceptions\WhatsAppException;
use Chat\WhatsappIntegration\Exceptions\RateLimitException;
use Chat\WhatsappIntegration\Exceptions\ValidationException;
use Chat\WhatsappIntegration\Exceptions\ConnectionException;
use Twilio\Exceptions\RestException;
use Illuminate\Cache\CacheManager;
use Carbon\Carbon;

class WhatsApp 
{
    protected $client;
    protected $config;
    protected $validator;
    protected $cache;
    
    // rate limiting constants
    const RATE_LIMIT_KEY = 'whatsapp_rate_limit:';
    const MAX_REQUESTS_PER_MINUTE = 60;
    const RATE_LIMIT_WINDOW = 60;

    public function __construct(
        array $config, 
        Client $client = null, 
        RequestValidator $validator = null,
        CacheManager $cache = null
    ) {
        $this->validateConfig($config);
        
        $this->config = $config;
        $this->client = $client ?? new Client($config['account_sid'], $config['auth_token']);
        $this->validator = $validator ?? new RequestValidator($config['auth_token']);
        $this->cache = $cache ?? app(CacheManager::class);
    }

    /**
     * validate configuration parameters
     *
     * @param array $config
     * @throws ValidationException
     */
    private function validateConfig(array $config): void
    {
        $requiredKeys = ['account_sid', 'auth_token', 'from_number'];
        
        foreach ($requiredKeys as $key) {
            if (!isset($config[$key]) || empty($config[$key])) {
                throw ValidationException::missingConfig($key);
            }
        }

        // validate phone number format
        if (!preg_match('/^\+[1-9]\d{1,14}$/', $config['from_number'])) {
            throw ValidationException::invalidPhoneNumber($config['from_number']);
        }
    }

    /**
     * check rate limits before sending message
     *
     * @param string $to
     * @throws RateLimitException
     */
    private function checkRateLimit(string $to): void
    {
        $key = self::RATE_LIMIT_KEY . $to;
        $requests = $this->cache->get($key, 0);
        
        if ($requests >= self::MAX_REQUESTS_PER_MINUTE) {
            throw RateLimitException::limitExceeded($to);
        }

        
        $this->cache->put($key, $requests + 1, Carbon::now()->addSeconds(self::RATE_LIMIT_WINDOW));
    }

    /**
     * validate phone number format
     *
     * @param string $number
     * @throws ValidationException
     */
    private function validatePhoneNumber(string $number): void
    {
        if (!preg_match('/^\+[1-9]\d{1,14}$/', $number)) {
            throw ValidationException::invalidPhoneNumber($number);
        }
    }

    /**
     * send a WhatsApp message
     *
     * @param string $to
     * @param string $message
     * @param string|null $contentSid
     * @param string|null $contentVariables
     * @return \Twilio\Rest\Api\V2010\Account\MessageInstance
     * @throws ValidationException|RateLimitException|ConnectionException
     */
    public function sendMessage($to, $message, $contentSid = null, $contentVariables = null)
    {
        // validate phone number
        $this->validatePhoneNumber($to);

        // check rate limits
        $this->checkRateLimit($to);

        try {
            $messageParams = [
                'from' => 'whatsapp:' . $this->config['from_number'],
                'body' => $message
            ];

            if ($contentSid) {
                if (!preg_match('/^HX[a-f0-9]{32}$/', $contentSid)) {
                    throw ValidationException::invalidTemplateSid($contentSid);
                }
                $messageParams['contentSid'] = $contentSid;
            }
            
            if ($contentVariables) {
                if (!is_string($contentVariables) || !json_decode($contentVariables)) {
                    throw ValidationException::invalidTemplateVariables();
                }
                $messageParams['contentVariables'] = $contentVariables;
            }

            return $this->client->messages->create(
                'whatsapp:' . $to,
                $messageParams
            );
        } catch (RestException $e) {
            // map Twilio error codes toexceptions
            switch ($e->getCode()) {
                case 20429: 
                    throw RateLimitException::twilioLimitExceeded();
                case 20003: 
                    throw ConnectionException::authenticationFailed();
                case 20404:
                    throw ConnectionException::resourceNotFound($e->getMessage());
                case 20001: 
                    throw ValidationException::invalidParameters($e->getMessage());
                default:
                    throw ConnectionException::generalError($e->getMessage(), $e->getCode());
            }
        }
    }

    /**
     * validate webhook signature
     *
     * @param string $signature
     * @param string $url
     * @param array $params
     * @return bool
     * @throws ValidationException
     */
    public function validateWebhookSignature($signature, $url, $params)
    {
        if (empty($signature)) {
            throw ValidationException::missingSignature();
        }

        if (empty($url)) {
            throw ValidationException::missingWebhookUrl();
        }

        $isValid = $this->validator->validate($signature, $url, $params);
        
        if (!$isValid) {
            throw ValidationException::invalidSignature();
        }

        return true;
    }

    /**
     * incoming webhook
     *
     * @param array $requestData
     * @param string $url
     * @param string $signature
     * @return array
     * @throws ValidationException
     */
    public function handleWebhook(array $requestData, string $url, string $signature)
    {
        if (empty($requestData['MessageSid'])) {
            throw ValidationException::missingMessageSid();
        }

        
        $this->validateWebhookSignature($signature, $url, $requestData);

    
        return [
            'MessageSid' => $requestData['MessageSid'],
            'From' => $requestData['From'] ?? null,
            'To' => $requestData['To'] ?? null,
            'Body' => $requestData['Body'] ?? null,
            'Status' => $requestData['Status'] ?? null,
            'MediaUrls' => $this->extractMediaUrls($requestData),
            'Timestamp' => $requestData['Timestamp'] ?? now()->toIso8601String(),
            'ErrorCode' => $requestData['ErrorCode'] ?? null,
            'ErrorMessage' => $requestData['ErrorMessage'] ?? null,
        ];
    }

    /**
     * xxtract media URLs from webhook dta
     *
     * @param array $data
     * @return array
     */
    private function extractMediaUrls(array $data): array
    {
        $mediaUrls = [];
        $numMedia = (int) ($data['NumMedia'] ?? 0);
        
        for ($i = 0; $i < $numMedia; $i++) {
            if (isset($data["MediaUrl{$i}"])) {
                $mediaUrls[] = [
                    'url' => $data["MediaUrl{$i}"],
                    'contentType' => $data["MediaContentType{$i}"] ?? null,
                ];
            }
        }
        
        return $mediaUrls;
    }
}