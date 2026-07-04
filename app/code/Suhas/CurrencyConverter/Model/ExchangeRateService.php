<?php
/**
 * Copyright © Suhas. All rights reserved.
 */
declare(strict_types=1);

namespace Suhas\CurrencyConverter\Model;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Psr\Log\LoggerInterface;
use Suhas\CurrencyConverter\Api\ExchangeRateServiceInterface;
use Suhas\CurrencyConverter\Exception\ApiException;

/**
 * Default {@see ExchangeRateServiceInterface} implementation.
 *
 * Talks to the Frankfurter v2 API, validates input, caches the currency list, and
 * normalises the API's payloads into the compact shapes the storefront needs.
 */
class ExchangeRateService implements ExchangeRateServiceInterface
{
    private const CACHE_TAG = 'suhas_currency_converter';
    private const CACHE_KEY_CURRENCIES = 'suhas_currency_converter_currencies';

    private const TIMEOUT_SECONDS = 15;
    private const CONNECT_TIMEOUT_SECONDS = 5;

    public function __construct(
        private readonly CurlFactory $curlFactory,
        private readonly CacheInterface $cache,
        private readonly Json $json,
        private readonly TimezoneInterface $timezone,
        private readonly LoggerInterface $logger
    ) {
    }

    public function getCurrencies(): array
    {
        $cached = $this->cache->load(self::CACHE_KEY_CURRENCIES);
        if (is_string($cached) && $cached !== '') {
            $decoded = $this->json->unserialize($cached);
            if (is_array($decoded) && $decoded !== []) {
                return $decoded;
            }
        }

        $raw = $this->get('/currencies');

        $currencies = [];
        foreach ($raw as $entry) {
            if (!is_array($entry) || empty($entry['iso_code'])) {
                continue;
            }
            $code = strtoupper((string) $entry['iso_code']);
            $currencies[$code] = (string) ($entry['name'] ?? $code);
        }

        if ($currencies === []) {
            throw new ApiException(__('No currencies were returned by the exchange-rate service.'));
        }

        asort($currencies, SORT_NATURAL | SORT_FLAG_CASE);

        $this->cache->save(
            $this->json->serialize($currencies),
            self::CACHE_KEY_CURRENCIES,
            [self::CACHE_TAG],
            Config::CACHE_LIFETIME
        );

        return $currencies;
    }

    public function getCurrentRate(string $from, string $to): array
    {
        $from = $this->normaliseCode($from);
        $to = $this->normaliseCode($to);

        // Same currency: the rate is trivially 1 — short-circuit to avoid a needless remote call.
        if ($from === $to) {
            return [
                'base' => $from,
                'quote' => $to,
                'rate' => 1.0,
                'date' => $this->timezone->date()->format('Y-m-d'),
            ];
        }

        $response = $this->get(sprintf('/rate/%s/%s', $from, $to));

        if (!isset($response['rate']) || !is_numeric($response['rate'])) {
            throw new ApiException(
                __('No exchange rate is available for %1 to %2.', $from, $to)
            );
        }

        return [
            'base' => (string) ($response['base'] ?? $from),
            'quote' => (string) ($response['quote'] ?? $to),
            'rate' => (float) $response['rate'],
            'date' => (string) ($response['date'] ?? $this->timezone->date()->format('Y-m-d')),
        ];
    }

    public function getTimeSeries(string $from, string $to, int $days): array
    {
        $from = $this->normaliseCode($from);
        $to = $this->normaliseCode($to);
        $days = $days > 0 ? min($days, 3650) : Config::HISTORY_DAYS;

        $today = $this->timezone->date();
        $start = (clone $today)->modify(sprintf('-%d days', $days));

        // Same currency: a flat line at 1.0 across the range — no remote call needed.
        if ($from === $to) {
            return [
                'base' => $from,
                'quote' => $to,
                'series' => [
                    ['date' => $start->format('Y-m-d'), 'rate' => 1.0],
                    ['date' => $today->format('Y-m-d'), 'rate' => 1.0],
                ],
            ];
        }

        $response = $this->get('/rates', [
            'from' => $start->format('Y-m-d'),
            'to' => $today->format('Y-m-d'),
            'base' => $from,
            'quotes' => $to,
        ]);

        $series = [];
        foreach ($response as $point) {
            if (!is_array($point) || !isset($point['date'], $point['rate']) || !is_numeric($point['rate'])) {
                continue;
            }
            $series[] = [
                'date' => (string) $point['date'],
                'rate' => (float) $point['rate'],
            ];
        }

        // Frankfurter returns the series in date order already, but guarantee it.
        usort($series, static fn (array $a, array $b): int => strcmp($a['date'], $b['date']));

        return [
            'base' => $from,
            'quote' => $to,
            'series' => $series,
        ];
    }

    /**
     * Validate/normalise an ISO 4217 currency code.
     *
     * @throws ApiException 400 when the code is malformed.
     */
    private function normaliseCode(string $code): string
    {
        $code = strtoupper(trim($code));
        if (preg_match('/^[A-Z]{3}$/', $code) !== 1) {
            throw ApiException::invalidInput(__('"%1" is not a valid ISO 4217 currency code.', $code));
        }

        return $code;
    }

    /**
     * GET a Frankfurter endpoint and return the decoded JSON body.
     *
     * @param array<string,string|int> $query
     * @return array<mixed>
     * @throws ApiException
     */
    private function get(string $path, array $query = []): array
    {
        $url = Config::API_BASE_URL . '/' . ltrim($path, '/');
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        try {
            $curl = $this->curlFactory->create();
            $curl->setOption(CURLOPT_TIMEOUT, self::TIMEOUT_SECONDS);
            $curl->setOption(CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT_SECONDS);
            $curl->setOption(CURLOPT_FOLLOWLOCATION, true);
            $curl->addHeader('Accept', 'application/json');
            $curl->get($url);

            $status = $curl->getStatus();
            $body = $curl->getBody();
        } catch (\Throwable $e) {
            $this->logger->error('CurrencyConverter: Frankfurter request failed', [
                'url' => $url,
                'exception' => $e->getMessage(),
            ]);
            throw new ApiException(
                __('Unable to reach the exchange-rate service. Please try again later.'),
                $e
            );
        }

        if ($status < 200 || $status >= 300) {
            $this->logger->warning('CurrencyConverter: Frankfurter returned non-2xx', [
                'url' => $url,
                'status' => $status,
                'body' => $body,
            ]);
            throw new ApiException(
                __('The exchange-rate service returned an error (HTTP %1).', $status)
            );
        }

        try {
            $decoded = $this->json->unserialize($body);
        } catch (\InvalidArgumentException $e) {
            throw new ApiException(
                __('The exchange-rate service returned an invalid response.'),
                $e
            );
        }

        if (!is_array($decoded)) {
            throw new ApiException(
                __('The exchange-rate service returned an unexpected response.')
            );
        }

        return $decoded;
    }
}
