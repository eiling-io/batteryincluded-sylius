<?php

namespace EilingIo\SyliusBatteryIncludedPlugin\Controller\Shop;

use BatteryIncludedSdk\Highlights\HighlightsService;
use BatteryIncludedSdk\Shop\BrowseSearchStruct;
use BatteryIncludedSdk\Shop\BrowseService;
use BatteryIncludedSdk\Suggest\SuggestSearchStruct;
use BatteryIncludedSdk\Suggest\SuggestService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;

class SearchController extends AbstractController
{
    public function __construct(
        private readonly BrowseService $browseService,
        private readonly SuggestService $suggestService,
        private readonly HighlightsService $highlightsService,
        private readonly ProductRepositoryInterface $productRepository
    ) {
    }

    public function search(Request $request): Response
    {
        $searchWord = $request->query->get('search');
        $searchStruct = new BrowseSearchStruct();
        $searchStruct->setQuery($searchWord);
        $perPage = 9;
        $currentPage = $request->query->getInt('page', 1);
        $searchStruct->setPerPage($perPage);
        $searchStruct->setPage($currentPage);
        $filter = $_GET['filter'] ?? [];
        $filterLink = urldecode(http_build_query(compact('filter')));

        foreach ($filter as $key => $value) {
            if ($key === '_PRODUCT.price') {
                $searchStruct->addRangeFilter($key, $value['min'], $value['max']);
            } else {
                $searchStruct->addFilter($key, urldecode($value));
            }
        }

        $result = $this->browseService->browse($searchStruct);
        $facets = $result->getFacets();
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
            $products = $this->productRepository->findBy(['code' => $orderNumbers]);
        }

        return $this->render(
            '@EilingIoSyliusBatteryIncludedPlugin/shop/search/search.html.twig',
            compact('products', 'searchWord', 'currentPage', 'maxHits', 'maxPages', 'facets', 'filter', 'filterLink')
        );
    }

    public function searchAjax(Request $request): Response
    {
        $searchWord = $request->query->get('search', '');
        $searchStruct = new SuggestSearchStruct();
        $searchStruct->setQuery($searchWord);

        $result = $this->suggestService->suggestWithFilter($searchStruct);

        $orderNumbers = array_values(
            array_filter(
                array_map(
                    static fn($item) => $item['_PRODUCT']['ordernumber'] ?? null,
                    $result->getDocuments()
                )
            )
        );

        $products = [];
        if (!empty($orderNumbers)) {
            $products = $this->productRepository->findBy(['code' => $orderNumbers]);
        }

        $queryCompletions = $result->getQueryCompletions();

        $highlights = [];
        if (strlen($searchWord) === 0) {
            $highlights = $this->highlightsService->getHighlights()->getAll();
        }

        return $this->render(
            '@EilingIoSyliusBatteryIncludedPlugin/shop/search/search_ajax.html.twig',
            compact('products', 'searchWord', 'queryCompletions', 'highlights')
        );
    }
}

