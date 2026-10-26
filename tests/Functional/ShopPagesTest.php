<?php

declare(strict_types=1);

namespace Tests\EilingIo\SyliusBatteryIncludedPlugin\Functional;

use Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Renders the shop pages of the plugin with the fixtures of the test application. The api answers with another
 * product than the one of the page, so only the plugin can render it.
 */
final class ShopPagesTest extends WebTestCase
{
    private KernelBrowser $client;

    /**
     * Product of the requested page (detail page, taxon).
     */
    private ProductInterface $pageProduct;

    /**
     * Product delivered by the api.
     */
    private ProductInterface $hitProduct;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        MockApi::reset();

        $this->pageProduct = self::findProduct('ASC');
        $this->hitProduct = self::findProduct('DESC');
        self::assertNotSame($this->pageProduct->getId(), $this->hitProduct->getId());
        MockApi::$productCodes = [(string) $this->hitProduct->getCode()];
    }

    public function testHomepageShowsThePresetSlider(): void
    {
        $this->request('/en_US/');

        $this->assertHitIn('[aria-labelledby="index-products-heading"]');
        self::assertNotEmpty(MockApi::requestedUrls('/documents/browse'));
    }

    public function testSearchShowsTheHits(): void
    {
        $this->request('/search?search=shirt');

        $this->assertHitIn('body');
        self::assertStringContainsString('q=shirt', urldecode(MockApi::requestedUrls('/documents/browse')[0]));
    }

    public function testSuggestShowsTheDocuments(): void
    {
        $this->request('/searchAjax?search=shirt');

        $this->assertHitIn('.search-overlay-content');
        self::assertNotEmpty(MockApi::requestedUrls('/documents/suggest'));
    }

    public function testEmptySuggestLoadsTheHighlights(): void
    {
        $this->request('/searchAjax?search=');

        self::assertNotEmpty(MockApi::requestedUrls('/documents/highlights'));
    }

    public function testTaxonPageIsFilteredByTheCategory(): void
    {
        $taxon = $this->pageProduct->getMainTaxon();
        self::assertNotNull($taxon);

        $this->request('/en_US/taxons/' . $taxon->getTranslation('en_US')->getSlug());

        $this->assertHitIn('body');
        self::assertStringContainsString('_PRODUCT.categories', urldecode(MockApi::requestedUrls('/documents/browse')[0]));
    }

    public function testProductPageShowsTheRecommendations(): void
    {
        $this->request('/en_US/products/' . $this->pageProduct->getTranslation('en_US')->getSlug());

        $this->assertHitIn('[aria-labelledby="recommendation-related-heading"]');
        self::assertStringContainsString('id=PRODUCT-' . $this->pageProduct->getId(), urldecode(MockApi::requestedUrls('/documents/recommendations')[0]));
    }

    private function request(string $url): void
    {
        $this->client->request('GET', $url);

        self::assertResponseIsSuccessful();
    }

    private function assertHitIn(string $selector): void
    {
        $element = $this->client->getCrawler()->filter($selector);

        self::assertCount(1, $element, sprintf('"%s" is missing.', $selector));
        self::assertStringContainsString((string) $this->hitProduct->getTranslation('en_US')->getName(), $element->text());
    }

    /**
     * The first or last enabled product of the channel FASHION_WEB with main taxon from the fixtures.
     */
    private static function findProduct(string $order): ProductInterface
    {
        /** @var EntityRepository<ProductInterface> $repository */
        $repository = self::getContainer()->get('sylius.repository.product');
        $product = $repository->createQueryBuilder('o')
            ->innerJoin('o.channels', 'channel')
            ->andWhere('channel.code = :channel')
            ->andWhere('o.enabled = true')
            ->andWhere('o.mainTaxon IS NOT NULL')
            ->setParameter('channel', 'FASHION_WEB')
            ->orderBy('o.id', $order)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(ProductInterface::class, $product, 'The fixtures are missing, run sylius:fixtures:load --env=test.');

        return $product;
    }
}
