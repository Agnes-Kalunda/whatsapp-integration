<?php

namespace Twilio\Exceptions;

class RestException extends TwilioException {
    protected $statusCode;
    protected $details;
    protected $moreInfo;

    /**
     * Construct the exception.
     *
     * @param string $message The Exception message to throw.
     * @param int $code The Exception code.
     * @param int $statusCode The HTTP Status code.
     * @param string $moreInfo More information about the error.
     * @param array $details Additional details about the error.
     */
    public function __construct(string $message, int $code, int $statusCode = 500, string $moreInfo = '', array $details = []) {
        $this->statusCode = $statusCode;
        $this->moreInfo = $moreInfo;
        $this->details = $details;
        parent::__construct($message, $code);
    }

    /**
     * Get the HTTP Status Code of the RestException.
     *
     * @return int HTTP Status Code
     */
    public function getStatusCode(): int {
        return $this->statusCode;
    }

    /**
     * Get more information of the RestException.
     *
     * @return string More error information
     */
    public function getMoreInfo(): string {
        return $this->moreInfo;
    }

    /**
     * Get the details of the RestException.
     *
     * @return array Exception details
     */
    public function getDetails(): array {
        return $this->details;
    }
}
