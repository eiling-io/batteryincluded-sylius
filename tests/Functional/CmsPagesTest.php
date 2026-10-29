<?php

declare(strict_types=1);

namespace Tests\EilingIo\SyliusBatteryIncludedPlugin\Functional;

use Batteryincluded\BatteryincludedBundle\Sync\DocumentChangeQueue;
use Doctrine\ORM\EntityRepository;
use EilingIo\SyliusBatteryIncludedPlugin\Document\ShopDocumentUrlResolver;
use Sylius\CmsPlugin\Entity\PageInterface;
use Sylius\CmsPlugin\Entity\PageTranslationInterface;
use Sylius\CmsPlugin\SyliusCmsPlugin;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Pages of the Sylius CMS plugin (fixture suite batteryincluded_cms): export and search. Skipped without the CMS plugin.
 */
final class CmsPagesTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        if (!class_exists(SyliusCmsPlugin::class)) {
            self::markTestSkipped('The Sylius CMS plugin is not installed.');
        }

        $this->client = self::createClient();
        MockApi::reset();
    }

    public function testSearchShowsTheMatchingPagesAboveTheProducts(): void
    {
        MockApi::$contentPageCodes = ['returns', 'shipping'];

        $this->client->request('GET', '/en_US/search?search=delivery');

        self::assertResponseIsSuccessful();
        $pages = $this->client->getCrawler()->filter('[data-test-content-pages] a');
        self::assertSame(['Returns & exchanges', 'Shipping & delivery'], $pages->each(static fn ($link) => trim($link->filter('.fw-semibold')->text())));
        self::assertSame('/en_US/pages/returns', $pages->first()->attr('href'));

        // products and pages are searched separately
        $types = array_map(static fn (string $url) => self::filterOf($url, 'type'), MockApi::requestedUrls('/documents/browse'));
        self::assertContains(['PRODUCT'], $types);
        self::assertContains(['CONTENT'], $types);
    }

    public function testGermanSearchShowsTheGermanPages(): void
    {
        MockApi::$contentPageCodes = ['returns', 'shipping'];

        $this->client->request('GET', '/de_DE/search?search=versand');

        self::assertResponseIsSuccessful();
        $pages = $this->client->getCrawler()->filter('[data-test-content-pages] a');
        self::assertSame(['Rückgabe & Umtausch', 'Versand & Lieferung'], $pages->each(static fn ($link) => trim($link->filter('.fw-semibold')->text())));
        self::assertSame('/de_DE/pages/rueckgabe', $pages->first()->attr('href'));

        $contentSearch = array_values(array_filter(MockApi::requestedUrls('/documents/browse'), static fn (string $url) => self::filterOf($url, 'type') === ['CONTENT']));
        self::assertSame('de-de', MockApi::parameter($contentSearch[0], 'v', 'locale'));
    }

    public function testSuggestShowsThePages(): void
    {
        MockApi::$contentPageCodes = ['size_guide'];

        $this->client->request('GET', '/en_US/searchAjax?search=size');

        self::assertResponseIsSuccessful();
        $link = $this->client->getCrawler()->filter('[data-test-content-pages] a');
        self::assertCount(1, $link);
        self::assertSame('Size guide', trim($link->text()));
        self::assertSame('/en_US/pages/size-guide', $link->attr('href'));
    }

    public function testDisabledAndUnknownPagesAreNotShown(): void
    {
        MockApi::$contentPageCodes = ['internal', 'unknown'];

        $this->client->request('GET', '/en_US/searchAjax?search=notes');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $this->client->getCrawler()->filter('[data-test-content-pages]'));
    }

    public function testExportDeliversTheEnabledPages(): void
    {
        $report = (string) tempnam(sys_get_temp_dir(), 'bi-report');
        $command = $this->command('bi:export');
        $command->execute(['--dry-run' => true, '--report' => $report]);

        $command->assertCommandIsSuccessful();
        $types = json_decode((string) file_get_contents($report), true, flags: JSON_THROW_ON_ERROR)['types'];
        unlink($report);
        self::assertSame(4, $types['CONTENT']);
        self::assertGreaterThan(0, $types['PRODUCT']);
    }

    public function testSyncDeliversTheDocumentOfAPage(): void
    {
        /** @var EntityRepository<PageInterface> $repository */
        $repository = self::getContainer()->get('sylius_cms.repository.page');
        $shipping = $repository->findOneBy(['code' => 'shipping']);
        $internal = $repository->findOneBy(['code' => 'internal']);
        self::assertNotNull($shipping, 'The fixtures are missing, run sylius:fixtures:load batteryincluded_cms --env=test.');
        self::assertNotNull($internal);

        $command = $this->command('bi:sync');
        $command->execute(['ids' => ['CONTENT-' . $shipping->getId(), 'CONTENT-' . $internal->getId()], '--dry-run' => true]);

        $command->assertCommandIsSuccessful();
        $display = $command->getDisplay();
        self::assertStringContainsString('"type": "CONTENT"', $display);
        self::assertStringContainsString('"code": "shipping"', $display);
        self::assertStringContainsString('"title": "Shipping & delivery"', $display);
        self::assertStringContainsString('Orders placed before 2 pm', $display);
        self::assertStringContainsString('/en_US/pages/shipping', $display);
        // every language of the channel
        self::assertStringContainsString('"de-de": {', $display);
        self::assertStringContainsString('"title": "Versand & Lieferung"', $display);
        self::assertStringContainsString('Bestellungen bis 14 Uhr', $display);
        self::assertStringContainsString('/de_DE/pages/versand', $display);
        self::assertStringContainsString('No document for: CONTENT-' . $internal->getId(), $display);
    }

    public function testAChangedPageIsSyncedAndLinked(): void
    {
        /** @var EntityRepository<PageInterface> $repository */
        $repository = self::getContainer()->get('sylius_cms.repository.page');
        $shipping = $repository->findOneBy(['code' => 'shipping']);
        self::assertNotNull($shipping);
        $id = 'CONTENT-' . $shipping->getId();

        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $queue = self::getContainer()->get(DocumentChangeQueue::class);
        $entityManager->beginTransaction();
        try {
            $translation = $shipping->getTranslation('de_DE');
            self::assertInstanceOf(PageTranslationInterface::class, $translation);
            $translation->setTitle('Versand');
            $entityManager->flush();

            self::assertSame(['default' => [$id => ['action' => 'sync', 'deleteMissing' => true]]], $queue->getChanges());
        } finally {
            $queue->reset();
            $entityManager->rollback();
        }

        self::assertSame('/en_US/pages/shipping', self::getContainer()->get(ShopDocumentUrlResolver::class)->resolve($id, 'CONTENT', []));
    }

    private function command(string $name): CommandTester
    {
        return new CommandTester((new Application(self::$kernel ?? self::bootKernel()))->find($name));
    }

    /**
     * @return list<string>|null
     */
    private static function filterOf(string $url, string $field): ?array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query['f'][$field] ?? null;
    }
}
