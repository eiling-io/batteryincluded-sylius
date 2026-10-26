<?php

declare(strict_types=1);

namespace Tests\EilingIo\SyliusBatteryIncludedPlugin\Unit\Provider;

use EilingIo\SyliusBatteryIncludedPlugin\Provider\ProductDataProvider;
use Liip\ImagineBundle\Service\FilterService;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ProductDataProviderTest extends TestCase
{
    public function testOnlyTheOwnCollectionIsSupported(): void
    {
        $provider = $this->createProvider('ch');

        self::assertTrue($provider->supportsCollection('ch'));
        self::assertFalse($provider->supportsCollection('default'));
    }

    public function testIdsOfOtherDocumentTypesAreIgnored(): void
    {
        $channelRepository = $this->createMock(ChannelRepositoryInterface::class);
        $channelRepository->expects(self::never())->method(self::anything());

        $provider = $this->createProvider('default', $channelRepository);

        self::assertSame([], $provider->getByIds(['BLOG-1', 'CONTENT-PRODUCT-2']));
    }

    private function createProvider(string $collection, ?ChannelRepositoryInterface $channelRepository = null): ProductDataProvider
    {
        return new ProductDataProvider(
            $this->createStub(ProductRepositoryInterface::class),
            $channelRepository ?? $this->createStub(ChannelRepositoryInterface::class),
            $this->createStub(UrlGeneratorInterface::class),
            $this->createStub(FilterService::class),
            $collection,
            'FASHION_WEB',
        );
    }
}
