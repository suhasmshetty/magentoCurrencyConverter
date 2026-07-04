<?php
/**
 * Copyright © Suhas. All rights reserved.
 */
declare(strict_types=1);

namespace Suhas\CurrencyConverter\Block;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Suhas\CurrencyConverter\Model\Config;

/**
 * View model/block for the converter page.
 *
 * Exposes the endpoint URLs and defaults the JS widget needs, serialised as a
 * single JSON config blob to keep the template clean.
 */
class Converter extends Template
{
    public function __construct(
        Context $context,
        private readonly Json $json,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * JSON config consumed by the JS widget (see data-mage-init in the template).
     */
    public function getJsonConfig(): string
    {
        return $this->json->serialize([
            'endpoints' => [
                'currencies' => $this->getUrl('currencyconverter/ajax/currencies'),
                'rate' => $this->getUrl('currencyconverter/ajax/rate'),
                'history' => $this->getUrl('currencyconverter/ajax/history'),
            ],
            'defaults' => [
                'from' => Config::DEFAULT_FROM,
                'to' => Config::DEFAULT_TO,
                'historyDays' => Config::HISTORY_DAYS,
            ],
        ]);
    }
}
