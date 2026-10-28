<?php

declare(strict_types=1);

namespace EilingIo\SyliusBatteryIncludedPlugin\Twig\Components;

use EilingIo\SyliusBatteryIncludedPlugin\Search\PresetProductFinder;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(
    name: 'EilingIo:Error404',
    template: '@EilingIoSyliusBatteryIncludedPlugin/shop/error/404_products.html.twig'
)]
final class Error404Component
{
    public array $products = [];

    public function __construct(private readonly PresetProductFinder $presetProductFinder)
    {
    }

    public function mount(): void
    {
        $this->products = $this->presetProductFinder->find(PresetProductFinder::NOT_FOUND);
    }
}
