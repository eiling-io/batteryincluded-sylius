<?php

declare(strict_types=1);

namespace EilingIo\SyliusBatteryIncludedPlugin\Provider;

use Batteryincluded\BatteryincludedBundle\BatteryincludedBundle;
use Batteryincluded\BatteryincludedBundle\Provider\CollectionAwareDataProviderInterface;
use Batteryincluded\BatteryincludedBundle\Provider\PartialDataProviderInterface;
use BatteryIncludedSdk\Dto\CategoryDto;
use BatteryIncludedSdk\Dto\ProductBaseDto;
use BatteryIncludedSdk\Dto\ProductPropertyDto;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Liip\ImagineBundle\Service\FilterService;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Delivers the enabled products of one channel to its collection, used by `bi:export`, `bi:sync` and the
 * DocumentSynchronizer of the batteryincluded bundle. One instance is registered per configured channel.
 */
final class ProductDataProvider implements CollectionAwareDataProviderInterface, PartialDataProviderInterface
{
    public const TYPE = 'PRODUCT';

    private ?ChannelInterface $channel = null;

    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ChannelRepositoryInterface $channelRepository,
        private readonly UrlGeneratorInterface $router,
        private readonly FilterService $imagineFilter,
        private readonly string $collection = BatteryincludedBundle::DEFAULT_COLLECTION,
        private readonly ?string $channelCode = null,
    ) {
    }

    public function supportsCollection(string $collection): bool
    {
        return $collection === $this->collection;
    }

    public function getBatches(int $batchSize): \Generator
    {
        $channel = $this->getChannel();
        $offset = 0;

        do {
            /** @var list<ProductInterface> $products */
            $products = $this->createQueryBuilder($channel)
                ->orderBy('o.id', 'ASC')
                ->setFirstResult($offset)
                ->setMaxResults($batchSize)
                ->getQuery()
                ->getResult();
            $offset += $batchSize;

            if ($products !== []) {
                yield array_map(fn (ProductInterface $product) => $this->createDto($product, $channel), $products);
            }
        } while (count($products) === $batchSize);
    }

    public function getByIds(array $ids): iterable
    {
        $productIds = [];
        foreach ($ids as $id) {
            if (str_starts_with($id, self::TYPE . '-')) {
                $productIds[] = substr($id, strlen(self::TYPE) + 1);
            }
        }

        if ($productIds === []) {
            return [];
        }

        $channel = $this->getChannel();
        /** @var list<ProductInterface> $products */
        $products = $this->createQueryBuilder($channel)
            ->andWhere('o.id IN (:ids)')
            ->setParameter('ids', $productIds)
            ->getQuery()
            ->getResult();

        return array_map(fn (ProductInterface $product) => $this->createDto($product, $channel), $products);
    }

    private function createQueryBuilder(ChannelInterface $channel): QueryBuilder
    {
        \assert($this->productRepository instanceof EntityRepository);

        return $this->productRepository->createQueryBuilder('o')
            ->innerJoin('o.channels', 'channel')
            ->andWhere('channel = :channel')
            ->andWhere('o.enabled = :enabled')
            ->setParameter('channel', $channel)
            ->setParameter('enabled', true);
    }

    private function getChannel(): ChannelInterface
    {
        if ($this->channel !== null) {
            return $this->channel;
        }

        $channel = $this->channelCode !== null
            ? $this->channelRepository->findOneByCode($this->channelCode)
            : ($this->channelRepository->findEnabled()[0] ?? null);

        if (!$channel instanceof ChannelInterface) {
            throw new \RuntimeException(sprintf('The channel "%s" of the collection "%s" does not exist.', $this->channelCode ?? '(first enabled)', $this->collection));
        }

        return $this->channel = $channel;
    }

    private function createDto(ProductInterface $product, ChannelInterface $channel): ProductBaseDto
    {
        $dto = new ProductBaseDto((string) $product->getId(), self::TYPE);
        $dto->setName($product->getName());
        $dto->setDescription($product->getDescription());
        $dto->setOrdernumber($product->getCode());
        $dto->setImageUrl($this->getImageUrl($product, $channel));
        $dto->setRating($product->getAverageRating());

        /** @var ProductVariantInterface|false $variant */
        $variant = $product->getVariants()->first();
        if ($variant !== false) {
            $dto->setInstock($variant->getOnHand() - $variant->getOnHold());
            $channelPricing = $variant->getChannelPricingForChannel($channel);
            if ($channelPricing !== null && $channelPricing->getPrice() !== null) {
                $dto->setPrice($channelPricing->getPrice() / 100);
            }
        }

        $manufacturerName = 'Unbekannt';
        $cap = $product->getAttributeByCodeAndLocale('cap_brand', 'de_DE');
        $tshirt = $product->getAttributeByCodeAndLocale('t_shirt_brand', 'de_DE');
        $jeans = $product->getAttributeByCodeAndLocale('jeans_brand', 'de_DE');
        $dress = $product->getAttributeByCodeAndLocale('dress_brand', 'de_DE');
        $manufacturerName = $cap ? $cap->getValue() : $manufacturerName;
        $manufacturerName = $tshirt ? $tshirt->getValue() : $manufacturerName;
        $manufacturerName = $jeans ? $jeans->getValue() : $manufacturerName;
        $manufacturerName = $dress ? $dress->getValue() : $manufacturerName;
        $dto->setManufacture($manufacturerName);

        $dtoProperties = new ProductPropertyDto();
        foreach ($product->getVariants() as $productVariant) {
            foreach ($productVariant->getOptionValues() as $optionValue) {
                $dtoProperties->addProperty($optionValue->getOptionCode(), $optionValue->getValue());
            }
        }
        $dto->setProperties($dtoProperties);

        foreach ($product->getTaxons() as $taxon) {
            if (!$taxon->hasChildren()) {
                $dto->addCategory($this->getCategoryPath($taxon));
            }
        }

        $dto->setShopUrl(
            $this->router->generate(
                'sylius_shop_product_show',
                ['slug' => $product->getSlug()],
                UrlGeneratorInterface::ABSOLUTE_URL
            )
        );

        return $dto;
    }

    private function getImageUrl(ProductInterface $product, ChannelInterface $channel): ?string
    {
        $image = $product->getImages()->first();
        if (!$image) {
            return null;
        }

        $urlRaw = $this->imagineFilter->getUrlOfFilteredImage($image->getPath(), 'sylius_shop_product_thumbnail');
        $imagePath = parse_url($urlRaw)['path'] ?? $urlRaw;
        $baseUrl = (string) $channel->getHostname();
        if (!str_starts_with($baseUrl, 'http')) {
            $baseUrl = 'https://' . ltrim($baseUrl, '/');
        }

        return rtrim($baseUrl, '/') . $imagePath;
    }

    private function getCategoryPath(TaxonInterface $taxon): CategoryDto
    {
        $dto = new CategoryDto();
        $parts = [];
        $current = $taxon;

        while ($current !== null) {
            $parts[] = $current->getName();
            $parent = $current->getParent();
            $current = $parent === null || $parent->isRoot() ? null : $parent;
        }
        foreach (array_reverse($parts) as $part) {
            $dto->addCategoryNode($part);
        }

        return $dto;
    }
}
