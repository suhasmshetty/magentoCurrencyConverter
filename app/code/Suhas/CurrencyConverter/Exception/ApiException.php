<?php
/**
 * Copyright © Suhas. All rights reserved.
 */
declare(strict_types=1);

namespace Suhas\CurrencyConverter\Exception;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;

/**
 * Error surfaced to the storefront by the currency-converter service.
 *
 * Carries the HTTP status the AJAX layer should return: 400 for bad client input
 * (see {@see self::invalidInput()}), 502 for an upstream/Frankfurter failure (default).
 */
class ApiException extends LocalizedException
{
    private int $httpStatusCode;

    public function __construct(Phrase $phrase, ?\Throwable $cause = null, int $httpStatusCode = 502)
    {
        parent::__construct($phrase, $cause);
        $this->httpStatusCode = $httpStatusCode;
    }

    /**
     * Bad client input (e.g. an invalid currency code) — maps to HTTP 400.
     */
    public static function invalidInput(Phrase $phrase): self
    {
        return new self($phrase, null, 400);
    }

    public function getHttpStatusCode(): int
    {
        return $this->httpStatusCode;
    }
}
