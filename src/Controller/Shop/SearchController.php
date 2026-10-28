<?php

namespace EilingIo\SyliusBatteryIncludedPlugin\Controller\Shop;

use EilingIo\SyliusBatteryIncludedPlugin\Dto\ContentPageDto;
use EilingIo\SyliusBatteryIncludedPlugin\Twig\Components\Error404Component;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SearchController extends BatteryIncludedBaseController
{
    private const CONTENT_PAGES_PER_SEARCH = 4;

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
        $notFoundSlider = [];
        if (count($products) === 0) {
            $notFoundSearch = $this->searchStructFactory->createBrowseSearchStruct();
            $notFoundSearch->setPresetId(Error404Component::PRESET_ID);
            $notFoundSearch = $this->getResultBySearchStruct($notFoundSearch);
            $notFoundSlider = $notFoundSearch['products'];
        };

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

        $contentPages = $this->cmsPageFinder?->findByCodes($this->cmsPageFinder->getCodes($result->getDocuments())) ?? [];

        $queryCompletions = $result->getQueryCompletions();

        $highlights = [];
        if (strlen($searchWord) === 0) {
            // the query suggestions are configured per locale
            $highlights = $this->serviceFactory->getHighlightsService()->getHighlights($request->getLocale())->getAll();
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
        $result = $this->serviceFactory->getBrowseService()->browse($searchStruct);

        return $this->cmsPageFinder->findByCodes($this->cmsPageFinder->getCodes($result->getRawHits()));
    }
}

