<?php

declare(strict_types=1);

namespace EilingIo\SyliusBatteryIncludedPlugin\Factory;

use BatteryIncludedSdk\CartRecommendations\CartRecommendationsService;
use BatteryIncludedSdk\Highlights\HighlightsService;
use BatteryIncludedSdk\Recommendations\RecommendationsService;
use BatteryIncludedSdk\Service\SyncService;
use BatteryIncludedSdk\Shop\BrowseService;
use BatteryIncludedSdk\Suggest\SuggestService;
use Sylius\Component\Channel\Context\ChannelContextInterface;

final readonly class ServiceFactory
{
    public function __construct(
        private ApiClientFactory $apiClientFactory,
        private ChannelContextInterface $channelContext,
    ) {
    }

    public function getSyncService(?string $channelCode = null): SyncService
    {
        return new SyncService($this->apiClientFactory->createForChannel($channelCode ?? $this->currentChannelCode()));
    }

    public function getBrowseService(?string $channelCode = null): BrowseService
    {
        return new BrowseService(
            $this->apiClientFactory->createForChannel($channelCode ?? $this->currentChannelCode())
        );
    }

    public function getSuggestService(?string $channelCode = null): SuggestService
    {
        return new SuggestService(
            $this->apiClientFactory->createForChannel($channelCode ?? $this->currentChannelCode())
        );
    }

    public function getHighlightsService(?string $channelCode = null): HighlightsService
    {
        return new HighlightsService(
            $this->apiClientFactory->createForChannel($channelCode ?? $this->currentChannelCode())
        );
    }

    public function getCartRecommendationsService(?string $channelCode = null): CartRecommendationsService
    {
        return new CartRecommendationsService(
            $this->apiClientFactory->createForChannel($channelCode ?? $this->currentChannelCode())
        );
    }

    public function getRecommendationsService(?string $channelCode = null): RecommendationsService
    {
        return new RecommendationsService(
            $this->apiClientFactory->createForChannel($channelCode ?? $this->currentChannelCode())
        );
    }

    private function currentChannelCode(): string
    {
        return $this->channelContext->getChannel()->getCode();
    }
}