<?php

declare(strict_types=1);

namespace EilingIo\SyliusBatteryIncludedPlugin\Controller\Shop;

use EilingIo\SyliusBatteryIncludedPlugin\Cms\CmsPageFinder;
use EilingIo\SyliusBatteryIncludedPlugin\Factory\ServiceFactory;
use EilingIo\SyliusBatteryIncludedPlugin\Search\ApiCallGuard;
use EilingIo\SyliusBatteryIncludedPlugin\Search\SearchStructFactory;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class TaxonController extends BatteryIncludedBaseController
{
    private const PER_PAGE = 12;

    public function __construct(
        ServiceFactory $serviceFactory,
        ProductRepositoryInterface $productRepository,
        SearchStructFactory $searchStructFactory,
        ApiCallGuard $apiCallGuard,
        private readonly TaxonRepositoryInterface $taxonRepository,
        ?CmsPageFinder $cmsPageFinder = null,
    ) {
        parent::__construct($serviceFactory, $productRepository, $searchStructFactory, $apiCallGuard, $cmsPageFinder);
    }

    public function index(Request $request): Response
    {
        $slug = $request->attributes->get('slug');
        $locale = $request->getLocale();

        $taxon = $this->taxonRepository->findOneBySlug($slug, $locale);
        if ($taxon === null) {
            throw $this->createNotFoundException(sprintf('Taxon with slug "%s" not found.', $slug));
        }

        $currentPage = $request->query->getInt('page', 1);
        $filter = $request->query->all('filter');
        $sort = $request->query->get('sort', '');
        $filterLink = urldecode(http_build_query(compact('filter')));

        $struct = $this->searchStructFactory->createBrowseSearchStruct();
        $struct->setPerPage(self::PER_PAGE);
        $struct->setPage($currentPage);
        // the path in the language of the request, like the translated categories of the export
        $struct->addFilter(SearchStructFactory::CATEGORY_FIELD, $this->buildCategoryPath($taxon));

        foreach ($filter as $key => $value) {
            if (is_array($value) && isset($value['min'], $value['max'])) {
                $struct->addRangeFilter($key, (string)$value['min'], (string)$value['max']);
            } elseif (is_string($value)) {
                $struct->addFilter($key, urldecode($value));
            }
        }

        if ($sort !== '') {
            $struct->setSort($sort);
        }

        ['products' => $products, 'facets' => $facets, 'maxHits' => $maxHits, 'maxPages' => $maxPages] = $this->getResultBySearchStruct($struct);

        return $this->render(
            '@EilingIoSyliusBatteryIncludedPlugin/shop/taxon/index.html.twig',
            compact(
                'taxon',
                'products',
                'facets',
                'maxHits',
                'maxPages',
                'currentPage',
                'filter',
                'filterLink',
                'sort'
            )
        );
    }

    private function buildCategoryPath(TaxonInterface $taxon): string
    {
        $parts = [];
        $current = $taxon;

        while ($current !== null) {
            array_unshift($parts, $current->getName());
            $current = $current->getParent()->isRoot() ? null : $current->getParent();
        }

        return implode(' > ', $parts);
    }
}
