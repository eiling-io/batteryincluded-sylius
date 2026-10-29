<?php

declare(strict_types=1);

namespace Tests\EilingIo\SyliusBatteryIncludedPlugin\Unit\Search;

use EilingIo\SyliusBatteryIncludedPlugin\Search\ProductHits;
use PHPUnit\Framework\TestCase;

final class ProductHitsTest extends TestCase
{
    public function testTheOrdernumbersOfHitsAndDocumentsAreReturnedInTheirOrder(): void
    {
        self::assertSame(['B', 'A'], ProductHits::orderNumbers([
            ['document' => ['_PRODUCT' => ['ordernumber' => 'B']]],
            ['_PRODUCT' => ['ordernumber' => 'A', 'active' => true]],
        ]));
    }

    public function testInactiveProductsAndOtherDocumentsAreSkipped(): void
    {
        self::assertSame(['A'], ProductHits::orderNumbers([
            ['document' => ['_PRODUCT' => ['ordernumber' => 'A']]],
            // not in the channel of a shared collection
            ['document' => ['_PRODUCT' => ['ordernumber' => 'B', 'active' => false]]],
            ['document' => ['_CONTENT' => ['code' => 'shipping']]],
            ['document' => ['_PRODUCT' => ['ordernumber' => '']]],
            'invalid',
        ]));
    }
}
