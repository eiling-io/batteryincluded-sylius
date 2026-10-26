<?php

declare(strict_types=1);

namespace EilingIo\SyliusBatteryIncludedPlugin\Factory;

use Batteryincluded\BatteryincludedBundle\Collection\CollectionRegistry;
use BatteryIncludedSdk\CartRecommendations\CartRecommendationsService;
use BatteryIncludedSdk\Highlights\HighlightsService;
use BatteryIncludedSdk\Recommendations\RecommendationsService;
use BatteryIncludedSdk\Service\SyncService;
use BatteryIncludedSdk\Shop\BrowseService;
use BatteryIncludedSdk\Suggest\SuggestService;
use EilingIo\SyliusBatteryIncludedPlugin\Channel\ChannelCollectionResolver;

/**
 * Returns the sdk services of the batteryincluded bundle for the collection of a channel (default: current channel).
 */
final readonly class ServiceFactory
{
    public function __construct(
        private CollectionRegistry $collections,
        private ChannelCollectionResolver $channelCollectionResolver,
    ) {
    }

    public function getSyncService(?string $channelCode = null): SyncService
    {
        return $this->get(SyncService::class, $channelCode);
    }

    public function getBrowseService(?string $channelCode = null): BrowseService
    {
        return $this->get(BrowseService::class, $channelCode);
    }

    public function getSuggestService(?string $channelCode = null): SuggestService
    {
        return $this->get(SuggestService::class, $channelCode);
    }

    public function getHighlightsService(?string $channelCode = null): HighlightsService
    {
        return $this->get(HighlightsService::class, $channelCode);
    }

    public function getCartRecommendationsService(?string $channelCode = null): CartRecommendationsService
    {
        return $this->get(CartRecommendationsService::class, $channelCode);
    }

    public function getRecommendationsService(?string $channelCode = null): RecommendationsService
    {
        return $this->get(RecommendationsService::class, $channelCode);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function get(string $class, ?string $channelCode): object
    {
        return $this->collections->get($this->channelCollectionResolver->resolve($channelCode), $class);
    }
}
