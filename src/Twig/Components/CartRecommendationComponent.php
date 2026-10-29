<?php

namespace EilingIo\SyliusBatteryIncludedPlugin\Twig\Components;

use BatteryIncludedSdk\CartRecommendations\CartRecommendationsResponse;
use EilingIo\SyliusBatteryIncludedPlugin\Factory\ServiceFactory;
use EilingIo\SyliusBatteryIncludedPlugin\Channel\ChannelCollectionResolver;
use EilingIo\SyliusBatteryIncludedPlugin\Search\ApiCallGuard;
use EilingIo\SyliusBatteryIncludedPlugin\Search\ProductHits;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(
    name: 'EilingIo:CartRecommendation',
    template: '@EilingIoSyliusBatteryIncludedPlugin/shop/cart/recommendation.html.twig'
)]
final class CartRecommendationComponent
{
    public array $recommendations = [];

    public function __construct(
        private readonly ServiceFactory $serviceFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly LocaleContextInterface $localeContext,
        private readonly ApiCallGuard $apiCallGuard,
        private readonly ChannelCollectionResolver $channelCollectionResolver,
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

        $rawResult = $this->apiCallGuard->call(
            'cart recommendations',
            fn () => $this->serviceFactory->getCartRecommendationsService()->recommendByIdentifiers($productIds, locale: $this->localeContext->getLocaleCode(), availability: $this->channelCollectionResolver->resolveAvailability()),
            new CartRecommendationsResponse('', 503),
        )->getRecommendations();
        foreach ($rawResult as $key => $recommendations) {
            if (count($recommendations) > 0) {
                $orderNumbers = ProductHits::orderNumbers($recommendations);

                if (!empty($orderNumbers)) {
                    $orderNumbers = array_slice($orderNumbers, 0, 6);
                    $products = $this->productRepository->findBy(['code' => $orderNumbers]);
                    $this->recommendations[$key] = $products;
                }
            }
        };
    }
}
