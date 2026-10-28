<?php

declare(strict_types=1);

namespace Tests\EilingIo\SyliusBatteryIncludedPlugin\Functional;

use Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Product\Model\ProductTranslationInterface;
use Sylius\Component\Taxonomy\Model\TaxonTranslationInterface;
use Sylius\Resource\Model\TranslatableInterface;
use Sylius\Resource\Model\TranslationInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Renders the shop pages of the plugin in english and german with the fixtures of the test application. The api
 * answers with another product than the one of the page, so only the plugin can render it.
 */
final class ShopPagesTest extends WebTestCase
{
    private KernelBrowser $client;

    /**
     * Product of the requested page (detail page, taxon).
     *
     * @var array{id: int, slug: string, taxonSlugs: array<string, string>}
     */
    private array $page;

    /**
     * Product delivered by the api, a dress: its german name differs from the english one.
     *
     * @var array{code: string, names: array<string, string>}
     */
    private array $hit;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        MockApi::reset();

        $pageProduct = self::findProduct('mens_t_shirts');
        $taxon = $pageProduct->getMainTaxon();
        self::assertInstanceOf(TaxonInterface::class, $taxon);
        $hitProduct = self::findProduct('dresses');

        // plain values: the requests share the entity manager with the test, Sylius sets the locale of an entity
        // and the shop pages load translations of one locale only
        $this->page = [
            'id' => (int) $pageProduct->getId(),
            'slug' => (string) self::translation($pageProduct, 'en_US', ProductTranslationInterface::class)->getSlug(),
            'taxonSlugs' => [
                'en_US' => (string) self::translation($taxon, 'en_US', TaxonTranslationInterface::class)->getSlug(),
                'de_DE' => (string) self::translation($taxon, 'de_DE', TaxonTranslationInterface::class)->getSlug(),
            ],
        ];
        $this->hit = [
            'code' => (string) $hitProduct->getCode(),
            'names' => [
                'en_US' => (string) self::translation($hitProduct, 'en_US', ProductTranslationInterface::class)->getName(),
                'de_DE' => (string) self::translation($hitProduct, 'de_DE', ProductTranslationInterface::class)->getName(),
            ],
        ];
        self::getContainer()->get('doctrine.orm.entity_manager')->clear();

        self::assertNotSame($this->hit['names']['en_US'], $this->hit['names']['de_DE'], 'The german fixtures are missing.');
        MockApi::$productCodes = [$this->hit['code']];
        MockApi::$presetProductCodes = [$this->hit['code']];
    }

    public function testHomepageShowsThePresetSlider(): void
    {
        $this->request('/en_US/');

        $this->assertHitIn('[aria-labelledby="index-products-heading"]', 'en_US');
        $this->assertLocaleOf('/documents/browse', 'en-us');
        // tests/TestApplication/config/config.yaml
        self::assertSame('4474bb20-a291-4ecb-9e56-59afb061864b', $this->query('/documents/browse', 'preset'));
    }

    public function testSearchWithoutHitsShowsTheNotFoundPreset(): void
    {
        MockApi::$productCodes = [];

        $this->request('/de_DE/search?search=nichts');

        $this->assertHitIn('body', 'de_DE');
        // the second browse request, after the search
        self::assertSame('29ca74c9-2b4e-4cda-a524-1e928c1b95bc', $this->query('/documents/browse', 'preset', 1));
    }

    public function testSearchShowsTheHits(): void
    {
        $this->request('/en_US/search?search=shirt');

        $this->assertHitIn('body', 'en_US');
        self::assertSame('shirt', $this->query('/documents/browse', 'q'));
        $this->assertLocaleOf('/documents/browse', 'en-us');
    }

    public function testGermanSearchShowsTheGermanNames(): void
    {
        $this->request('/de_DE/search?search=kleid');

        $this->assertHitIn('body', 'de_DE');
        $this->assertLocaleOf('/documents/browse', 'de-de');
    }

    public function testSuggestShowsTheDocuments(): void
    {
        $this->request('/en_US/searchAjax?search=shirt');

        $this->assertHitIn('.search-overlay-content', 'en_US');
        $this->assertLocaleOf('/documents/suggest', 'en-us');
    }

    public function testGermanSuggestShowsTheGermanNames(): void
    {
        $this->request('/de_DE/searchAjax?search=kleid');

        $this->assertHitIn('.search-overlay-content', 'de_DE');
        // the texts of the plugin, translations/messages.de.yaml
        self::assertStringContainsString('Produkte', $this->client->getCrawler()->filter('.search-overlay-content')->text());
        $this->assertLocaleOf('/documents/suggest', 'de-de');
    }

    public function testEmptySuggestShowsTheQuerySuggestionsOfTheLocale(): void
    {
        foreach (['en_US' => 'Jeans in Size XL', 'de_DE' => 'Jeans in Größe XL'] as $locale => $suggestion) {
            MockApi::$requests = [];
            $this->request('/' . $locale . '/searchAjax?search=');

            self::assertStringContainsString($suggestion, $this->client->getCrawler()->filter('.search-overlay-content')->text());
            $this->assertLocaleOf('/documents/highlights', strtolower(str_replace('_', '-', $locale)));
        }
    }

    public function testTaxonPageIsFilteredByTheTranslatedCategory(): void
    {
        foreach (['en_US' => 'T-shirts > Men', 'de_DE' => 'T-Shirts > Herren'] as $locale => $path) {
            MockApi::$requests = [];
            $this->request('/' . $locale . '/taxons/' . $this->page['taxonSlugs'][$locale]);

            $this->assertHitIn('body', $locale);
            $url = MockApi::requestedUrls('/documents/browse')[0];
            self::assertSame([$path], MockApi::parameter($url, 'f', '_PRODUCT_i18n.categories'));
            self::assertSame(strtolower(str_replace('_', '-', $locale)), MockApi::parameter($url, 'v', 'locale'));
        }
    }

    public function testFacetsOfOtherLocalesAreHidden(): void
    {
        MockApi::$facets = [
            ['field_name' => '_PRODUCT_i18n.properties.dress_height', 'field_label' => 'Kleiderlänge', 'type' => 'select', 'counts' => [['value' => 'Kurz', 'count' => 3]]],
            ['field_name' => '_PRODUCT_i18n.en-us.properties.dress_height', 'field_label' => 'Dress height', 'type' => 'select', 'counts' => [['value' => 'Petite', 'count' => 3]]],
        ];

        $this->request('/de_DE/search?search=kleid');

        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Kleiderlänge', $content);
        self::assertStringNotContainsString('Dress height', $content);
        self::assertStringNotContainsString('Petite', $content);
    }

    public function testProductPageShowsTheRecommendations(): void
    {
        $this->request('/en_US/products/' . $this->page['slug']);

        $this->assertHitIn('[aria-labelledby="recommendation-related-heading"]', 'en_US');
        self::assertStringContainsString('id=PRODUCT-' . $this->page['id'], urldecode(MockApi::requestedUrls('/documents/recommendations')[0]));
    }

    private function request(string $url): void
    {
        $this->client->request('GET', $url);

        self::assertResponseIsSuccessful();
    }

    private function assertHitIn(string $selector, string $locale): void
    {
        $element = $this->client->getCrawler()->filter($selector);

        self::assertCount(1, $element, sprintf('"%s" is missing.', $selector));
        self::assertStringContainsString($this->hit['names'][$locale], $element->text());
    }

    private function assertLocaleOf(string $path, string $locale): void
    {
        $urls = MockApi::requestedUrls($path);
        self::assertNotEmpty($urls);
        self::assertSame($locale, MockApi::parameter($urls[0], 'v', 'locale'));
    }

    private function query(string $path, string $name, int $request = 0): mixed
    {
        parse_str((string) parse_url(MockApi::requestedUrls($path)[$request], PHP_URL_QUERY), $query);

        return $query[$name] ?? null;
    }

    /**
     * The stored translation of the locale, without the fallback of getTranslation().
     *
     * @template T of TranslationInterface
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private static function translation(TranslatableInterface $translatable, string $locale, string $class): TranslationInterface
    {
        $translation = $translatable->getTranslations()->get($locale);
        self::assertInstanceOf($class, $translation, sprintf('The translation %s is missing.', $locale));

        return $translation;
    }

    /**
     * The first enabled product of the channel FASHION_WEB with this main taxon from the fixtures.
     */
    private static function findProduct(string $mainTaxon): ProductInterface
    {
        /** @var EntityRepository<ProductInterface> $repository */
        $repository = self::getContainer()->get('sylius.repository.product');
        $product = $repository->createQueryBuilder('o')
            ->innerJoin('o.channels', 'channel')
            ->innerJoin('o.mainTaxon', 'taxon')
            ->andWhere('channel.code = :channel')
            ->andWhere('o.enabled = true')
            ->andWhere('taxon.code = :taxon')
            ->setParameter('channel', 'FASHION_WEB')
            ->setParameter('taxon', $mainTaxon)
            ->orderBy('o.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(ProductInterface::class, $product, 'The fixtures are missing, run sylius:fixtures:load --env=test.');

        return $product;
    }
}
