<?php

$bundles = [
    Batteryincluded\BatteryincludedBundle\BatteryincludedBundle::class => ['all' => true],
    EilingIo\SyliusBatteryIncludedPlugin\EilingIoSyliusBatteryIncludedPlugin::class => ['all' => true],
];

// optional: the content pages of the Sylius CMS plugin are exported and searchable if it is installed
if (class_exists(Sylius\CmsPlugin\SyliusCmsPlugin::class)) {
    $bundles[Sylius\CmsPlugin\SyliusCmsPlugin::class] = ['all' => true];
}

return $bundles;
