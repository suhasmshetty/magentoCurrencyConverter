<?php
/**
 * Copyright © Suhas. All rights reserved.
 */
declare(strict_types=1);

namespace Suhas\CurrencyConverter\Controller\Ajax;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json as JsonResult;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\RequestInterface;
use Psr\Log\LoggerInterface;
use Suhas\CurrencyConverter\Api\ExchangeRateServiceInterface;
use Suhas\CurrencyConverter\Exception\ApiException;

/**
 * Base class for the module's storefront AJAX (GET) endpoints.
 *
 * Centralises JSON envelope shape and error handling so each concrete action
 * only implements {@see self::handle()}.
 */
abstract class AbstractAjax implements HttpGetActionInterface
{
    public function __construct(
        protected readonly JsonFactory $jsonFactory,
        protected readonly RequestInterface $request,
        protected readonly ExchangeRateServiceInterface $service,
        protected readonly LoggerInterface $logger
    ) {
    }

    public function execute(): JsonResult
    {
        $result = $this->jsonFactory->create();

        try {
            $data = $this->handle();
            return $result->setData(['success' => true] + $data);
        } catch (ApiException $e) {
            // 400 for bad input, 502 for an upstream failure — the exception carries which.
            return $result
                ->setHttpResponseCode($e->getHttpStatusCode())
                ->setData(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            $this->logger->error('CurrencyConverter: unexpected AJAX error', [
                'action' => static::class,
                'exception' => $e->getMessage(),
            ]);
            return $result
                ->setHttpResponseCode(500)
                ->setData([
                    'success' => false,
                    'message' => (string) __('Something went wrong. Please try again.'),
                ]);
        }
    }

    /**
     * Return the action-specific payload merged into the success envelope.
     *
     * @return array<string,mixed>
     * @throws ApiException
     */
    abstract protected function handle(): array;
}
