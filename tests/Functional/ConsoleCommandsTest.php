<?php

declare(strict_types=1);

namespace Tests\EilingIo\SyliusBatteryIncludedPlugin\Functional;

use Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The commands of the batteryincluded bundle with the ProductDataProvider of the plugin.
 */
final class ConsoleCommandsTest extends KernelTestCase
{
    public function testExportSerializesTheProductsOfTheChannel(): void
    {
        $command = $this->command('bi:export');
        $command->execute(['--dry-run' => true]);

        $command->assertCommandIsSuccessful();
        self::assertStringContainsString('ProductDataProvider', $command->getDisplay());
        self::assertMatchesRegularExpression('/Dry run: [1-9]\d* documents serialized/', $command->getDisplay());
    }

    public function testSyncDeliversTheDocumentOfAProduct(): void
    {
        /** @var EntityRepository<ProductInterface> $repository */
        $repository = self::getContainer()->get('sylius.repository.product');
        /** @var ProductInterface $product */
        $product = $repository->findOneBy(['enabled' => true], ['id' => 'ASC']);

        $command = $this->command('bi:sync');
        $command->execute(['ids' => ['PRODUCT-' . $product->getId(), 'PRODUCT-0'], '--dry-run' => true]);

        $command->assertCommandIsSuccessful();
        $display = $command->getDisplay();
        self::assertStringContainsString('"id": "PRODUCT-' . $product->getId() . '"', $display);
        self::assertStringContainsString('"ordernumber": ' . json_encode($product->getCode()), $display);
        self::assertStringContainsString('"price":', $display);
        // every language of the channel, the german texts come from the GermanDemoDataFixture
        self::assertStringContainsString('"en-us": {', $display);
        self::assertStringContainsString('"de-de": {', $display);
        self::assertStringContainsString('"name": ' . json_encode($product->getTranslation('de_DE')->getName(), JSON_UNESCAPED_UNICODE), $display);
        self::assertStringContainsString('/de_DE/products/' . $product->getTranslation('de_DE')->getSlug(), $display);
        self::assertStringContainsString('für jeden Tag', $display);
        self::assertStringContainsString('No document for: PRODUCT-0', $display);
    }

    public function testTheChannelsOfTheSharedCollectionExportTheirAvailability(): void
    {
        $dress = $this->findProduct('dresses');
        $cap = $this->findProduct('simple_caps');

        $command = $this->command('bi:sync');
        $command->execute(['ids' => ['PRODUCT-' . $dress->getId(), 'PRODUCT-' . $cap->getId()], '--dry-run' => true]);

        $command->assertCommandIsSuccessful();
        $documents = $this->documents($command->getDisplay());

        // FASHION_WEB delivers the defaults, FASHION_CH sells the dress 10 % more expensive and no caps
        $channels = self::getContainer()->get('sylius.repository.channel');
        $variant = $dress->getVariants()->first();
        self::assertInstanceOf(ProductVariantInterface::class, $variant);
        $webPrice = $variant->getChannelPricingForChannel($channels->findOneByCode('FASHION_WEB'))?->getPrice();
        $chPrice = $variant->getChannelPricingForChannel($channels->findOneByCode('FASHION_CH'))?->getPrice();
        $availability = $documents['PRODUCT-' . $dress->getId()]['_PRODUCT_availability'];
        self::assertSame($webPrice / 100, $documents['PRODUCT-' . $dress->getId()]['_PRODUCT']['price']);
        self::assertEquals(['active' => true, 'price' => $webPrice / 100], $availability['fashion-web']);
        self::assertEquals(['active' => true, 'price' => $chPrice / 100], $availability['fashion-ch']);
        self::assertSame(['active' => false], $documents['PRODUCT-' . $cap->getId()]['_PRODUCT_availability']['fashion-ch']);
    }

    /**
     * Documents printed by `bi:sync --dry-run`, by id.
     *
     * @return array<string, array<string, mixed>>
     */
    private function documents(string $display): array
    {
        preg_match_all('/^\{\n.*?^\}$/ms', $display, $matches);
        $documents = [];
        foreach ($matches[0] as $json) {
            $document = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
            $documents[$document['id']] = $document;
        }

        return $documents;
    }

    private function findProduct(string $mainTaxon): ProductInterface
    {
        /** @var EntityRepository<ProductInterface> $repository */
        $repository = self::getContainer()->get('sylius.repository.product');
        $product = $repository->createQueryBuilder('o')
            ->innerJoin('o.mainTaxon', 'taxon')
            ->andWhere('o.enabled = true')
            ->andWhere('taxon.code = :taxon')
            ->setParameter('taxon', $mainTaxon)
            ->orderBy('o.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        self::assertInstanceOf(ProductInterface::class, $product);

        return $product;
    }

    private function command(string $name): CommandTester
    {
        $application = new Application(self::bootKernel());

        return new CommandTester($application->find($name));
    }
}
