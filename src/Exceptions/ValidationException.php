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

    /**
     * Create a new exception for missing template variable
     *
     * @param string $key
     * @param string $templateName
     * @return self
     */
    public static function missingTemplateVariable(string $key, string $templateName): self
    {
        return new self(
            "Missing required template variable '{$key}' for template '{$templateName}'",
            1010
        );
    }

    /**
     * Create a new exception for invalid template variable type
     *
     * @param string $key
     * @param string $templateName
     * @param string $expectedType
     * @return self
     */
    public static function invalidTemplateVariableType(string $key, string $templateName, string $expectedType): self
    {
        return new self(
            "Invalid type for template variable '{$key}' in template '{$templateName}'. Expected {$expectedType}",
            1011
        );
    }

    /**
     * Create a new exception for template not found
     *
     * @param string $templateName
     * @return self
     */
    public static function templateNotFound(string $templateName): self
    {
        return new self(
            "Template not found: {$templateName}",
            1012
        );
    }

    /**
     * Create a new exception for invalid template format
     *
     * @param string $templateName
     * @return self
     */
    public static function invalidTemplateFormat(string $templateName): self
    {
        return new self(
            "Invalid template format for template '{$templateName}'. Missing required fields",
            1013
        );
    }

    /**
     * Create a new exception for invalid template component
     *
     * @param string $component
     * @param string $templateName
     * @return self
     */
    public static function invalidTemplateComponent(string $component, string $templateName): self
    {
        return new self(
            "Invalid template component '{$component}' in template '{$templateName}'",
            1014
        );
    }
}