<?php

namespace EilingIo\SyliusBatteryIncludedPlugin\Controller\Shop;

use BatteryIncludedSdk\Shop\BrowseSearchStruct;
use BatteryIncludedSdk\Shop\BrowseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;

class SearchController extends AbstractController
{
    public function __construct(
        private readonly BrowseService $browseService,
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

        $result = $this->browseService->browse($searchStruct);
        $maxHits = $result->getFound();
        $maxPages = $result->getPages();

        $orderNumbers = array_values(
            array_filter(
                array_map(
                    static fn ($item) => $item['document']['_PRODUCT']['ordernumber'] ?? null,
                    $result->getHits()
                )
            )
        );

        $products = [];
        if (!empty($orderNumbers)) {
            $products = $this->productRepository->findBy(['code' => $orderNumbers]);
        }
        return $this->render('@EilingIoSyliusBatteryIncludedPlugin/shop/search/search.html.twig',
            compact('products', 'searchWord', 'currentPage', 'maxHits', 'maxPages')
        );
    }
}

