<?php

declare(strict_types=1);

use Sylius\CmsPlugin\SyliusCmsPlugin;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

// configuration of the optional Sylius CMS plugin, see bundles.php
return function (ContainerConfigurator $container) {
    if (!class_exists(SyliusCmsPlugin::class)) {
        return;
    }

    $container->import('@SyliusCmsPlugin/config/config.yaml');
    $container->import('cms_fixtures.yaml');
    // the default expects the vendor directory below the project dir of the test application
    $container->parameters()->set('sylius_cms_api_platform_mapping_path', '%kernel.project_dir%/../cms-plugin/config/api_platform/');
};
