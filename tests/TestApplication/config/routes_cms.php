<?php

declare(strict_types=1);

use Sylius\CmsPlugin\SyliusCmsPlugin;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

// routes of the optional Sylius CMS plugin, see bundles.php
return function (RoutingConfigurator $routes) {
    if (class_exists(SyliusCmsPlugin::class)) {
        $routes->import('@SyliusCmsPlugin/config/routes.yaml');
    }
};
