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
        'order_confirmation' => [
            'sid' => 'HX350d429d32e64a552466cafecbe95f3c',
            'name' => 'order_confirmation',
            'content' => 'Your order has been confirmed. Your delivery is scheduled for {{1}} at {{2}}.',
            'components' => [
                '1' => 'date',
                '2' => 'time'
            ]
        ],
        'delivery_update' => [
            'sid' => 'HX123456789abcdef123456789abcdef12',
            'name' => 'delivery_update',
            'content' => 'Your delivery status has been updated to {{1}}.',
            'components' => [
                '1' => 'status'
            ]
        ],
        'payment_received' => [
            'sid' => 'HX987654321abcdef123456789abcdef12',
            'name' => 'payment_received',
            'content' => 'We have received your payment of {{1}}. Thank you!',
            'components' => [
                '1' => 'amount'
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
            // Config not available, fall back to default templates
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
            case 'amount':
                if (!is_numeric($value)) {
                    throw ValidationException::invalidTemplateVariableType($key, $templateName, 'numeric amount');
                }
                break;
            case 'status':
                $validStatuses = ['pending', 'shipped', 'delivered', 'cancelled'];
                if (!in_array(strtolower($value), $validStatuses)) {
                    throw ValidationException::invalidTemplateVariableType($key, $templateName, 'status (' . implode(', ', $validStatuses) . ')');
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