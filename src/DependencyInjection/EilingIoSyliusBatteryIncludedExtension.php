<?php

declare(strict_types=1);

namespace EilingIo\SyliusBatteryIncludedPlugin\DependencyInjection;

use Batteryincluded\BatteryincludedBundle\BatteryincludedBundle;
use Batteryincluded\BatteryincludedBundle\Provider\DataProviderInterface;
use EilingIo\SyliusBatteryIncludedPlugin\Channel\ChannelCollectionResolver;
use EilingIo\SyliusBatteryIncludedPlugin\Cms\CmsPageFinder;
use EilingIo\SyliusBatteryIncludedPlugin\Document\ShopDocumentUrlResolver;
use EilingIo\SyliusBatteryIncludedPlugin\Provider\CmsPageDataProvider;
use EilingIo\SyliusBatteryIncludedPlugin\Provider\ProductDataProvider;
use EilingIo\SyliusBatteryIncludedPlugin\Search\PresetProductFinder;
use EilingIo\SyliusBatteryIncludedPlugin\Sync\DocumentChangeListener;
use Sylius\Bundle\CoreBundle\DependencyInjection\PrependDoctrineMigrationsTrait;
use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

final class EilingIoSyliusBatteryIncludedExtension extends AbstractResourceExtension implements
    PrependExtensionInterface
{
    use PrependDoctrineMigrationsTrait;

    /**
     * Name of the Sylius CMS plugin in `kernel.bundles`.
     */
    private const CMS_BUNDLE = 'SyliusCmsPlugin';

    /**
     * Seconds of curl for a search, suggestion or recommendation and to connect, the bundle waits 10 and 5 seconds.
     * Every shop page waits for them, without the api it is rendered without results, see ApiCallGuard.
     */
    public const CURL_TIMEOUT = 5;

    public const CURL_CONNECT_TIMEOUT = 2;

    /** @psalm-suppress UnusedVariable */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));

        $loader->load('services.xml');

        $container->getDefinition(ChannelCollectionResolver::class)
            ->setArgument('$channelCollections', $config['channels']);
        $container->getDefinition(PresetProductFinder::class)
            ->setArgument('$presets', $config['presets']);

        $this->registerDataProviders('product_data_provider', ProductDataProvider::class, $config['channels'], $container);

        $cmsPages = $config['cms_pages'] && $container->hasParameter('kernel.bundles') && isset($container->getParameter('kernel.bundles')[self::CMS_BUNDLE]);
        if ($cmsPages) {
            $this->registerCmsPages($config['channels'], $container);
        }

        if ($config['sync_changes']) {
            $container->register(DocumentChangeListener::class, DocumentChangeListener::class)
                ->setAutowired(true)
                ->setArgument('$collections', $config['channels'] === [] ? [BatteryincludedBundle::DEFAULT_COLLECTION] : array_values(array_unique($config['channels'])))
                ->setArgument('$cmsPages', $cmsPages)
                ->addTag('doctrine.event_listener', ['event' => 'onFlush'])
                ->addTag('doctrine.event_listener', ['event' => 'postFlush'])
                ->addTag('doctrine.event_listener', ['event' => 'onClear']);
        }
    }

    public function prepend(ContainerBuilder $container): void
    {
        $this->prependDoctrineMigrations($container);

        // defaults only, the configuration of the project wins; imports, updates and deletes have no limit
        if ($container->hasExtension('batteryincluded')) {
            $container->prependExtensionConfig('batteryincluded', [
                'curl' => ['timeout' => self::CURL_TIMEOUT, 'connect_timeout' => self::CURL_CONNECT_TIMEOUT],
            ]);
        }
    }

    /**
     * One provider per collection with the channels of the collection, without channels the first enabled channel
     * is exported into the default collection.
     *
     * @param array<string, string> $channels
     *
     * @return list<string> service ids
     */
    private function registerDataProviders(string $name, string $class, array $channels, ContainerBuilder $container): array
    {
        $channelCodesByCollection = $channels === [] ? [BatteryincludedBundle::DEFAULT_COLLECTION => []] : [];
        foreach ($channels as $channelCode => $collection) {
            $channelCodesByCollection[$collection][] = (string) $channelCode;
        }

        $ids = [];
        foreach ($channelCodesByCollection as $collection => $channelCodes) {
            $ids[] = $id = 'eiling_io_sylius_battery_included.' . $name . '.' . $collection;
            $container->register($id, $class)
                ->setAutowired(true)
                ->setArgument('$collection', (string) $collection)
                ->setArgument('$channelCodes', $channelCodes)
                ->addTag(DataProviderInterface::class);
        }

        return $ids;
    }

    /**
     * The pages of the Sylius CMS plugin are exported like the products, the search shows them with the CmsPageFinder.
     *
     * @param array<string, string> $channels
     */
    private function registerCmsPages(array $channels, ContainerBuilder $container): void
    {
        foreach ($this->registerDataProviders('cms_page_data_provider', CmsPageDataProvider::class, $channels, $container) as $id) {
            $container->getDefinition($id)->setArgument('$pageRepository', new Reference('sylius_cms.repository.page'));
        }

        $container->getDefinition(ShopDocumentUrlResolver::class)
            ->setArgument('$pageRepository', new Reference('sylius_cms.repository.page'));

        $container->register(CmsPageFinder::class, CmsPageFinder::class)
            ->setAutowired(true)
            ->setArgument('$pageRepository', new Reference('sylius_cms.repository.page'));
    }

    protected function getMigrationsNamespace(): string
    {
        return 'DoctrineMigrations';
    }

    protected function getMigrationsDirectory(): string
    {
        return '@EilingIoSyliusBatteryIncludedPlugin/src/Migrations';
    }

    protected function getNamespacesOfMigrationsExecutedBefore(): array
    {
        return [
            'Sylius\Bundle\CoreBundle\Migrations',
        ];
    }
}
