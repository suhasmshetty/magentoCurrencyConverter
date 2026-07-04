<?php
/**
 * Copyright © Suhas. All rights reserved.
 */
declare(strict_types=1);

namespace Suhas\CurrencyConverter\Controller\Ajax;

/**
 * GET currencyconverter/ajax/currencies
 *
 * Returns the list of supported currencies as { code, name } objects.
 */
class Currencies extends AbstractAjax
{
    protected function handle(): array
    {
        $currencies = [];
        foreach ($this->service->getCurrencies() as $code => $name) {
            $currencies[] = ['code' => $code, 'name' => $name];
        }

        return ['currencies' => $currencies];
    }
}
