<?php

namespace Chat\WhatsappIntegration\Exceptions;

class ValidationException extends WhatsAppException
{
    public static function missingConfig(string $key): self
    {
        return new self("Missing required configuration key: {$key}", 1001);
    }

    public static function invalidPhoneNumber(string $number): self
    {
        return new self("Invalid phone number format: {$number}", 1002);
    }

    public static function invalidTemplateSid(string $sid): self
    {
        return new self("Invalid template SID format: {$sid}", 1003);
    }

    public static function invalidTemplateVariables(): self
    {
        return new self("Invalid template variables format. Must be a valid JSON string", 1004);
    }

    public static function missingSignature(): self
    {
        return new self("Missing webhook signature", 1005);
    }

    public static function missingWebhookUrl(): self
    {
        return new self("Missing webhook URL", 1006);
    }

    public static function invalidSignature(): self
    {
        return new self("Invalid webhook signature", 1007);
    }

    public static function missingMessageSid(): self
    {
        return new self("Missing MessageSid in webhook data", 1008);
    }

    public static function invalidParameters(string $message): self
    {
        return new self("Invalid parameters: {$message}", 1009);
    }
}