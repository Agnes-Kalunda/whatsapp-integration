<?php

namespace Chat\WhatsappIntegration\Exceptions;

use Chat\WhatsappIntegration\WhatsApp;

class RateLimitException extends WhatsAppException
{
    public static function limitExceeded(string $number): self
    {
        return new self(
            "Rate limit exceeded for number {$number}. Maximum " . WhatsApp::MAX_REQUESTS_PER_MINUTE . " requests per minute allowed.", 
            2001
        );
    }

    public static function twilioLimitExceeded(): self
    {
        return new self("Twilio rate limit exceeded. Please try again later.", 2002);
    }
}