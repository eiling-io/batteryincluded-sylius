<?php

namespace EilingIo\SyliusBatteryIncludedPlugin\Controller\Shop;

use BatteryIncludedSdk\Shop\BrowseSearchStruct;
use BatteryIncludedSdk\Shop\FacetDto;
use EilingIo\SyliusBatteryIncludedPlugin\Cms\CmsPageFinder;
use EilingIo\SyliusBatteryIncludedPlugin\Factory\ServiceFactory;
use EilingIo\SyliusBatteryIncludedPlugin\Provider\ProductDataProvider;
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
        // only with the Sylius CMS plugin, the collection then contains content pages as well
        protected readonly ?CmsPageFinder $cmsPageFinder = null,
    ) {
    }

    protected function getResultBySearchStruct(BrowseSearchStruct $searchStruct): array
    {
        if ($this->cmsPageFinder !== null) {
            $searchStruct->addFilter('type', ProductDataProvider::TYPE);
        }

        $result = $this->serviceFactory->getBrowseService()->browse($searchStruct);
        $facets = $this->withoutFacetsOfOtherLocales($result->getFacets(SearchStructFactory::CATEGORY_FIELD));
        $maxHits = $result->getFound();
        $maxPages = $result->getPages();

        $orderNumbers = array_values(
            array_filter(
                array_map(
                    static fn($item) => $item['document']['_PRODUCT']['ordernumber'] ?? null,
                    $result->getHits()
                )
            )
        );

        $products = [];
        if (!empty($orderNumbers)) {
            $unsorted = $this->productRepository->findBy(['code' => $orderNumbers]);
            $position = array_flip($orderNumbers);
            usort($unsorted, static fn($a, $b) => $position[$a->getCode()] <=> $position[$b->getCode()]);
            $products = $unsorted;
        }

        return compact('facets', 'maxHits', 'maxPages', 'orderNumbers', 'products');
    }

    /**
     * The gateway delivers the facets of the translated fields of every locale, e.g. `_PRODUCT_i18n.de-de.categories`
     * besides `_PRODUCT_i18n.categories` of the requested locale.
     *
     * @param list<FacetDto> $facets
     *
     * @return list<FacetDto>
     */
    private function withoutFacetsOfOtherLocales(array $facets): array
    {
        return array_values(array_filter(
            $facets,
            static fn (FacetDto $facet) => preg_match('/^_[A-Z]+_i18n\.[a-z]{2}(-[a-z0-9]{2,8})*\./', $facet->getFieldName()) !== 1,
        ));
    }
}

