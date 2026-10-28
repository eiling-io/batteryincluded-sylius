<?php

namespace EilingIo\SyliusBatteryIncludedPlugin\Twig\Components;

use EilingIo\SyliusBatteryIncludedPlugin\Search\PresetProductFinder;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(
    name: 'EilingIo:PresetSlider',
    template: '@EilingIoSyliusBatteryIncludedPlugin/shop/index/preset_slider.html.twig'
)]
final class PresetSliderComponent
{
    public array $presetSliderProducts = [];

    public function __construct(private readonly PresetProductFinder $presetProductFinder)
    {
    }

    public function mount(): void
    {
        $this->presetSliderProducts = $this->presetProductFinder->find(PresetProductFinder::HOMEPAGE);
    }
}
