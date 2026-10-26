<?php

declare(strict_types=1);

namespace Tests\EilingIo\SyliusBatteryIncludedPlugin\Unit\Factory;

use Batteryincluded\BatteryincludedBundle\Collection\CollectionRegistry;
use BatteryIncludedSdk\Shop\BrowseService;
use BatteryIncludedSdk\Suggest\SuggestService;
use EilingIo\SyliusBatteryIncludedPlugin\Channel\ChannelCollectionResolver;
use EilingIo\SyliusBatteryIncludedPlugin\Factory\ServiceFactory;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class ServiceFactoryTest extends TestCase
{
    public function testServicesOfTheCollectionOfTheChannelAreReturned(): void
    {
        $defaultBrowseService = $this->createStub(BrowseService::class);
        $chBrowseService = $this->createStub(BrowseService::class);
        $chSuggestService = $this->createStub(SuggestService::class);

        $factory = $this->createFactory('FASHION_CH', [
            'default|' . BrowseService::class => $defaultBrowseService,
            'ch|' . BrowseService::class => $chBrowseService,
            'ch|' . SuggestService::class => $chSuggestService,
        ]);

        self::assertSame($chBrowseService, $factory->getBrowseService());
        self::assertSame($chSuggestService, $factory->getSuggestService());
        self::assertSame($defaultBrowseService, $factory->getBrowseService('FASHION_WEB'));
    }

    /**
     * @param array<string, object> $services
     */
    private function createFactory(string $currentChannelCode, array $services): ServiceFactory
    {
        $channel = $this->createStub(ChannelInterface::class);
        $channel->method('getCode')->willReturn($currentChannelCode);
        $channelContext = $this->createStub(ChannelContextInterface::class);
        $channelContext->method('getChannel')->willReturn($channel);

        $config = ['baseUrl' => 'https://api.test/', 'collection' => 'test', 'apiKey' => 'key'];
        $registry = new CollectionRegistry(
            new ServiceLocator(array_map(static fn (object $service) => static fn () => $service, $services)),
            ['default' => $config, 'ch' => $config],
            [],
        );

        return new ServiceFactory($registry, new ChannelCollectionResolver($channelContext, ['FASHION_CH' => 'ch']));
    }
}
