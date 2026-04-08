<?php

namespace EilingIo\SyliusBatteryIncludedPlugin\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Doctrine\ORM\QueryBuilder;
use Sylius\Resource\Model\ResourceInterface;

class ProductRepositoryDecorator extends ServiceEntityRepository implements ProductRepositoryInterface
{
    private ProductRepositoryInterface $inner;
    // Hier kannst du deinen eigenen Suchservice injizieren
    // private YourCustomSearchService $searchService;

    public function __construct(ProductRepositoryInterface $inner /*, YourCustomSearchService $searchService */)
    {
        $this->inner = $inner;
        // $this->searchService = $searchService;
    }

    public function createListQueryBuilder(string $locale, mixed $taxonId = null): QueryBuilder
    {
        return $this->inner->createListQueryBuilder($locale, $taxonId);
    }

    public function createShopListQueryBuilder(
        ChannelInterface $channel,
        TaxonInterface $taxon,
        string $locale,
        array $sorting = [],
        bool $includeAllDescendants = false,
    ): QueryBuilder {
        return $this->inner->createShopListQueryBuilder(
            $channel,
            $taxon,
            $locale,
            $sorting,
            $includeAllDescendants
        );
    }

    public function findLatestByChannel(ChannelInterface $channel, string $locale, int $count): array
    {
        return $this->inner->findLatestByChannel($channel, $locale, $count);
    }

    // ads
    public function findOneByChannelAndSlug(ChannelInterface $channel, string $locale, string $slug): ?ProductInterface
    {
        return $this->inner->findOneByChannelAndSlug($channel, $locale, $slug);
    }

    public function findOneByChannelAndCode(ChannelInterface $channel, string $code): ?ProductInterface
    {
        return $this->inner->findOneByChannelAndCode($channel, $code);
    }

    public function findOneByCode(string $code): ?ProductInterface
    {
        return $this->inner->findOneByCode($code);
    }

    public function findByTaxon(TaxonInterface $taxon): array
    {
        echo "\n";
        var_dump($taxon);
        echo "\n";
        die();
        return $this->inner->findByTaxon($taxon);
    }

    public function findByProductTaxonIds(array $ids): array
    {
        echo "\n";
        var_dump($ids);
        echo "\n";
        die();
        return $this->inner->findByProductTaxonIds($ids);
    }

    public function findByName(string $name, string $locale): array
    {
        echo "\n";
        var_dump($name);
        echo "\n";
        die();
        return $this->inner->findByName($name, $locale);
    }

    public function findOneByChannelAndCodeWithAvailableAssociations(
        ChannelInterface $channel,
        string $code
    ): ?ProductInterface {
        return $this->inner->findOneByChannelAndCodeWithAvailableAssociations($channel, $code);
    }

    public function findByNamePart(string $phrase, string $locale, ?int $limit = null): array
    {
        return $this->inner->findByNamePart($phrase, $locale, $limit);
    }

    public function findByPhrase(string $phrase, string $locale, ?int $limit = null): iterable
    {
        return $this->inner->findByPhrase($phrase, $locale, $limit);
    }

    public function createPaginator(array $criteria = [], array $sorting = []): iterable
    {
        return $this->inner->createPaginator($criteria, $sorting);
    }

    public function add(ResourceInterface $resource): void
    {
        $this->inner->add($resource);
    }

    public function remove(ResourceInterface $resource): void
    {
        $this->inner->remove($resource);
    }
}

