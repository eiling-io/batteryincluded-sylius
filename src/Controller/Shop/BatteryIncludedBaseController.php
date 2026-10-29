<?php

namespace EilingIo\SyliusBatteryIncludedPlugin\Controller\Shop;

use BatteryIncludedSdk\Shop\BrowseResponse;
use BatteryIncludedSdk\Shop\BrowseSearchStruct;
use EilingIo\SyliusBatteryIncludedPlugin\Cms\CmsPageFinder;
use EilingIo\SyliusBatteryIncludedPlugin\Factory\ServiceFactory;
use EilingIo\SyliusBatteryIncludedPlugin\Provider\ProductDataProvider;
use EilingIo\SyliusBatteryIncludedPlugin\Search\ApiCallGuard;
use EilingIo\SyliusBatteryIncludedPlugin\Search\ProductHits;
use EilingIo\SyliusBatteryIncludedPlugin\Search\SearchStructFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;

abstract class BatteryIncludedBaseController extends AbstractController
{
    public function __construct(
        protected readonly ServiceFactory $serviceFactory,
        protected readonly ProductRepositoryInterface $productRepository,
        protected readonly SearchStructFactory $searchStructFactory,
        protected readonly ApiCallGuard $apiCallGuard,
        // only with the Sylius CMS plugin, the collection then contains content pages as well
        protected readonly ?CmsPageFinder $cmsPageFinder = null,
    ) {
    }

    protected function getResultBySearchStruct(BrowseSearchStruct $searchStruct): array
    {
        if ($this->cmsPageFinder !== null) {
            $searchStruct->addFilter('type', ProductDataProvider::TYPE);
        }

        // without the api the page shows no products, like a search without hits
        $result = $this->apiCallGuard->call(
            'browse',
            fn () => $this->serviceFactory->getBrowseService()->browse($searchStruct),
            new BrowseResponse('', $searchStruct, 503),
        );
        // the sdk skips the facets of other locales, e.g. `_PRODUCT_i18n.en-us.categories`
        $facets = $result->getFacets(SearchStructFactory::CATEGORY_FIELD);
        $maxHits = $result->getFound();
        $maxPages = $result->getPages();

        $orderNumbers = ProductHits::orderNumbers($result->getHits());

        $products = [];
        if (!empty($orderNumbers)) {
            $unsorted = $this->productRepository->findBy(['code' => $orderNumbers]);
            $position = array_flip($orderNumbers);
            usort($unsorted, static fn($a, $b) => $position[$a->getCode()] <=> $position[$b->getCode()]);
            $products = $unsorted;
        }

        return compact('facets', 'maxHits', 'maxPages', 'orderNumbers', 'products');
    }
}

