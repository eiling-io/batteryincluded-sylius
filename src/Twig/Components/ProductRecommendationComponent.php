<?php

namespace EilingIo\SyliusBatteryIncludedPlugin\Twig\Components;

use BatteryIncludedSdk\Recommendations\RecommendationsService;
use EilingIo\SyliusBatteryIncludedPlugin\Factory\ServiceFactory;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(
    name: 'EilingIo:ProductRecommendation',
    template: '@EilingIoSyliusBatteryIncludedPlugin/shop/product/recommendation.html.twig'
)]
final class ProductRecommendationComponent
{
    public ?ProductInterface $product = null;

    public array $recommendations = [];

    public function __construct(
        private readonly ServiceFactory $serviceFactory,
        private readonly ProductRepositoryInterface $productRepository
    ) {
    }

    public function mount(ProductInterface|null $productDetail = null): void
    {
        if ($productDetail instanceof ProductInterface) {
            $rawResult = $this->serviceFactory->getRecommendationsService()->recommendByIdentifier($productDetail->getId())->getRecommendations();
            foreach ($rawResult as $key => $recommendations) {
                if (count($recommendations) > 0) {
                    $orderNumbers = array_values(
                        array_filter(
                            array_map(
                                static fn($item) => $item['document']['_PRODUCT']['ordernumber'] ?? null,
                                $recommendations
                            )
                        )
                    );

                    if (!empty($orderNumbers)) {
                        $orderNumbers = array_slice($orderNumbers, 0, 4);
                        $products = $this->productRepository->findBy(['code' => $orderNumbers]);
                        $this->recommendations[$key] = $products;
                    }
                }
            };
        }
    }
}