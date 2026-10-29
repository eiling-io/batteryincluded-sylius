<?php

declare(strict_types=1);

namespace EilingIo\SyliusBatteryIncludedPlugin\Sync;

use Batteryincluded\BatteryincludedBundle\Sync\DocumentChangeQueue;
use Doctrine\ORM\Event\OnFlushEventArgs;
use EilingIo\SyliusBatteryIncludedPlugin\Dto\ContentPageDto;
use EilingIo\SyliusBatteryIncludedPlugin\Provider\ProductDataProvider;
use Sylius\CmsPlugin\Entity\ContentConfigurationInterface;
use Sylius\CmsPlugin\Entity\PageInterface;
use Sylius\CmsPlugin\Entity\PageTranslationInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\ProductImageInterface;
use Sylius\Component\Core\Model\ProductTaxonInterface;
use Sylius\Component\Product\Model\ProductAttributeValueInterface;
use Sylius\Component\Product\Model\ProductInterface;
use Sylius\Component\Product\Model\ProductTranslationInterface;
use Sylius\Component\Product\Model\ProductVariantInterface;

/**
 * Syncs the documents of products and CMS pages changed in the admin, by the API or by own code. Changes of a product,
 * its translations, variants, prices, images, taxons and attributes sync the product in every collection of the
 * plugin: the provider of a collection decides whether the product belongs to it, `deleteMissing` removes it from the
 * other ones (disabled, removed from the channel). Removed products and pages are deleted.
 *
 * Changes affecting many products (taxon names, option values) are not tracked, run `bi:export` afterwards.
 */
final class DocumentChangeListener
{
    /**
     * Products and pages to sync, the ids of new entities are generated during the flush.
     *
     * @var array<int, ProductInterface|PageInterface>
     */
    private array $changed = [];

    /**
     * Document ids of removed products and pages, read before Doctrine removes them.
     *
     * @var array<string, true>
     */
    private array $removed = [];

    /**
     * @param list<string> $collections collections of the data providers of the plugin
     */
    public function __construct(
        private readonly DocumentChangeQueue $queue,
        private readonly array $collections,
        private readonly bool $cmsPages = false,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $unitOfWork = $args->getObjectManager()->getUnitOfWork();

        foreach ([...$unitOfWork->getScheduledEntityInsertions(), ...$unitOfWork->getScheduledEntityUpdates()] as $entity) {
            $this->add($this->getDocumentEntity($entity));
        }

        // e.g. the channels of a product
        foreach ([...$unitOfWork->getScheduledCollectionUpdates(), ...$unitOfWork->getScheduledCollectionDeletions()] as $collection) {
            $this->add($this->getDocumentEntity($collection->getOwner()));
        }

        foreach ($unitOfWork->getScheduledEntityDeletions() as $entity) {
            $documentEntity = $this->getDocumentEntity($entity);
            if ($documentEntity === $entity) {
                $id = $this->getDocumentId($entity);
                if ($id !== null) {
                    $this->removed[$id] = true;
                }
            } else {
                // a removed variant or image changes its product
                $this->add($documentEntity);
            }
        }
    }

    public function postFlush(): void
    {
        $changed = $this->changed;
        $removed = $this->removed;
        $this->onClear();

        foreach ($changed as $entity) {
            $id = $this->getDocumentId($entity);
            if ($id === null || isset($removed[$id])) {
                continue;
            }

            foreach ($this->collections as $collection) {
                $this->queue->scheduleSync($id, $collection, true);
            }
        }

        foreach (array_keys($removed) as $id) {
            foreach ($this->collections as $collection) {
                $this->queue->scheduleDelete($id, $collection);
            }
        }
    }

    /**
     * Discards the changes of a failed flush and of `clear()`.
     */
    public function onClear(): void
    {
        $this->changed = [];
        $this->removed = [];
    }

    private function add(ProductInterface|PageInterface|null $entity): void
    {
        if ($entity !== null) {
            $this->changed[spl_object_id($entity)] = $entity;
        }
    }

    /**
     * The product or page whose document contains the entity.
     */
    private function getDocumentEntity(?object $entity): ProductInterface|PageInterface|null
    {
        $documentEntity = match (true) {
            $entity instanceof ProductInterface => $entity,
            $entity instanceof ProductTranslationInterface => $entity->getTranslatable(),
            $entity instanceof ProductVariantInterface,
            $entity instanceof ProductTaxonInterface,
            $entity instanceof ProductAttributeValueInterface => $entity->getProduct(),
            $entity instanceof ChannelPricingInterface => $entity->getProductVariant()?->getProduct(),
            $entity instanceof ProductImageInterface => $entity->getOwner(),
            !$this->cmsPages => null,
            $entity instanceof PageInterface => $entity,
            $entity instanceof PageTranslationInterface => $entity->getTranslatable(),
            $entity instanceof ContentConfigurationInterface => $entity->getPage(),
            default => null,
        };

        return $documentEntity instanceof ProductInterface || $documentEntity instanceof PageInterface ? $documentEntity : null;
    }

    private function getDocumentId(ProductInterface|PageInterface $entity): ?string
    {
        $id = $entity->getId();
        if ($id === null) {
            return null;
        }

        return ($entity instanceof ProductInterface ? ProductDataProvider::TYPE : ContentPageDto::TYPE) . '-' . $id;
    }
}
