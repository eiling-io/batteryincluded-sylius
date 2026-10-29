<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Tests\EilingIo\SyliusBatteryIncludedPlugin\Fixture\SharedChannelFixture;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

// FASHION_WEB and FASHION_CH share the collection default. The demo shop finds the channels by the host names of ddev
// (.ddev/config.yaml), the tests by localhost and ch.localhost.
return function (ContainerConfigurator $container) {
    // the defaults of the fixture in the tests, empty options are rejected
    $fixture = $container->env() === 'test' ? [] : ['options' => [
        'hostname' => 'ch.syliusbatteryincludedplugin.ddev.site',
        'source_hostname' => 'syliusbatteryincludedplugin.ddev.site',
    ]];

    $container->services()
        ->set(SharedChannelFixture::class)
            ->args([
                service('doctrine.orm.entity_manager'),
                service('sylius.repository.channel'),
                service('sylius.factory.channel'),
                service('sylius.factory.channel_pricing'),
                service('sylius.repository.product'),
            ])
            ->tag('sylius_fixtures.fixture');

    $container->extension('sylius_fixtures', [
        'suites' => [
            'default' => [
                'fixtures' => [
                    'batteryincluded_shared_channel' => ['priority' => -1001] + $fixture,
                ],
            ],
            // adds the channel to an existing database: bin/console sylius:fixtures:load batteryincluded_shared_channel
            'batteryincluded_shared_channel' => [
                'listeners' => ['logger' => null],
                'fixtures' => [
                    'batteryincluded_shared_channel' => $fixture,
                ],
            ],
        ],
    ]);

    // imported before config.yaml, FASHION_WEB stays the first channel and delivers the defaults
    $container->extension('eiling_io_sylius_battery_included', [
        'channels' => [
            'FASHION_WEB' => 'default',
            SharedChannelFixture::CHANNEL => 'default',
        ],
    ]);
};
