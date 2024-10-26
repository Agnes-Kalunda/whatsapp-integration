<?php

namespace Chat\WhatsappIntegration\Exceptions;

class WhatsAppException extends \Exception
{
    protected $errorResponse;

    public function __construct($message = "", $code = 0, \Throwable $previous = null, array $errorResponse = [])
    {
        parent::__construct($message, $code, $previous);
        $this->errorResponse = $errorResponse ?: [
            'status' => 'error',
            'message' => $message,
            'code' => $code
        ];
    }

    public function getErrorResponse()
    {
        return $this->errorResponse;
    }

    public static function missingMessage()
    {
        return new self("Message content is required", 400);
    }

    public static function emptyMessage()
    {
        return new self("Message content cannot be empty", 400);
    }

    public static function missingConfigField($field)
    {
        return new self("Missing required configuration: {$field}", 400);
    }

    public static function emptyConfigField($field)
    {
        return new self("Configuration field '{$field}' cannot be empty", 400);
    }

    public static function invalidPhoneNumber($message)
    {
        return new self($message, 400);
    }

    public static function authenticationFailed()
    {
        return new self("Authentication failed. Please check your credentials.", 401);
    }

    public static function rateLimitExceeded()
    {
        return new self("Rate limit exceeded. Please wait before sending more messages.", 429);
    }
}