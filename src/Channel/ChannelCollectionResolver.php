<?php

declare(strict_types=1);

namespace EilingIo\SyliusBatteryIncludedPlugin\Channel;

use Batteryincluded\BatteryincludedBundle\BatteryincludedBundle;
use Sylius\Component\Channel\Context\ChannelContextInterface;

/**
 * Resolves the collection of the batteryincluded bundle for a channel, see `eiling_io_sylius_battery_included.channels`.
 * Channels sharing a collection request their availability, the products of the other channels are inactive then.
 */
final readonly class ChannelCollectionResolver
{
    /**
     * @param array<string, string> $channelCollections channel code => collection name
     */
    public function __construct(
        private ChannelContextInterface $channelContext,
        private array $channelCollections = [],
    ) {
    }

    /**
     * @param string|null $channelCode defaults to the channel of the current request
     */
    public function resolve(?string $channelCode = null): string
    {
        $channelCode ??= $this->channelContext->getChannel()->getCode();

        return $this->channelCollections[$channelCode] ?? BatteryincludedBundle::DEFAULT_COLLECTION;
    }

    /**
     * Availability scope of the channel (its code) if it shares its collection with other channels, otherwise null:
     * the collection contains only the values of the channel.
     *
     * @param string|null $channelCode defaults to the channel of the current request
     */
    public function resolveAvailability(?string $channelCode = null): ?string
    {
        $channelCode ??= (string) $this->channelContext->getChannel()->getCode();
        if (!isset($this->channelCollections[$channelCode])) {
            return null;
        }

        $channelsOfCollection = array_keys($this->channelCollections, $this->channelCollections[$channelCode], true);

        return count($channelsOfCollection) > 1 ? $channelCode : null;
    }
}
