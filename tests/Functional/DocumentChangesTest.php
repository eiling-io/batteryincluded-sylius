<?php

declare(strict_types=1);

namespace Tests\EilingIo\SyliusBatteryIncludedPlugin\Functional;

use Batteryincluded\BatteryincludedBundle\Sync\DocumentChangeQueue;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use EilingIo\SyliusBatteryIncludedPlugin\Document\ShopDocumentUrlResolver;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Changed products are synced by the DocumentChangeListener, the ShopDocumentUrlResolver links them. Every test runs in
 * a transaction which is rolled back.
 */
final class DocumentChangesTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        MockApi::reset();
        $this->entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $this->entityManager->beginTransaction();
    }

    protected function tearDown(): void
    {
        // sends the remaining changes to the MockApi before the rollback
        self::getContainer()->get(DocumentChangeQueue::class)->reset();
        $this->entityManager->rollback();
        parent::tearDown();
    }

    public function testAChangedTranslationSyncsTheProductInEveryCollection(): void
    {
        $product = $this->findProduct();
        $product->getTranslation('de_DE')->setName('Geändertes Kleid');
        $this->entityManager->flush();

        $id = 'PRODUCT-' . $product->getId();
        self::assertSame(['default' => [$id => ['action' => 'sync', 'deleteMissing' => true]]], $this->queue()->getChanges());

        $this->queue()->flush();
        $bodies = MockApi::requestedBodies('/documents/import');
        self::assertCount(1, $bodies);
        self::assertStringContainsString('"id":"' . $id . '"', $bodies[0]);
        self::assertStringContainsString('Geändertes Kleid', json_decode($bodies[0], true, flags: JSON_THROW_ON_ERROR)['_PRODUCT_i18n']['de-de']['name']);
    }

    public function testAChangedPriceSyncsTheProduct(): void
    {
        $product = $this->findProduct();
        $variant = $product->getVariants()->first();
        $channel = $product->getChannels()->first();
        self::assertInstanceOf(ProductVariantInterface::class, $variant);
        self::assertInstanceOf(ChannelInterface::class, $channel);
        $channelPricing = $variant->getChannelPricingForChannel($channel);
        self::assertInstanceOf(ChannelPricingInterface::class, $channelPricing);
        $channelPricing->setPrice(4711);
        $this->entityManager->flush();

        self::assertArrayHasKey('PRODUCT-' . $product->getId(), $this->queue()->getChanges()['default']);
    }

    public function testADisabledProductIsDeleted(): void
    {
        $product = $this->findProduct();
        $product->setEnabled(false);
        $this->entityManager->flush();
        $this->queue()->flush();

        // deleteMissing: the provider does not deliver disabled products
        self::assertSame([], MockApi::requestedBodies('/documents/import'));
        self::assertSame([json_encode(['PRODUCT-' . $product->getId()])], MockApi::requestedBodies('/documents/delete'));
    }

    public function testClearedChangesAreNotSynced(): void
    {
        $product = $this->findProduct();
        $product->setEnabled(false);
        $this->entityManager->clear();
        $this->entityManager->flush();

        self::assertSame([], $this->queue()->getChanges());
    }

    public function testTheProductIsLinkedInTheLocale(): void
    {
        $product = $this->findProduct();
        $resolver = self::getContainer()->get(ShopDocumentUrlResolver::class);

        // without request: the default locale of the channel
        self::assertSame('/en_US/products/' . $product->getTranslation('en_US')->getSlug(), $resolver->resolve('PRODUCT-' . $product->getId(), 'PRODUCT', []));
        self::assertNull($resolver->resolve('PRODUCT-0', 'PRODUCT', []));
        self::assertNull($resolver->resolve('BLOG-1', 'BLOG', []));
    }

    private function queue(): DocumentChangeQueue
    {
        return self::getContainer()->get(DocumentChangeQueue::class);
    }

    private function findProduct(): ProductInterface
    {
        /** @var EntityRepository<ProductInterface> $repository */
        $repository = self::getContainer()->get('sylius.repository.product');
        $product = $repository->findOneBy(['enabled' => true], ['id' => 'ASC']);
        self::assertInstanceOf(ProductInterface::class, $product, 'The fixtures are missing, run sylius:fixtures:load --env=test.');

        return $product;
    }
}
