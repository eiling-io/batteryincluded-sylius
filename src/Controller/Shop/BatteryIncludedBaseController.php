<?php

namespace EilingIo\SyliusBatteryIncludedPlugin\Controller\Shop;

use BatteryIncludedSdk\Shop\BrowseSearchStruct;
use BatteryIncludedSdk\Suggest\SuggestSearchStruct;
use EilingIo\SyliusBatteryIncludedPlugin\Factory\ServiceFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;

abstract class BatteryIncludedBaseController extends AbstractController
{
    public function __construct(
        protected readonly ServiceFactory $serviceFactory,
        protected readonly ProductRepositoryInterface $productRepository
    ) {
    }

    protected function getResultBySearchStruct(BrowseSearchStruct $searchStruct): array
    {
        $result = $this->serviceFactory->getBrowseService()->browse($searchStruct);
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
            $unsorted = $this->productRepository->findBy(['code' => $orderNumbers]);
            $position = array_flip($orderNumbers);
            usort($unsorted, static fn($a, $b) => $position[$a->getCode()] <=> $position[$b->getCode()]);
            $products = $unsorted;
        }

        return compact('facets', 'maxHits', 'maxPages', 'orderNumbers', 'products');
    }
}

