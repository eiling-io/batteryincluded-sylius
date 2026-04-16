<?php

namespace EilingIo\SyliusBatteryIncludedPlugin\Command;

use BatteryIncludedSdk\Dto\CategoryDto;
use BatteryIncludedSdk\Dto\ProductBaseDto;
use BatteryIncludedSdk\Dto\ProductPropertyDto;
use EilingIo\SyliusBatteryIncludedPlugin\Factory\ServiceFactory;
use Liip\ImagineBundle\Service\FilterService;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsCommand(
    name: 'bi:export',
    description: 'Exports all Products to bi.',
)]
class ExportCommand extends Command
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private readonly ServiceFactory $serviceFactory,
        private UrlGeneratorInterface $router,
        private FilterService $imagineFilter,
        private ChannelRepositoryInterface $channelRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'channel',
                InputArgument::REQUIRED,
                'Channel code to export data for.',
                null
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $channels = [];
        $channelCode = $input->getArgument('channel');
        /** @var ChannelInterface|null $channel */
        $channel = $this->channelRepository->findOneByCode($channelCode);
        if ($channel === null) {
            foreach ($this->channelRepository->findEnabled() as $availableChannel) {
                $channels[] = $availableChannel;
                $io->writeln(sprintf('Available channel: "%s"', $availableChannel->getCode()));
            }
            $io->writeln('All channels are exported now!');
        } else {
            $channels[] = $channel;
        }

        foreach ($channels as $channel) {
            $io->writeln(sprintf('Exporting products to bi: "%s"', $channel->getCode()));
            $products = [];
            $productsRaw = $this->productRepository->findAll();
            /** @var ProductInterface $raw */
            foreach ($productsRaw as $raw) {
                $dto = new ProductBaseDto($raw->getId());
                $dto->setName($raw->getName());
                $dto->setDescription($raw->getDescription());
                $dto->setOrdernumber($raw->getCode());
                $image = $raw->getImages()->first();
                $imageUrl = null;
                if ($image) {
                    $urlRaw = $this->imagineFilter->getUrlOfFilteredImage(
                        $image->getPath(),
                        'sylius_shop_product_thumbnail'
                    );
                    $parsed = parse_url($urlRaw);
                    $imagePath = $parsed['path'] ?? $urlRaw;
                    $baseUrl = $channel->getHostname();
                    if (!str_starts_with($baseUrl, 'http')) {
                        $baseUrl = 'https://' . ltrim($baseUrl, '/');
                    }
                    $baseUrl = rtrim($baseUrl, '/');
                    $imageUrl = $baseUrl . $imagePath;
                }
                $dto->setImageUrl($imageUrl);
                $dto->setInstock($raw->getVariants()->first()->getOnHand() - $raw->getVariants()->first()->getOnHold());
                $dto->setRating($raw->getAverageRating());

                $variant = $raw->getVariants()->first();
                $price = null;
                if ($variant !== false) {
                    $channelPricing = $variant->getChannelPricingForChannel($channel);
                    if ($channelPricing) {
                        $price = $channelPricing->getPrice();
                        $price /= 100;
                    }
                }
                $dto->setPrice($price);

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

                $dto->setShopUrl(
                    $this->router->generate(
                        'sylius_shop_product_show',
                        ['slug' => $raw->getSlug()],
                        UrlGeneratorInterface::ABSOLUTE_URL
                    )
                );
                $products[] = $dto;
            }

            $this->serviceFactory->getSyncService($channel->getCode())->syncFullElements(...$products);
        }

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
            $current = $current->getParent()->isRoot() ? null : $current->getParent();
        }
        foreach (array_reverse($parts) as $part) {
            $dto->addCategoryNode($part);
        }
        return $dto;
    }
}
