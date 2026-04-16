<?php

declare(strict_types=1);

namespace EilingIo\SyliusBatteryIncludedPlugin\Factory;

use BatteryIncludedSdk\Client\ApiClient;
use BatteryIncludedSdk\Client\CurlHttpClient;
use Sylius\Component\Channel\Context\ChannelContextInterface;

final readonly class ApiClientFactory
{
    public function __construct(
        private ChannelContextInterface $channelContext,
        private string $defaultBaseUrl,
        private string $defaultCollection,
        private string $defaultApiKey,
    ) {
    }

    /**
     * Creates an ApiClient for the current HTTP request channel.
     * Used by Symfony DI as a service factory.
     */
    public function create(): ApiClient
    {
        return $this->createForChannel(
            $this->channelContext->getChannel()->getCode()
        );
    }

    /**
     * Creates an ApiClient for a specific channel code.
     * Looks up env vars in the pattern: {CHANNEL_CODE}_BATTERYINCLUDED_{VAR}
     * Falls back to the default env vars if no channel-specific ones exist.
     */
    public function createForChannel(string $channelCode): ApiClient
    {
        $prefix = strtoupper(str_replace(['-', ' '], '_', $channelCode));

        $baseUrl = $this->getEnvValue($prefix . '_BATTERYINCLUDED_BASE_URL') ?: $this->defaultBaseUrl;
        $collection = $this->getEnvValue($prefix . '_BATTERYINCLUDED_COLLECTION') ?: $this->defaultCollection;
        $apiKey = $this->getEnvValue($prefix . '_BATTERYINCLUDED_API_KEY') ?: $this->defaultApiKey;

        return new ApiClient(new CurlHttpClient(), $baseUrl, $collection, $apiKey);
    }

    private function getEnvValue(string $key): string|null
    {
        return $_SERVER[$key] ?? $_ENV[$key] ?? null;
    }
}