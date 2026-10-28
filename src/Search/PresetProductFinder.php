<?php

declare(strict_types=1);

namespace EilingIo\SyliusBatteryIncludedPlugin\Search;

use EilingIo\SyliusBatteryIncludedPlugin\Channel\ChannelCollectionResolver;
use EilingIo\SyliusBatteryIncludedPlugin\Cms\CmsPageFinder;
use EilingIo\SyliusBatteryIncludedPlugin\Factory\ServiceFactory;
use EilingIo\SyliusBatteryIncludedPlugin\Provider\ProductDataProvider;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;

/**
 * Products of the presets of the sliders, see `eiling_io_sylius_battery_included.presets`. The gateway ignores an
 * unknown preset id and delivers all documents, so a slider without configured preset shows nothing.
 */
final readonly class PresetProductFinder
{
    public const HOMEPAGE = 'homepage';

    public const NOT_FOUND = 'not_found';

    /**
     * @param array<string, array<string, string|null>> $presets collection name => slider => preset id
     */
    public function __construct(
        private ServiceFactory $serviceFactory,
        private ProductRepositoryInterface $productRepository,
        private SearchStructFactory $searchStructFactory,
        private ChannelCollectionResolver $channelCollectionResolver,
        private array $presets = [],
        // only with the Sylius CMS plugin, the collection then contains content pages as well
        private ?CmsPageFinder $cmsPageFinder = null,
    ) {
    }

    /**
     * @param string $slider self::HOMEPAGE or self::NOT_FOUND
     *
     * @return list<ProductInterface> in the order of the preset
     */
    public function find(string $slider): array
    {
        $presetId = $this->presets[$this->channelCollectionResolver->resolve()][$slider] ?? null;
        if ($presetId === null) {
            return [];
        }

        $struct = $this->searchStructFactory->createBrowseSearchStruct();
        $struct->setPresetId($presetId);
        if ($this->cmsPageFinder !== null) {
            $struct->addFilter('type', ProductDataProvider::TYPE);
        }

        $orderNumbers = array_values(
            array_filter(
                array_map(
                    static fn($item) => $item['document']['_PRODUCT']['ordernumber'] ?? null,
                    $this->serviceFactory->getBrowseService()->browse($struct)->getHits()
                )
            )
        );

        if (empty($orderNumbers)) {
            return [];
        }

        /** @var list<ProductInterface> $products */
        $products = $this->productRepository->findBy(['code' => $orderNumbers]);
        $position = array_flip($orderNumbers);
        usort($products, static fn($a, $b) => $position[$a->getCode()] <=> $position[$b->getCode()]);

        return $products;
    }
}
