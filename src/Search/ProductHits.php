<?php

declare(strict_types=1);

namespace EilingIo\SyliusBatteryIncludedPlugin\Search;

use EilingIo\SyliusBatteryIncludedPlugin\Provider\ProductDataProvider;

/**
 * The shop loads the products of the hits by their ordernumber (Sylius product code).
 */
final class ProductHits
{
    /**
     * Ordernumbers of the products in the resolved hits of browse and recommendations or the resolved documents of
     * suggest, in their order. Products which are inactive in the requested availability, e.g. not in the channel of a
     * shared collection, are skipped.
     *
     * @param array<mixed> $hits hits with `document` or documents
     *
     * @return list<string>
     */
    public static function orderNumbers(array $hits): array
    {
        $orderNumbers = [];
        foreach ($hits as $hit) {
            $product = (is_array($hit) ? ($hit['document'] ?? $hit) : [])['_' . ProductDataProvider::TYPE] ?? null;
            if (!is_array($product) || ($product['active'] ?? true) === false) {
                continue;
            }

            $orderNumber = $product['ordernumber'] ?? null;
            if (is_string($orderNumber) && $orderNumber !== '') {
                $orderNumbers[] = $orderNumber;
            }
        }

        return $orderNumbers;
    }
}
