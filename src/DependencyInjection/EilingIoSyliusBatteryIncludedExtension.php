<?php

declare(strict_types=1);

namespace EilingIo\SyliusBatteryIncludedPlugin\DependencyInjection;

use Batteryincluded\BatteryincludedBundle\BatteryincludedBundle;
use Batteryincluded\BatteryincludedBundle\Provider\DataProviderInterface;
use EilingIo\SyliusBatteryIncludedPlugin\Channel\ChannelCollectionResolver;
use EilingIo\SyliusBatteryIncludedPlugin\Cms\CmsPageFinder;
use EilingIo\SyliusBatteryIncludedPlugin\Provider\CmsPageDataProvider;
use EilingIo\SyliusBatteryIncludedPlugin\Provider\ProductDataProvider;
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

    /** @psalm-suppress UnusedVariable */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));

        $loader->load('services.xml');

        $container->getDefinition(ChannelCollectionResolver::class)
            ->setArgument('$channelCollections', $config['channels']);

        $this->registerDataProviders('product_data_provider', ProductDataProvider::class, $config['channels'], $container);

        if ($config['cms_pages'] && $container->hasParameter('kernel.bundles') && isset($container->getParameter('kernel.bundles')[self::CMS_BUNDLE])) {
            $this->registerCmsPages($config['channels'], $container);
        }
    }

    public function prepend(ContainerBuilder $container): void
    {
        $this->prependDoctrineMigrations($container);
    }

    /**
     * One provider per channel, each delivers only to the collection of its channel.
     *
     * @param array<string, string> $channels
     *
     * @return list<string> service ids
     */
    private function registerDataProviders(string $name, string $class, array $channels, ContainerBuilder $container): array
    {
        if ($channels === []) {
            $channels = [null => BatteryincludedBundle::DEFAULT_COLLECTION];
        }

        $ids = [];
        foreach ($channels as $channelCode => $collection) {
            $ids[] = $id = 'eiling_io_sylius_battery_included.' . $name . '.' . $collection;
            $container->register($id, $class)
                ->setAutowired(true)
                ->setArgument('$collection', $collection)
                ->setArgument('$channelCode', $channelCode === '' ? null : (string) $channelCode)
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
