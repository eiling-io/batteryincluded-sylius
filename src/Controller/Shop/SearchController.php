<?php

namespace EilingIo\SyliusBatteryIncludedPlugin\Controller\Shop;

use BatteryIncludedSdk\Shop\BrowseSearchStruct;
use BatteryIncludedSdk\Suggest\SuggestSearchStruct;
use EilingIo\SyliusBatteryIncludedPlugin\Twig\Components\Error404Component;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SearchController extends BatteryIncludedBaseController
{
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

        extract($this->getResultBySearchStruct($searchStruct), EXTR_SKIP);
        $notFoundSlider = [];
        if (count($products) === 0) {
            $notFoundSearch = new BrowseSearchStruct();
            $notFoundSearch->setPresetId(Error404Component::PRESET_ID);
            $notFoundSearch = $this->getResultBySearchStruct($notFoundSearch);
            $notFoundSlider = $notFoundSearch['products'];
        };

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
                'notFoundSlider'
            )
        );
    }

    public function searchAjax(Request $request): Response
    {
        $searchWord = $request->query->get('search', '');
        $searchStruct = new SuggestSearchStruct();
        $searchStruct->setQuery($searchWord);

        $result = $this->serviceFactory->getSuggestService()->suggestWithFilter($searchStruct);

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
            $unsorted = $this->productRepository->findBy(['code' => $orderNumbers]);
            $position = array_flip($orderNumbers);
            usort($unsorted, static fn($a, $b) => $position[$a->getCode()] <=> $position[$b->getCode()]);
            $products = $unsorted;
        }

        $queryCompletions = $result->getQueryCompletions();

        $highlights = [];
        if (strlen($searchWord) === 0) {
            $highlights = $this->serviceFactory->getHighlightsService()->getHighlights()->getAll();
        }

        return $this->render(
            '@EilingIoSyliusBatteryIncludedPlugin/shop/search/search_ajax.html.twig',
            compact('products', 'searchWord', 'queryCompletions', 'highlights')
        );
    }
}

