<?php

namespace Chat\WhatsappIntegration\Helpers;

use Chat\WhatsappIntegration\Exceptions\ValidationException;
use Illuminate\Support\Facades\Config;

class TemplateHelper
{
    /**
     * Default templates
     * 
     * @var array
     */
    private static $defaultTemplates = [
        'verification_code' => [
            'sid' => 'HX229f5a04fd0510ce1b071852155d3e75',
            'name' => 'verification_code',
            'content' => '{{1}} is your verification code. For your security, do not share this code.',
            'components' => [
                '1' => 'code'
            ]
        ],
        'appointment_reminder' => [
            'sid' => 'HXb5b62575e6e4ff6129ad7c8efe1f983e',
            'name' => 'appointment_reminder',
            'content' => 'Your appointment is coming up on {{1}} at {{2}}',
            'components' => [
                '1' => 'date',
                '2' => 'time'
            ]
        ],
        'order_confirmation' => [
            'sid' => 'HX350d429d32e64a552466cafecbe95f3c',
            'name' => 'order_confirmation',
            'content' => 'Thank you for your order. Your delivery is scheduled for {{1}} at {{2}}. If you need to change it, please reply back and let us know.',
            'components' => [
                '1' => 'date',
                '2' => 'time'
            ]
        ]
    ];

    /**
     * Get template by name
     *
     * @param string $templateName
     * @return array|null
     */
    public static function getTemplate(string $templateName): ?array
    {
        // Try to get from config first
        try {
            $configTemplates = Config::get('whatsapp.templates', []);
            if (isset($configTemplates[$templateName])) {
                return $configTemplates[$templateName];
            }
        } catch (\Exception $e) {
           
        }

        return self::$defaultTemplates[$templateName] ?? null;
    }

    /**
     * Get all available templates
     *
     * @return array
     */
    public static function getAllTemplates(): array
    {
        try {
            $configTemplates = Config::get('whatsapp.templates', []);
            return array_merge(self::$defaultTemplates, $configTemplates);
        } catch (\Exception $e) {
            return self::$defaultTemplates;
        }
    }

    /**
     * Validate template variables
     *
     * @param array $template
     * @param array $variables
     * @throws ValidationException
     */
    public static function validateTemplateVariables(array $template, array $variables): void
    {
        if (!isset($template['components']) || !is_array($template['components'])) {
            throw ValidationException::invalidTemplateFormat($template['name'] ?? 'unknown');
        }

        foreach ($template['components'] as $key => $type) {
            if (!isset($variables[$key])) {
                throw ValidationException::missingTemplateVariable($key, $template['name']);
            }

            self::validateVariableType($variables[$key], $type, $key, $template['name']);
        }
    }

    /**
     * Validate variable type
     *
     * @param mixed $value
     * @param string $type
     * @param string $key
     * @param string $templateName
     * @throws ValidationException
     */
    private static function validateVariableType($value, string $type, string $key, string $templateName): void
    {
        switch ($type) {
            case 'date':
                if (!strtotime($value)) {
                    throw ValidationException::invalidTemplateVariableType($key, $templateName, 'date');
                }
                break;
            case 'time':
                if (!preg_match('/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/', $value)) {
                    throw ValidationException::invalidTemplateVariableType($key, $templateName, 'time (HH:MM)');
                }
                break;
            case 'code':
                if (!is_numeric($value) || strlen($value) !== 6) {
                    throw ValidationException::invalidTemplateVariableType($key, $templateName, '6-digit code');
                }
                break;
        }
    }

    /**
     * Build template message
     *
     * @param array $template
     * @param array $variables
     * @return string
     */
    public static function buildTemplateMessage(array $template, array $variables): string
    {
        if (!isset($template['content'])) {
            throw ValidationException::invalidTemplateFormat($template['name'] ?? 'unknown');
        }

        $message = $template['content'];
        foreach ($variables as $key => $value) {
            $message = str_replace("{{$key}}", $value, $message);
        }
        return $message;
    }
}