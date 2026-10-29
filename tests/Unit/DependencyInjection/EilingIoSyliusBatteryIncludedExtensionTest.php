<?php

declare(strict_types=1);

namespace Tests\EilingIo\SyliusBatteryIncludedPlugin\Unit\DependencyInjection;

use Batteryincluded\BatteryincludedBundle\BatteryincludedBundle;
use Batteryincluded\BatteryincludedBundle\Provider\DataProviderInterface;
use EilingIo\SyliusBatteryIncludedPlugin\Channel\ChannelCollectionResolver;
use EilingIo\SyliusBatteryIncludedPlugin\Cms\CmsPageFinder;
use EilingIo\SyliusBatteryIncludedPlugin\DependencyInjection\EilingIoSyliusBatteryIncludedExtension;
use EilingIo\SyliusBatteryIncludedPlugin\Provider\ProductDataProvider;
use EilingIo\SyliusBatteryIncludedPlugin\Document\ShopDocumentUrlResolver;
use EilingIo\SyliusBatteryIncludedPlugin\Search\PresetProductFinder;
use EilingIo\SyliusBatteryIncludedPlugin\Sync\DocumentChangeListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class EilingIoSyliusBatteryIncludedExtensionTest extends TestCase
{
    public function testWithoutChannelsTheDefaultCollectionGetsOneProvider(): void
    {
        $container = $this->load([]);

        self::assertSame(['eiling_io_sylius_battery_included.product_data_provider.default'], $this->providerIds($container));
        $provider = $container->getDefinition('eiling_io_sylius_battery_included.product_data_provider.default');
        self::assertSame(ProductDataProvider::class, $provider->getClass());
        self::assertSame('default', $provider->getArgument('$collection'));
        self::assertSame([], $provider->getArgument('$channelCodes'));
        self::assertSame([], $container->getDefinition(ChannelCollectionResolver::class)->getArgument('$channelCollections'));
    }

    public function testEveryChannelGetsAProviderForItsCollection(): void
    {
        $channels = ['FASHION_WEB' => 'default', 'FASHION_CH' => 'ch'];
        $container = $this->load(['channels' => $channels]);

        self::assertSame([
            'eiling_io_sylius_battery_included.product_data_provider.default',
            'eiling_io_sylius_battery_included.product_data_provider.ch',
        ], $this->providerIds($container));
        $provider = $container->getDefinition('eiling_io_sylius_battery_included.product_data_provider.ch');
        self::assertSame('ch', $provider->getArgument('$collection'));
        self::assertSame(['FASHION_CH'], $provider->getArgument('$channelCodes'));
        self::assertSame($channels, $container->getDefinition(ChannelCollectionResolver::class)->getArgument('$channelCollections'));
    }

    public function testChannelsSharingACollectionGetOneProvider(): void
    {
        $container = $this->load(['channels' => ['FASHION_WEB' => 'default', 'FASHION_CH' => 'default', 'FASHION_AT' => 'at']], ['SyliusCmsPlugin' => \stdClass::class]);

        // in the order of the configuration, the first channel delivers the defaults
        self::assertSame(['FASHION_WEB', 'FASHION_CH'], $container->getDefinition('eiling_io_sylius_battery_included.product_data_provider.default')->getArgument('$channelCodes'));
        self::assertSame(['FASHION_WEB', 'FASHION_CH'], $container->getDefinition('eiling_io_sylius_battery_included.cms_page_data_provider.default')->getArgument('$channelCodes'));
        self::assertSame(['FASHION_AT'], $container->getDefinition('eiling_io_sylius_battery_included.product_data_provider.at')->getArgument('$channelCodes'));
        // every collection is synced once
        self::assertSame(['default', 'at'], $container->getDefinition(DocumentChangeListener::class)->getArgument('$collections'));
    }

    public function testWithoutCmsPluginNoPagesAreExported(): void
    {
        $container = $this->load([], ['SyliusShopBundle' => \stdClass::class]);

        self::assertFalse($container->hasDefinition(CmsPageFinder::class));
        self::assertSame(['eiling_io_sylius_battery_included.product_data_provider.default'], $this->providerIds($container));
    }

    public function testWithCmsPluginEveryChannelGetsAPageProvider(): void
    {
        $container = $this->load(['channels' => ['FASHION_WEB' => 'default', 'FASHION_CH' => 'ch']], ['SyliusCmsPlugin' => \stdClass::class]);

        self::assertTrue($container->hasDefinition(CmsPageFinder::class));
        self::assertContains('eiling_io_sylius_battery_included.cms_page_data_provider.default', $this->providerIds($container));
        $provider = $container->getDefinition('eiling_io_sylius_battery_included.cms_page_data_provider.ch');
        self::assertSame(['FASHION_CH'], $provider->getArgument('$channelCodes'));
        self::assertSame('sylius_cms.repository.page', (string) $provider->getArgument('$pageRepository'));
    }

    public function testCmsPagesCanBeDisabled(): void
    {
        $container = $this->load(['cms_pages' => false], ['SyliusCmsPlugin' => \stdClass::class]);

        self::assertFalse($container->hasDefinition(CmsPageFinder::class));
        self::assertNotContains('eiling_io_sylius_battery_included.cms_page_data_provider.default', $this->providerIds($container));
    }

    public function testThePresetsArePassedPerCollection(): void
    {
        self::assertSame([], $this->load([])->getDefinition(PresetProductFinder::class)->getArgument('$presets'));

        $container = $this->load(['presets' => ['default' => ['homepage' => 'home-id'], 'ch' => ['not_found' => '404-id']]]);

        self::assertEquals([
            'default' => ['homepage' => 'home-id', 'not_found' => null],
            'ch' => ['homepage' => null, 'not_found' => '404-id'],
        ], $container->getDefinition(PresetProductFinder::class)->getArgument('$presets'));
    }

    public function testChangesAreSyncedIntoEveryCollection(): void
    {
        $listener = $this->load([])->getDefinition(DocumentChangeListener::class);
        self::assertSame(['default'], $listener->getArgument('$collections'));
        self::assertFalse($listener->getArgument('$cmsPages'));
        self::assertSame(['onFlush', 'postFlush', 'onClear'], array_column($listener->getTag('doctrine.event_listener'), 'event'));

        $container = $this->load(['channels' => ['FASHION_WEB' => 'default', 'FASHION_CH' => 'ch']], ['SyliusCmsPlugin' => \stdClass::class]);
        $listener = $container->getDefinition(DocumentChangeListener::class);
        self::assertSame(['default', 'ch'], $listener->getArgument('$collections'));
        self::assertTrue($listener->getArgument('$cmsPages'));
        self::assertSame('sylius_cms.repository.page', (string) $container->getDefinition(ShopDocumentUrlResolver::class)->getArgument('$pageRepository'));
    }

    public function testSyncingChangesCanBeDisabled(): void
    {
        self::assertFalse($this->load(['sync_changes' => false])->hasDefinition(DocumentChangeListener::class));
    }

    public function testTheShopWaitsShorterForCurl(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension((new BatteryincludedBundle())->getContainerExtension());
        $container->loadFromExtension('batteryincluded', ['curl' => ['timeout' => 8]]);

        (new EilingIoSyliusBatteryIncludedExtension())->prepend($container);

        // prepended, so the configuration of the project wins
        self::assertSame([
            ['curl' => ['timeout' => 5, 'connect_timeout' => 2]],
            ['curl' => ['timeout' => 8]],
        ], $container->getExtensionConfig('batteryincluded'));
    }

    public function testWithoutBundleNoTimeoutsArePrepended(): void
    {
        $container = new ContainerBuilder();

        (new EilingIoSyliusBatteryIncludedExtension())->prepend($container);

        self::assertFalse($container->hasExtension('batteryincluded'));
    }

    /**
     * @param array<string, mixed>        $config
     * @param array<string, class-string> $bundles kernel.bundles
     */
    private function load(array $config, ?array $bundles = null): ContainerBuilder
    {
        $container = new ContainerBuilder();
        if ($bundles !== null) {
            $container->setParameter('kernel.bundles', $bundles);
        }
        (new EilingIoSyliusBatteryIncludedExtension())->load([$config], $container);

        return $container;
    }

    /**
     * @return list<string>
     */
    private function providerIds(ContainerBuilder $container): array
    {
        return array_keys($container->findTaggedServiceIds(DataProviderInterface::class));
    }
}
