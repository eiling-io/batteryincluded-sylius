<?php

declare(strict_types=1);

namespace EilingIo\SyliusBatteryIncludedPlugin\Twig\Components;

use BatteryIncludedSdk\Shop\BrowseSearchStruct;
use EilingIo\SyliusBatteryIncludedPlugin\Factory\ServiceFactory;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(
    name: 'EilingIo:Error404',
    template: '@EilingIoSyliusBatteryIncludedPlugin/shop/error/404_products.html.twig'
)]
final class Error404Component
{
    private const PRESET_ID = "0b2fdce1-0809-450c-a522-cef26ce5a283";

    public array $products = [];

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
        $this->products = $unsorted;
    }
}
