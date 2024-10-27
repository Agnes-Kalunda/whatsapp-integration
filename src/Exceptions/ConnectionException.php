<?php

namespace Chat\WhatsappIntegration\Exceptions;

class ConnectionException extends WhatsAppException
{
    public static function authenticationFailed(): self
    {
        return new self("Failed to authenticate with Twilio API", 3001);
    }

    public static function resourceNotFound(string $message): self
    {
        return new self("Resource not found: {$message}", 3002);
    }

    public static function generalError(string $message, int $code): self
    {
        return new self("Twilio API error: {$message}", $code);
    }
}