<?php

namespace EilingIo\SyliusBatteryIncludedPlugin\Twig\Components;

use BatteryIncludedSdk\Shop\BrowseSearchStruct;
use EilingIo\SyliusBatteryIncludedPlugin\Factory\ServiceFactory;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(
    name: 'EilingIo:PresetSlider',
    template: '@EilingIoSyliusBatteryIncludedPlugin/shop/index/preset_slider.html.twig'
)]
final class PresetSliderComponent
{
    private const PRESET_ID = "5ec20093-4d66-4ab7-80a3-632835e02c12";

    public array $presetSliderProducts = [];

    public function __construct(
        private readonly ServiceFactory $serviceFactory,
        private readonly ProductRepositoryInterface $productRepository,
    ) {
    }

    public function mount(): void
    {
        $struct = new BrowseSearchStruct();
        $struct->setPresetId(self::PRESET_ID);

        $result = $this->serviceFactory->getBrowseService()->browse($struct);

        $orderNumbers = array_values(
            array_filter(
                array_map(
                    static fn($item) => $item['document']['_PRODUCT']['ordernumber'] ?? null,
                    $result->getHits()
                )
            )
        );

        if (empty($orderNumbers)) {
            return;
        }

        $unsorted = $this->productRepository->findBy(['code' => $orderNumbers]);
        $position = array_flip($orderNumbers);
        usort($unsorted, static fn($a, $b) => $position[$a->getCode()] <=> $position[$b->getCode()]);
        $this->presetSliderProducts = $unsorted;
    }
}