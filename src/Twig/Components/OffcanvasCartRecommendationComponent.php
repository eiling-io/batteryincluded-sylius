<?php

namespace EilingIo\SyliusBatteryIncludedPlugin\Twig\Components;

use EilingIo\SyliusBatteryIncludedPlugin\Factory\ServiceFactory;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(
    name: 'EilingIo:OffcanvasCartRecommendation',
    template: '@EilingIoSyliusBatteryIncludedPlugin/shop/cart/offcanvas_recommendation.html.twig'
)]
final class OffcanvasCartRecommendationComponent
{
    public array $recommendations = [];

    public function __construct(
        private readonly ServiceFactory $serviceFactory,
        private readonly ProductRepositoryInterface $productRepository,
    ) {
    }

    public function mount(OrderInterface|null $cartDetail = null): void
    {
        if (!$cartDetail instanceof OrderInterface) {
            return;
        }

        $productIds = [];
        foreach ($cartDetail->getItems() as $item) {
            /** @var OrderItemInterface $item */
            $product = $item->getProduct();
            if ($product instanceof ProductInterface && $product->getId() !== null) {
                $productIds[] = (string) $product->getId();
            }
        }

        if (count($productIds) === 0) {
            return;
        }

        $rawResult = $this->serviceFactory->getCartRecommendationsService()->recommendByIdentifiers($productIds)->getRecommendations();
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
                    $orderNumbers = array_slice($orderNumbers, 0, 3);
                    $products = $this->productRepository->findBy(['code' => $orderNumbers]);
                    $this->recommendations[$key] = $products;
                }
            }
        };
    }
}
