<?php

declare(strict_types=1);

namespace Tests\EilingIo\SyliusBatteryIncludedPlugin\Fixture;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Sylius\Bundle\FixturesBundle\Fixture\AbstractFixture;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

/**
 * Second channel FASHION_CH sharing the collection of FASHION_WEB, see config/shared_channel.php. It sells every
 * product except the caps, 10 % more expensive. Runs after the GermanDemoDataFixture, the channel gets the languages
 * of FASHION_WEB. With two channels Sylius finds the channel by the host name only, `source_hostname` sets the one of
 * FASHION_WEB.
 */
final class SharedChannelFixture extends AbstractFixture
{
    public const CHANNEL = 'FASHION_CH';

    public const HOSTNAME = 'ch.localhost';

    private const SOURCE_CHANNEL = 'FASHION_WEB';

    // main taxons of the products which are not sold in FASHION_CH
    private const EXCLUDED_TAXONS = ['simple_caps', 'caps_with_pompons'];

    /**
     * @param FactoryInterface<ChannelInterface>        $channelFactory
     * @param FactoryInterface<ChannelPricingInterface> $channelPricingFactory
     * @param ObjectRepository<ProductInterface>        $productRepository
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ChannelRepositoryInterface $channelRepository,
        private readonly FactoryInterface $channelFactory,
        private readonly FactoryInterface $channelPricingFactory,
        private readonly ObjectRepository $productRepository,
    ) {
    }

    public function getName(): string
    {
        return 'batteryincluded_shared_channel';
    }

    public function load(array $options): void
    {
        $source = $this->channelRepository->findOneByCode(self::SOURCE_CHANNEL);
        if (!$source instanceof ChannelInterface) {
            return;
        }

        if ($options['source_hostname'] !== null) {
            $source->setHostname($options['source_hostname']);
        }

        $existing = $this->channelRepository->findOneByCode(self::CHANNEL);
        if ($existing instanceof ChannelInterface) {
            $existing->setHostname($options['hostname']);
            $this->entityManager->flush();

            return;
        }

        $channel = $this->createChannel($source, $options['hostname']);
        $this->entityManager->persist($channel);

        /** @var ProductInterface $product */
        foreach ($this->productRepository->findAll() as $product) {
            if (!$product->hasChannel($source) || in_array($product->getMainTaxon()?->getCode(), self::EXCLUDED_TAXONS, true)) {
                continue;
            }

            $product->addChannel($channel);
            /** @var ProductVariantInterface $variant */
            foreach ($product->getVariants() as $variant) {
                $price = $variant->getChannelPricingForChannel($source)?->getPrice();
                if ($price === null) {
                    continue;
                }

                $channelPricing = $this->channelPricingFactory->createNew();
                $channelPricing->setChannelCode(self::CHANNEL);
                $channelPricing->setPrice((int) round($price * 1.1));
                $variant->addChannelPricing($channelPricing);
            }
        }

        $this->entityManager->flush();
    }

    protected function configureOptionsNode(ArrayNodeDefinition $optionsNode): void
    {
        $optionsNode
            ->children()
                ->scalarNode('hostname')->defaultValue(self::HOSTNAME)->cannotBeEmpty()->end()
                ->scalarNode('source_hostname')->defaultNull()->end()
            ->end();
    }

    private function createChannel(ChannelInterface $source, string $hostname): ChannelInterface
    {
        $channel = $this->channelFactory->createNew();
        $channel->setCode(self::CHANNEL);
        $channel->setName('Fashion Switzerland');
        $channel->setHostname($hostname);
        $channel->setEnabled(true);
        $channel->setBaseCurrency($source->getBaseCurrency());
        $channel->setDefaultLocale($source->getDefaultLocale());
        $channel->setTaxCalculationStrategy($source->getTaxCalculationStrategy());
        $channel->setDefaultTaxZone($source->getDefaultTaxZone());
        $channel->setMenuTaxon($source->getMenuTaxon());
        foreach ($source->getLocales() as $locale) {
            $channel->addLocale($locale);
        }
        foreach ($source->getCurrencies() as $currency) {
            $channel->addCurrency($currency);
        }

        return $channel;
    }
}
