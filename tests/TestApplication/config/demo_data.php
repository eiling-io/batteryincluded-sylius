<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Tests\EilingIo\SyliusBatteryIncludedPlugin\Fixture\GermanDemoDataFixture;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

// german texts for the demo shop and the tests, loaded at the end of the default fixture suite
return function (ContainerConfigurator $container) {
    $container->services()
        ->set(GermanDemoDataFixture::class)
            ->args([
                service('doctrine.orm.entity_manager'),
                service('sylius.repository.channel'),
                service('sylius.repository.locale'),
                service('sylius.repository.taxon'),
                service('sylius.repository.product'),
                service('sylius.repository.product_option'),
            ])
            ->tag('sylius_fixtures.fixture');

    $container->extension('sylius_fixtures', [
        'suites' => [
            'default' => [
                'fixtures' => [
                    'batteryincluded_german' => ['priority' => -1000],
                ],
            ],
        ],
    ]);
};
