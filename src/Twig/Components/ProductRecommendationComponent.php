<?php

namespace EilingIo\SyliusBatteryIncludedPlugin\Twig\Components;

use BatteryIncludedSdk\Recommendations\RecommendationsResponse;
use EilingIo\SyliusBatteryIncludedPlugin\Factory\ServiceFactory;
use EilingIo\SyliusBatteryIncludedPlugin\Channel\ChannelCollectionResolver;
use EilingIo\SyliusBatteryIncludedPlugin\Search\ApiCallGuard;
use EilingIo\SyliusBatteryIncludedPlugin\Search\ProductHits;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
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
        private readonly ProductRepositoryInterface $productRepository,
        private readonly LocaleContextInterface $localeContext,
        private readonly ApiCallGuard $apiCallGuard,
        private readonly ChannelCollectionResolver $channelCollectionResolver,
    ) {
    }

    public function mount(ProductInterface|null $productDetail = null): void
    {
        if ($productDetail instanceof ProductInterface) {
            $rawResult = $this->apiCallGuard->call(
                'recommendations',
                fn () => $this->serviceFactory->getRecommendationsService()->recommendByIdentifier((string) $productDetail->getId(), locale: $this->localeContext->getLocaleCode(), availability: $this->channelCollectionResolver->resolveAvailability()),
                new RecommendationsResponse('', 503),
            )->getRecommendations();
            foreach ($rawResult as $key => $recommendations) {
                if (count($recommendations) > 0) {
                    $orderNumbers = ProductHits::orderNumbers($recommendations);

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