<?php
/**
 * Copyright © Suhas. All rights reserved.
 */
declare(strict_types=1);

namespace Suhas\CurrencyConverter\Controller\Ajax;

/**
 * GET currencyconverter/ajax/rate?from=USD&to=EUR
 *
 * Returns the current exchange rate for a single currency pair.
 */
class Rate extends AbstractAjax
{
    protected function handle(): array
    {
        $from = (string) $this->request->getParam('from', '');
        $to = (string) $this->request->getParam('to', '');

        return ['rate' => $this->service->getCurrentRate($from, $to)];
    }
}
