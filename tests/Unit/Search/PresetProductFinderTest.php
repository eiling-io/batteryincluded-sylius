<?php

declare(strict_types=1);

namespace Tests\EilingIo\SyliusBatteryIncludedPlugin\Unit\Search;

use Batteryincluded\BatteryincludedBundle\Collection\CollectionRegistry;
use BatteryIncludedSdk\Shop\BrowseResponse;
use BatteryIncludedSdk\Shop\BrowseSearchStruct;
use BatteryIncludedSdk\Shop\BrowseService;
use Doctrine\ORM\EntityRepository;
use EilingIo\SyliusBatteryIncludedPlugin\Channel\ChannelCollectionResolver;
use EilingIo\SyliusBatteryIncludedPlugin\Cms\CmsPageFinder;
use EilingIo\SyliusBatteryIncludedPlugin\Factory\ServiceFactory;
use EilingIo\SyliusBatteryIncludedPlugin\Search\PresetProductFinder;
use EilingIo\SyliusBatteryIncludedPlugin\Search\SearchStructFactory;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class PresetProductFinderTest extends TestCase
{
    private ?BrowseSearchStruct $searchStruct = null;

    public function testWithoutPresetNothingIsRequested(): void
    {
        $browseService = $this->createMock(BrowseService::class);
        $browseService->expects(self::never())->method('browse');

        $finder = $this->createFinder($browseService, ['default' => ['homepage' => 'home-id', 'not_found' => null]]);

        self::assertSame([], $finder->find(PresetProductFinder::NOT_FOUND));
        self::assertSame([], $this->createFinder($browseService, [])->find(PresetProductFinder::HOMEPAGE));
    }

    public function testTheProductsOfThePresetOfTheCollectionAreReturnedInItsOrder(): void
    {
        $finder = $this->createFinder($this->browseService(['B', 'A']), ['ch' => ['homepage' => 'home-id', 'not_found' => null]], 'FASHION_CH');

        $products = $finder->find(PresetProductFinder::HOMEPAGE);

        self::assertSame(['B', 'A'], array_map(static fn (ProductInterface $product) => $product->getCode(), $products));
        self::assertNotNull($this->searchStruct);
        self::assertSame('home-id', $this->searchStruct->getPresetId());
        self::assertSame('de_DE', $this->searchStruct->getLocale());
        self::assertArrayNotHasKey('type', $this->searchStruct->getFilters());
    }

    public function testWithCmsPagesOnlyProductsAreRequested(): void
    {
        $cmsPageFinder = new CmsPageFinder($this->createStub(EntityRepository::class), $this->createStub(ChannelContextInterface::class));
        $finder = $this->createFinder($this->browseService(['A']), ['default' => ['homepage' => 'home-id', 'not_found' => null]], cmsPageFinder: $cmsPageFinder);

        $finder->find(PresetProductFinder::HOMEPAGE);

        self::assertNotNull($this->searchStruct);
        self::assertSame(['PRODUCT'], $this->searchStruct->getFilters()['type']);
    }

    /**
     * Browse service delivering these ordernumbers, the requested struct is kept in $searchStruct.
     *
     * @param list<string> $orderNumbers
     */
    private function browseService(array $orderNumbers): BrowseService
    {
        $browseService = $this->createMock(BrowseService::class);
        $browseService->expects(self::once())->method('browse')->willReturnCallback(function (BrowseSearchStruct $searchStruct) use ($orderNumbers) {
            $this->searchStruct = $searchStruct;
            $hits = array_map(static fn (string $code) => ['document' => ['_PRODUCT' => ['ordernumber' => $code]]], $orderNumbers);

            return new BrowseResponse((string) json_encode(['found' => count($hits), 'hits' => $hits]), $searchStruct);
        });

        return $browseService;
    }

    /**
     * @param array<string, array<string, string|null>> $presets
     */
    private function createFinder(BrowseService $browseService, array $presets, string $channelCode = 'FASHION_WEB', ?CmsPageFinder $cmsPageFinder = null): PresetProductFinder
    {
        $channel = $this->createStub(ChannelInterface::class);
        $channel->method('getCode')->willReturn($channelCode);
        $channelContext = $this->createStub(ChannelContextInterface::class);
        $channelContext->method('getChannel')->willReturn($channel);
        $channelCollectionResolver = new ChannelCollectionResolver($channelContext, ['FASHION_CH' => 'ch']);

        $config = ['baseUrl' => 'https://api.test/', 'collection' => 'test', 'apiKey' => 'key'];
        $registry = new CollectionRegistry(
            new ServiceLocator([
                'default|' . BrowseService::class => static fn () => $browseService,
                'ch|' . BrowseService::class => static fn () => $browseService,
            ]),
            ['default' => $config, 'ch' => $config],
            [],
        );

        $localeContext = $this->createStub(LocaleContextInterface::class);
        $localeContext->method('getLocaleCode')->willReturn('de_DE');

        // the repository delivers the products unsorted
        $productRepository = $this->createStub(ProductRepositoryInterface::class);
        $productRepository->method('findBy')->willReturnCallback(function (array $criteria) {
            $codes = $criteria['code'];
            sort($codes);

            return array_map(function (string $code) {
                $product = $this->createStub(ProductInterface::class);
                $product->method('getCode')->willReturn($code);

                return $product;
            }, $codes);
        });

        return new PresetProductFinder(
            new ServiceFactory($registry, $channelCollectionResolver),
            $productRepository,
            new SearchStructFactory($localeContext),
            $channelCollectionResolver,
            $presets,
            $cmsPageFinder,
        );
    }
}
