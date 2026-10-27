<?php

declare(strict_types=1);

namespace Tests\EilingIo\SyliusBatteryIncludedPlugin\Unit\DependencyInjection;

use Batteryincluded\BatteryincludedBundle\Provider\DataProviderInterface;
use EilingIo\SyliusBatteryIncludedPlugin\Channel\ChannelCollectionResolver;
use EilingIo\SyliusBatteryIncludedPlugin\Cms\CmsPageFinder;
use EilingIo\SyliusBatteryIncludedPlugin\DependencyInjection\EilingIoSyliusBatteryIncludedExtension;
use EilingIo\SyliusBatteryIncludedPlugin\Provider\ProductDataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
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
        self::assertNull($provider->getArgument('$channelCode'));
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
        self::assertSame('FASHION_CH', $provider->getArgument('$channelCode'));
        self::assertSame($channels, $container->getDefinition(ChannelCollectionResolver::class)->getArgument('$channelCollections'));
    }

    public function testACollectionCanOnlyBeAssignedToOneChannel(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['channels' => ['FASHION_WEB' => 'default', 'FASHION_CH' => 'default']]);
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
        self::assertSame('FASHION_CH', $provider->getArgument('$channelCode'));
        self::assertSame('sylius_cms.repository.page', (string) $provider->getArgument('$pageRepository'));
    }

    public function testCmsPagesCanBeDisabled(): void
    {
        $container = $this->load(['cms_pages' => false], ['SyliusCmsPlugin' => \stdClass::class]);

        self::assertFalse($container->hasDefinition(CmsPageFinder::class));
        self::assertNotContains('eiling_io_sylius_battery_included.cms_page_data_provider.default', $this->providerIds($container));
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
