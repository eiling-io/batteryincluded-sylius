<?php

declare(strict_types=1);

namespace EilingIo\SyliusBatteryIncludedPlugin\Provider;

use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;

/**
 * Channels of a collection for the data providers.
 *
 * @internal
 */
final class ChannelFinder
{
    /**
     * @param list<string> $channelCodes in the order of the configuration, none for the first enabled channel
     *
     * @return non-empty-list<ChannelInterface>
     */
    public static function find(ChannelRepositoryInterface $channelRepository, array $channelCodes, string $collection): array
    {
        if ($channelCodes === []) {
            $channel = $channelRepository->findEnabled()[0] ?? null;
            if (!$channel instanceof ChannelInterface) {
                throw new \RuntimeException(sprintf('The channel "(first enabled)" of the collection "%s" does not exist.', $collection));
            }

            return [$channel];
        }

        $channels = [];
        foreach ($channelCodes as $channelCode) {
            $channel = $channelRepository->findOneByCode($channelCode);
            if (!$channel instanceof ChannelInterface) {
                throw new \RuntimeException(sprintf('The channel "%s" of the collection "%s" does not exist.', $channelCode, $collection));
            }
            $channels[] = $channel;
        }

        return $channels;
    }
}
