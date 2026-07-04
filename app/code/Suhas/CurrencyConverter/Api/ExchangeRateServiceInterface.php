<?php
/**
 * Copyright © Suhas. All rights reserved.
 */
declare(strict_types=1);

namespace Suhas\CurrencyConverter\Api;

use Suhas\CurrencyConverter\Exception\ApiException;

/**
 * Storefront-facing service for currency data sourced from Frankfurter.
 */
interface ExchangeRateServiceInterface
{
    /**
     * Supported currencies as an ordered map of ISO code => human-readable name.
     *
     * @return array<string,string>
     * @throws ApiException
     */
    public function getCurrencies(): array;

    /**
     * Current exchange rate for a single currency pair.
     *
     * @return array{base:string,quote:string,rate:float,date:string}
     * @throws ApiException
     */
    public function getCurrentRate(string $from, string $to): array;

    /**
     * Historical daily rates for a pair over the last $days days.
     *
     * @return array{base:string,quote:string,series:array<array{date:string,rate:float}>}
     * @throws ApiException
     */
    public function getTimeSeries(string $from, string $to, int $days): array;
}
