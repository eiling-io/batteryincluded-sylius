<?php

declare(strict_types=1);

namespace Tests\EilingIo\SyliusBatteryIncludedPlugin\Functional;

use Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\ProductInterface;
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

    private function command(string $name): CommandTester
    {
        $application = new Application(self::bootKernel());

        return new CommandTester($application->find($name));
    }
}
