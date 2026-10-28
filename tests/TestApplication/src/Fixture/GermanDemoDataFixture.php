<?php

declare(strict_types=1);

namespace Tests\EilingIo\SyliusBatteryIncludedPlugin\Fixture;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Sylius\Bundle\FixturesBundle\Fixture\AbstractFixture;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Product\Model\ProductOptionInterface;

/**
 * German texts for the demo shop: the Sylius fixtures create a de_DE translation of everything, but with the english
 * texts and random taxon names. Runs at the end of the default suite and adds de_DE to the channel FASHION_WEB.
 */
final class GermanDemoDataFixture extends AbstractFixture
{
    private const LOCALE = 'de_DE';

    private const CHANNEL = 'FASHION_WEB';

    // taxon code => name, slug
    private const TAXONS = [
        'category' => ['Kategorie', 'kategorie'],
        't_shirts' => ['T-Shirts', 'kategorie/t-shirts'],
        'mens_t_shirts' => ['Herren', 't-shirts/herren'],
        'womens_t_shirts' => ['Damen', 't-shirts/damen'],
        'caps' => ['Mützen', 'kategorie/muetzen'],
        'simple_caps' => ['Einfach', 'muetzen/einfach'],
        'caps_with_pompons' => ['Mit Bommel', 'muetzen/mit-bommel'],
        'dresses' => ['Kleider', 'kategorie/kleider'],
        'jeans' => ['Jeans', 'kategorie/jeans'],
        'mens_jeans' => ['Herren', 'jeans/herren'],
        'womens_jeans' => ['Damen', 'jeans/damen'],
    ];

    private const OPTIONS = [
        'dress_height' => 'Kleiderlänge',
        'dress_size' => 'Kleidergröße',
        'jeans_size' => 'Jeansgröße',
        't_shirt_size' => 'T-Shirt-Größe',
    ];

    private const OPTION_VALUES = [
        'dress_height_petite' => 'Kurz',
        'dress_height_regular' => 'Normal',
        'dress_height_tall' => 'Lang',
    ];

    private const MATERIALS = [
        'cotton' => 'Baumwolle',
        'polyester' => 'Polyester',
        'wool' => 'Wolle',
        'silk' => 'Seide',
        'denim' => 'Denim',
        'rayon' => 'Viskose',
        'linen' => 'Leinen',
        'viscose' => 'Viskose',
    ];

    private const SEASONS = ['Spring' => 'Frühling', 'Summer' => 'Sommer', 'Fall' => 'Herbst', 'Winter' => 'Winter'];

    // main taxon => product type (for the name and the description)
    private const TYPES = [
        't_shirts' => 'T-Shirt',
        'mens_t_shirts' => 'T-Shirt',
        'womens_t_shirts' => 'T-Shirt',
        'caps' => 'Mütze',
        'simple_caps' => 'Mütze',
        'caps_with_pompons' => 'Bommelmütze',
        'dresses' => 'Kleid',
        'jeans' => 'Jeans',
        'mens_jeans' => 'Jeans',
        'womens_jeans' => 'Jeans',
    ];

    /**
     * @param ObjectRepository<LocaleInterface>        $localeRepository
     * @param ObjectRepository<TaxonInterface>         $taxonRepository
     * @param ObjectRepository<ProductInterface>       $productRepository
     * @param ObjectRepository<ProductOptionInterface> $productOptionRepository
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ChannelRepositoryInterface $channelRepository,
        private readonly ObjectRepository $localeRepository,
        private readonly ObjectRepository $taxonRepository,
        private readonly ObjectRepository $productRepository,
        private readonly ObjectRepository $productOptionRepository,
    ) {
    }

    public function getName(): string
    {
        return 'batteryincluded_german';
    }

    public function load(array $options): void
    {
        $this->addLocaleToChannel();
        $this->translateTaxons();
        $this->translateOptions();
        $this->translateProducts();

        $this->entityManager->flush();
    }

    private function addLocaleToChannel(): void
    {
        $channel = $this->channelRepository->findOneByCode(self::CHANNEL);
        $locale = $this->localeRepository->findOneBy(['code' => self::LOCALE]);
        if ($channel instanceof ChannelInterface && $locale instanceof LocaleInterface && !$channel->hasLocale($locale)) {
            $channel->addLocale($locale);
        }
    }

    private function translateTaxons(): void
    {
        foreach (self::TAXONS as $code => [$name, $slug]) {
            $taxon = $this->taxonRepository->findOneBy(['code' => $code]);
            if ($taxon instanceof TaxonInterface) {
                $translation = $taxon->getTranslation(self::LOCALE);
                $translation->setName($name);
                $translation->setSlug($slug);
            }
        }
    }

    private function translateOptions(): void
    {
        foreach ($this->productOptionRepository->findAll() as $option) {
            $option->getTranslation(self::LOCALE)->setName(self::OPTIONS[$option->getCode()] ?? $option->getName());
            foreach ($option->getValues() as $value) {
                if (isset(self::OPTION_VALUES[$value->getCode()])) {
                    $value->getTranslation(self::LOCALE)->setValue(self::OPTION_VALUES[$value->getCode()]);
                }
            }
        }
    }

    private function translateProducts(): void
    {
        /** @var ProductInterface $product */
        foreach ($this->productRepository->findAll() as $product) {
            $type = self::TYPES[$product->getMainTaxon()?->getCode()] ?? 'Artikel';
            $english = $product->getTranslation('en_US');
            $translation = $product->getTranslation(self::LOCALE);

            $translation->setName($this->translateName((string) $english->getName(), $type));
            $brand = $material = null;
            foreach ($product->getAttributes() as $attribute) {
                if ($attribute->getLocaleCode() !== self::LOCALE) {
                    continue;
                }
                $code = (string) $attribute->getCode();
                if (str_ends_with($code, '_material')) {
                    $attribute->setValue($material = $this->translateMaterial((string) $attribute->getValue()));
                } elseif (str_ends_with($code, '_collection')) {
                    $attribute->setValue(strtr((string) $attribute->getValue(), self::SEASONS));
                } elseif (str_ends_with($code, '_brand')) {
                    $brand = (string) $attribute->getValue();
                }
            }

            $translation->setShortDescription(sprintf('%s von %s aus %s.', $type, $brand ?? 'Sylius', $material ?? 'hochwertigem Material'));
            $translation->setDescription($this->describe($translation->getName() ?? $type, $type, $brand, $material));
        }
    }

    private function translateName(string $name, string $type): string
    {
        return match ($type) {
            'Mütze', 'Bommelmütze' => preg_replace('/\bCap$/', $type, $name) ?? $name,
            'Kleid' => $name . ' Kleid',
            default => $name,
        };
    }

    private function translateMaterial(string $material): string
    {
        // "100% cotton" => "100 % Baumwolle"
        return preg_replace_callback('/(\d+)%\s*(\w+)/', fn (array $match) => $match[1] . ' % ' . (self::MATERIALS[strtolower($match[2])] ?? $match[2]), $material) ?? $material;
    }

    private function describe(string $name, string $type, ?string $brand, ?string $material): string
    {
        $care = match ($type) {
            'Jeans' => 'Waschen Sie die Jeans auf links bei 30 °C, damit die Farbe lange erhalten bleibt.',
            'Kleid' => 'Wir empfehlen Schonwäsche bei 30 °C und Trocknen an der Luft.',
            'Mütze', 'Bommelmütze' => 'Die Mütze ist bei 30 °C waschbar und hält auch an kalten Tagen warm.',
            default => 'Das T-Shirt ist bei 40 °C waschbar und behält seine Passform.',
        };

        return sprintf(
            "%s – %s %s von %s aus %s für jeden Tag.\n\nBequemer Schnitt, angenehm zu tragen und einfach zu kombinieren. %s",
            $name,
            in_array($type, ['Mütze', 'Bommelmütze', 'Jeans'], true) ? 'eine' : 'ein',
            $type,
            $brand ?? 'Sylius',
            $material ?? 'hochwertigem Material',
            $care,
        );
    }
}
