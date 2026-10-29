<?php

declare(strict_types=1);

namespace EilingIo\SyliusBatteryIncludedPlugin\Search;

use BatteryIncludedSdk\Exception\BatteryIncludedException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Calls the api for a shop page: an unreachable api (timeout, connection error) must not break the page, it is
 * rendered without the results of BatteryIncluded. Error responses of the api are no exception, the responses of the
 * SDK are empty then. The bundle logs every failed request with url and status to the `batteryincluded` channel.
 */
final readonly class ApiCallGuard
{
    public function __construct(private LoggerInterface $logger = new NullLogger())
    {
    }

    /**
     * @template T
     *
     * @param string      $action   e.g. `search`, for the log
     * @param callable(): T $call
     * @param T           $fallback result of the page without the api
     *
     * @return T
     */
    public function call(string $action, callable $call, mixed $fallback): mixed
    {
        try {
            return $call();
        } catch (BatteryIncludedException $exception) {
            $this->logger->error('BatteryIncluded {action} failed, the page is rendered without it: {message}', [
                'action' => $action,
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            return $fallback;
        }
    }
}
