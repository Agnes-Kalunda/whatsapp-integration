<?php

namespace Chat\WhatsappIntegration\Exceptions;

use Exception;

class WhatsAppException extends Exception
{
    /**
     * Create a new WhatsApp exception instance.
     *
     * @param string $message
     * @param int $code
     * @param \Throwable|null $previous
     * @return void
     */
    public function __construct(string $message = "", int $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}