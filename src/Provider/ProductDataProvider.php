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
use Sylius\Component\Product\Model\ProductOptionValueTranslationInterface;
use Sylius\Component\Product\Model\ProductTranslationInterface;
use Sylius\Component\Taxonomy\Model\TaxonTranslationInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Delivers the enabled products of the channels of a collection, used by `bi:export`, `bi:sync` and the
 * DocumentSynchronizer of the batteryincluded bundle. One instance is registered per configured collection.
 *
 * The first channel of the product (in the order of the configuration) delivers the defaults of `_PRODUCT`. Channels
 * sharing a collection export their status and price into `_PRODUCT_availability.<channel code>`, a product which is
 * not in a channel is inactive there.
 */
final class ProductDataProvider implements CollectionAwareDataProviderInterface, PartialDataProviderInterface
{
    public const TYPE = 'PRODUCT';

    /**
     * @var list<ChannelInterface>|null
     */
    private ?array $channels = null;

    /**
     * @param list<string> $channelCodes channels of the collection, none for the first enabled channel
     */
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ChannelRepositoryInterface $channelRepository,
        private readonly UrlGeneratorInterface $router,
        private readonly FilterService $imagineFilter,
        private readonly string $collection = BatteryincludedBundle::DEFAULT_COLLECTION,
        private readonly array $channelCodes = [],
    ) {
    }

    public function supportsCollection(string $collection): bool
    {
        return $collection === $this->collection;
    }

    public function getBatches(int $batchSize): \Generator
    {
        $channels = $this->getChannels();
        $offset = 0;

        do {
            /** @var list<ProductInterface> $products */
            $products = $this->createQueryBuilder($channels)
                ->orderBy('o.id', 'ASC')
                ->setFirstResult($offset)
                ->setMaxResults($batchSize)
                ->getQuery()
                ->getResult();
            $offset += $batchSize;

            if ($products !== []) {
                yield array_map(fn (ProductInterface $product) => $this->createDto($product, $channels), $products);
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

        $channels = $this->getChannels();
        /** @var list<ProductInterface> $products */
        $products = $this->createQueryBuilder($channels)
            ->andWhere('o.id IN (:ids)')
            ->setParameter('ids', $productIds)
            ->getQuery()
            ->getResult();

        return array_map(fn (ProductInterface $product) => $this->createDto($product, $channels), $products);
    }

    /**
     * @param list<ChannelInterface> $channels
     */
    private function createQueryBuilder(array $channels): QueryBuilder
    {
        \assert($this->productRepository instanceof EntityRepository);

        // a product of several channels once
        return $this->productRepository->createQueryBuilder('o')
            ->distinct()
            ->innerJoin('o.channels', 'channel')
            ->andWhere('channel IN (:channels)')
            ->andWhere('o.enabled = :enabled')
            ->setParameter('channels', $channels)
            ->setParameter('enabled', true);
    }

    /**
     * @return list<ChannelInterface>
     */
    private function getChannels(): array
    {
        return $this->channels ??= ChannelFinder::find($this->channelRepository, $this->channelCodes, $this->collection);
    }

    /**
     * @param list<ChannelInterface> $channels
     */
    private function createDto(ProductInterface $product, array $channels): ProductBaseDto
    {
        // the defaults of the first channel of the product
        $channel = $channels[0];
        foreach ($channels as $candidate) {
            if ($product->hasChannel($candidate)) {
                $channel = $candidate;
                break;
            }
        }

        $dto = new ProductBaseDto((string) $product->getId(), self::TYPE);
        $dto->setOrdernumber($product->getCode());
        $dto->setImageUrl($this->getImageUrl($product, $channel));
        $dto->setRating($product->getAverageRating());

        /** @var ProductVariantInterface|false $variant */
        $variant = $product->getVariants()->first();
        if ($variant !== false) {
            // the stock is the same in every channel
            $dto->setInstock($variant->getOnHand() - $variant->getOnHold());
            $dto->setPrice($this->getPrice($variant, $channel));
        }

        if (count($channels) > 1) {
            foreach ($channels as $scope) {
                $dto->setAvailability(
                    (string) $scope->getCode(),
                    active: $product->hasChannel($scope),
                    price: $variant !== false ? $this->getPrice($variant, $scope) : null,
                );
            }
        }

        // the default locale of the channel fills _PRODUCT, every locale of the channels _PRODUCT_i18n
        $defaultLocale = (string) $channel->getDefaultLocale()?->getCode();
        $default = $this->getLocalizedFields($product, $defaultLocale);
        $dto->setName($default['name']);
        $dto->setDescription($default['description']);
        $dto->setManufacture($default['manufacture']);
        $dto->setShopUrl($default['shopUrl']);
        $dto->setProperties($default['properties']);
        foreach ($default['categories'] as $category) {
            $dto->addCategory($category);
        }

        foreach ($this->getLocales($channels) as $locale) {
            $fields = $locale === $defaultLocale ? $default : $this->getLocalizedFields($product, $locale);
            foreach (['name', 'description', 'manufacture', 'shopUrl'] as $field) {
                if ($fields[$field] !== null) {
                    $dto->addTranslation($locale, $field, $fields[$field]);
                }
            }
            $dto->setPropertiesTranslation($locale, $fields['properties']);
            foreach ($fields['categories'] as $category) {
                $dto->addCategoryTranslation($locale, $category);
            }
        }

        return $dto;
    }

    private function getPrice(ProductVariantInterface $variant, ChannelInterface $channel): ?float
    {
        $channelPricing = $variant->getChannelPricingForChannel($channel);

        return $channelPricing !== null && $channelPricing->getPrice() !== null ? $channelPricing->getPrice() / 100 : null;
    }

    /**
     * Languages of all channels of the collection.
     *
     * @param list<ChannelInterface> $channels
     *
     * @return list<string>
     */
    private function getLocales(array $channels): array
    {
        $locales = [];
        foreach ($channels as $channel) {
            foreach ($channel->getLocales() as $locale) {
                $locales[(string) $locale->getCode()] = true;
            }
        }

        return array_keys($locales);
    }

    /**
     * The language dependent fields of a locale, missing translations fall back to the default of Sylius.
     *
     * @return array{name: ?string, description: ?string, manufacture: ?string, shopUrl: ?string, properties: ProductPropertyDto, categories: list<CategoryDto>}
     */
    private function getLocalizedFields(ProductInterface $product, string $locale): array
    {
        $translation = $product->getTranslations()->get($locale);
        $translated = $translation instanceof ProductTranslationInterface;
        $slug = $translated ? $translation->getSlug() : $product->getSlug();

        // every value once, the variants repeat them
        $values = [];
        foreach ($product->getVariants() as $productVariant) {
            foreach ($productVariant->getOptionValues() as $optionValue) {
                $valueTranslation = $optionValue->getTranslations()->get($locale);
                $value = $valueTranslation instanceof ProductOptionValueTranslationInterface ? $valueTranslation->getValue() : $optionValue->getValue();
                $values[(string) $optionValue->getOptionCode()][(string) $value] = true;
            }
        }
        $properties = new ProductPropertyDto();
        foreach ($values as $option => $optionValues) {
            foreach (array_keys($optionValues) as $value) {
                $properties->addProperty($option, (string) $value);
            }
        }

        $categories = [];
        foreach ($product->getTaxons() as $taxon) {
            if (!$taxon->hasChildren()) {
                $categories[] = $this->getCategoryPath($taxon, $locale);
            }
        }

        return [
            'name' => $translated ? $translation->getName() : $product->getName(),
            'description' => $translated ? $translation->getDescription() : $product->getDescription(),
            'manufacture' => $this->getManufacturer($product, $locale),
            'shopUrl' => $slug !== null
                ? $this->router->generate('sylius_shop_product_show', ['slug' => $slug, '_locale' => $locale], UrlGeneratorInterface::ABSOLUTE_URL)
                : null,
            'properties' => $properties,
            'categories' => $categories,
        ];
    }

    private function getManufacturer(ProductInterface $product, string $locale): string
    {
        $manufacturerName = 'Unbekannt';
        foreach (['cap_brand', 't_shirt_brand', 'jeans_brand', 'dress_brand'] as $code) {
            $brand = $product->getAttributeByCodeAndLocale($code, $locale);
            $manufacturerName = $brand ? (string) $brand->getValue() : $manufacturerName;
        }

        return $manufacturerName;
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

    private function getCategoryPath(TaxonInterface $taxon, string $locale): CategoryDto
    {
        $dto = new CategoryDto();
        $parts = [];
        $current = $taxon;

        while ($current !== null) {
            $translation = $current->getTranslations()->get($locale);
            $parts[] = (string) ($translation instanceof TaxonTranslationInterface ? $translation->getName() : $current->getName());
            $parent = $current->getParent();
            $current = $parent === null || $parent->isRoot() ? null : $parent;
        }
        foreach (array_reverse($parts) as $part) {
            $dto->addCategoryNode($part);
        }

        return $dto;
    }
}
