<?php

declare(strict_types=1);

namespace EilingIo\SyliusBatteryIncludedPlugin\Controller\Shop;

use BatteryIncludedSdk\Shop\BrowseSearchStruct;
use EilingIo\SyliusBatteryIncludedPlugin\Factory\ServiceFactory;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class TaxonController extends BatteryIncludedBaseController
{
    private const CATEGORY_FILTER_FIELD = '_PRODUCT.categories';

    private const PER_PAGE = 12;

    public function __construct(
        ServiceFactory $serviceFactory,
        ProductRepositoryInterface $productRepository,
        private readonly TaxonRepositoryInterface $taxonRepository,
    ) {
        parent::__construct($serviceFactory, $productRepository);
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

        $struct = new BrowseSearchStruct();
        $struct->setPerPage(self::PER_PAGE);
        $struct->setPage($currentPage);
        $struct->addFilter(self::CATEGORY_FILTER_FIELD, $this->buildCategoryPath($taxon));

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

        extract($this->getResultBySearchStruct($struct), EXTR_SKIP);

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
