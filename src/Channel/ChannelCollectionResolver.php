<?php

declare(strict_types=1);

namespace EilingIo\SyliusBatteryIncludedPlugin\Channel;

use Batteryincluded\BatteryincludedBundle\BatteryincludedBundle;
use Sylius\Component\Channel\Context\ChannelContextInterface;

/**
 * Resolves the collection of the batteryincluded bundle for a channel, see `eiling_io_sylius_battery_included.channels`.
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
}
