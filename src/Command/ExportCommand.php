<?php

namespace EilingIo\SyliusBatteryIncludedPlugin\Command;

use BatteryIncludedSdk\Dto\CategoryDto;
use BatteryIncludedSdk\Dto\ProductBaseDto;
use BatteryIncludedSdk\Dto\ProductPropertyDto;
use BatteryIncludedSdk\Service\SyncService;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\Table;

#[AsCommand(
    name: 'bi:export',
    description: 'Exports all Products to bi.',
)]
class ExportCommand extends Command
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private SyncService $syncService

    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $productsRaw = $this->productRepository->findAll();
        $products = [];
        /** @var ProductInterface $raw */
        foreach ($productsRaw as $raw) {
            $dto = new ProductBaseDto($raw->getId());
            $dto->setName($raw->getName());
            $dto->setDescription($raw->getDescription());
            $dto->setOrdernumber($raw->getCode());
            $dto->setImageUrl('https://syliusbatteryincludedplugin.ddev.site/media/cache/sylius_shop_product_original/'.$raw->getVariants()->first()->getProduct()->getImages()->first()->getPath());
            $dto->setInstock($raw->getVariants()->first()->getOnHand() - $raw->getVariants()->first()->getOnHold());

            $manufacturerName = 'Unbekannt';
            $cap = $raw->getAttributeByCodeAndLocale('cap_brand', 'de_DE');
            $tshirt = $raw->getAttributeByCodeAndLocale('t_shirt_brand', 'de_DE');
            $jeans = $raw->getAttributeByCodeAndLocale('jeans_brand', 'de_DE');
            $dress = $raw->getAttributeByCodeAndLocale('dress_brand', 'de_DE');
            $manufacturerName = $cap ? $cap->getValue() : $manufacturerName;
            $manufacturerName = $tshirt ? $tshirt->getValue() : $manufacturerName;
            $manufacturerName = $jeans ? $jeans->getValue() : $manufacturerName;
            $manufacturerName = $dress ? $dress->getValue() : $manufacturerName;
            $dto->setManufacture($manufacturerName);
            $dtoProperties = new ProductPropertyDto();
            foreach ($raw->getVariants() as $variant) {
                /** @var \Sylius\Component\Product\Model\ProductVariantInterface $variant */
                foreach ($variant->getOptionValues() as $optionValue) {
                    $dtoProperties->addProperty($optionValue->getOptionCode(), $optionValue->getValue());
                }
            }
            $dto->setProperties($dtoProperties);

            foreach ($raw->getTaxons() as $taxon) {
                if (!$taxon->hasChildren()) {
                    $dto->addCategory($this->getCategoryPath($taxon));
                }
            }

            $dto->setShopUrl($raw->getVariants()->first()->getProduct()->getSlug());
            $products[] = $dto;
        }

        $this->syncService->syncFullElements(...$products);

        $io->success('Export erfolgreich durchgeführt!');
        return Command::SUCCESS;
    }

    private function getCategoryPath(TaxonInterface $taxon): CategoryDto
    {
        $dto = new CategoryDto();
        $parts = [];
        $current = $taxon;

        while ($current !== null) {
            $parts[] = $current->getName();
            $current = $current->getParent();
        }
        foreach (array_reverse($parts) as $part) {
            $dto->addCategoryNode($part);
        }
        return $dto;
    }
}
