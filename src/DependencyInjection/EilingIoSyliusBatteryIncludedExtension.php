<?php

declare(strict_types=1);

namespace EilingIo\SyliusBatteryIncludedPlugin\DependencyInjection;

use Batteryincluded\BatteryincludedBundle\BatteryincludedBundle;
use Batteryincluded\BatteryincludedBundle\Provider\DataProviderInterface;
use EilingIo\SyliusBatteryIncludedPlugin\Channel\ChannelCollectionResolver;
use EilingIo\SyliusBatteryIncludedPlugin\Provider\ProductDataProvider;
use Sylius\Bundle\CoreBundle\DependencyInjection\PrependDoctrineMigrationsTrait;
use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

final class EilingIoSyliusBatteryIncludedExtension extends AbstractResourceExtension implements
    PrependExtensionInterface
{
    use PrependDoctrineMigrationsTrait;

    /** @psalm-suppress UnusedVariable */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));

        $loader->load('services.xml');

        $container->getDefinition(ChannelCollectionResolver::class)
            ->setArgument('$channelCollections', $config['channels']);

        $this->registerProductDataProviders($config['channels'], $container);
    }

    public function prepend(ContainerBuilder $container): void
    {
        $this->prependDoctrineMigrations($container);
    }

    /**
     * One product provider per channel, each delivers only to the collection of its channel.
     *
     * @param array<string, string> $channels
     */
    private function registerProductDataProviders(array $channels, ContainerBuilder $container): void
    {
        if ($channels === []) {
            $channels = [null => BatteryincludedBundle::DEFAULT_COLLECTION];
        }

        foreach ($channels as $channelCode => $collection) {
            $container->register('eiling_io_sylius_battery_included.product_data_provider.' . $collection, ProductDataProvider::class)
                ->setAutowired(true)
                ->setArgument('$collection', $collection)
                ->setArgument('$channelCode', $channelCode === '' ? null : (string) $channelCode)
                ->addTag(DataProviderInterface::class);
        }
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
