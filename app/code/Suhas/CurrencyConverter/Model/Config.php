<?php
/**
 * Copyright © Suhas. All rights reserved.
 */
declare(strict_types=1);

namespace Suhas\CurrencyConverter\Model;

/**
 * Central place for the module's fixed settings.
 *
 * Kept as simple constants (no admin configuration) to keep the module small and
 * easy to follow; change a value here to change the default behaviour everywhere.
 */
class Config
{
    /** Base URL of the Frankfurter v2 API (no trailing slash). */
    public const API_BASE_URL = 'https://api.frankfurter.dev/v2';

    /** Currency codes pre-selected when the page loads. */
    public const DEFAULT_FROM = 'USD';
    public const DEFAULT_TO = 'EUR';

    /** Days of history the chart shows. */
    public const HISTORY_DAYS = 90;

    /** How long (seconds) the currency list is cached. */
    public const CACHE_LIFETIME = 3600;
}
