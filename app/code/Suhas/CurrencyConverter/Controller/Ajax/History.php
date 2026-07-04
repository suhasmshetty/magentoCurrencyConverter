<?php
/**
 * Copyright © Suhas. All rights reserved.
 */
declare(strict_types=1);

namespace Suhas\CurrencyConverter\Controller\Ajax;

/**
 * GET currencyconverter/ajax/history?from=USD&to=EUR&days=90
 *
 * Returns a daily time series of exchange rates for a pair.
 */
class History extends AbstractAjax
{
    protected function handle(): array
    {
        $from = (string) $this->request->getParam('from', '');
        $to = (string) $this->request->getParam('to', '');
        $days = (int) $this->request->getParam('days', 0);

        return ['history' => $this->service->getTimeSeries($from, $to, $days)];
    }
}
