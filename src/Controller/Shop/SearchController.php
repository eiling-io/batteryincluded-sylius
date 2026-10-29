<?php

namespace EilingIo\SyliusBatteryIncludedPlugin\Controller\Shop;

use BatteryIncludedSdk\Highlights\HighlightsResponse;
use BatteryIncludedSdk\Shop\BrowseResponse;
use BatteryIncludedSdk\Suggest\SuggestResponse;
use EilingIo\SyliusBatteryIncludedPlugin\Cms\CmsPageFinder;
use EilingIo\SyliusBatteryIncludedPlugin\Dto\ContentPageDto;
use EilingIo\SyliusBatteryIncludedPlugin\Factory\ServiceFactory;
use EilingIo\SyliusBatteryIncludedPlugin\Search\PresetProductFinder;
use EilingIo\SyliusBatteryIncludedPlugin\Search\ProductHits;
use EilingIo\SyliusBatteryIncludedPlugin\Search\ApiCallGuard;
use EilingIo\SyliusBatteryIncludedPlugin\Search\SearchStructFactory;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SearchController extends BatteryIncludedBaseController
{
    private const CONTENT_PAGES_PER_SEARCH = 4;

    public function __construct(
        ServiceFactory $serviceFactory,
        ProductRepositoryInterface $productRepository,
        SearchStructFactory $searchStructFactory,
        ApiCallGuard $apiCallGuard,
        private readonly PresetProductFinder $presetProductFinder,
        ?CmsPageFinder $cmsPageFinder = null,
    ) {
        parent::__construct($serviceFactory, $productRepository, $searchStructFactory, $apiCallGuard, $cmsPageFinder);
    }

    public function search(Request $request): Response
    {
        $searchWord = $request->query->get('search');
        $searchStruct = $this->searchStructFactory->createBrowseSearchStruct();
        $searchStruct->setQuery((string) $searchWord);
        $perPage = 9;
        $currentPage = $request->query->getInt('page', 1);
        $searchStruct->setPerPage($perPage);
        $searchStruct->setPage($currentPage);
        $filter = $_GET['filter'] ?? [];
        $sort = $request->query->get('sort', '');
        $filterLink = urldecode(http_build_query(compact('filter')));

        foreach ($filter as $key => $value) {
            if ($key === '_PRODUCT.price') {
                $searchStruct->addRangeFilter($key, $value['min'], $value['max']);
            } else {
                $searchStruct->addFilter($key, urldecode($value));
            }
        }

        if ($sort !== '') {
            $searchStruct->setSort($sort);
        }

        ['products' => $products, 'facets' => $facets, 'maxHits' => $maxHits, 'maxPages' => $maxPages] = $this->getResultBySearchStruct($searchStruct);
        $notFoundSlider = count($products) === 0 ? $this->presetProductFinder->find(PresetProductFinder::NOT_FOUND) : [];

        // the matching content pages above the products of the first page
        $contentPages = $currentPage === 1 ? $this->findContentPages((string) $searchWord) : [];

        return $this->render(
            '@EilingIoSyliusBatteryIncludedPlugin/shop/search/search.html.twig',
            compact(
                'products',
                'searchWord',
                'currentPage',
                'maxHits',
                'maxPages',
                'facets',
                'filter',
                'filterLink',
                'sort',
                'notFoundSlider',
                'contentPages'
            )
        );
    }

    public function searchAjax(Request $request): Response
    {
        $searchWord = $request->query->get('search', '');
        $searchStruct = $this->searchStructFactory->createSuggestSearchStruct();
        $searchStruct->setQuery($searchWord);

        $result = $this->apiCallGuard->call(
            'suggest',
            fn () => $this->serviceFactory->getSuggestService()->suggestWithFilter($searchStruct),
            new SuggestResponse('', 503),
        );

        // with the translations and the availability of the channel merged
        $documents = $result->getResolvedDocuments();
        $orderNumbers = ProductHits::orderNumbers($documents);

        $products = [];
        if (!empty($orderNumbers)) {
            $unsorted = $this->productRepository->findBy(['code' => $orderNumbers]);
            $position = array_flip($orderNumbers);
            usort($unsorted, static fn($a, $b) => $position[$a->getCode()] <=> $position[$b->getCode()]);
            $products = $unsorted;
        }

        $contentPages = $this->cmsPageFinder?->findByCodes($this->cmsPageFinder->getCodes($documents)) ?? [];

        $queryCompletions = $result->getQueryCompletions();

        $highlights = [];
        if (strlen($searchWord) === 0) {
            // the query suggestions are configured per locale
            $highlights = $this->apiCallGuard->call(
                'highlights',
                fn () => $this->serviceFactory->getHighlightsService()->getHighlights($request->getLocale()),
                new HighlightsResponse('', 503),
            )->getAll();
        }

        return $this->render(
            '@EilingIoSyliusBatteryIncludedPlugin/shop/search/search_ajax.html.twig',
            compact('products', 'searchWord', 'queryCompletions', 'highlights', 'contentPages')
        );
    }

    /**
     * @return list<\Sylius\CmsPlugin\Entity\PageInterface>
     */
    private function findContentPages(string $searchWord): array
    {
        if ($this->cmsPageFinder === null || $searchWord === '') {
            return [];
        }

        $searchStruct = $this->searchStructFactory->createBrowseSearchStruct();
        $searchStruct->setQuery($searchWord);
        $searchStruct->addFilter('type', ContentPageDto::TYPE);
        $searchStruct->setPerPage(self::CONTENT_PAGES_PER_SEARCH);
        $result = $this->apiCallGuard->call(
            'content page search',
            fn () => $this->serviceFactory->getBrowseService()->browse($searchStruct),
            new BrowseResponse('', $searchStruct, 503),
        );

        return $this->cmsPageFinder->findByCodes($this->cmsPageFinder->getCodes($result->getRawHits()));
    }
}

