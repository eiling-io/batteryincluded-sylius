<?php

declare(strict_types=1);

namespace EilingIo\SyliusBatteryIncludedPlugin\Document;

use Batteryincluded\BatteryincludedBundle\Document\DocumentUrlResolverInterface;
use Doctrine\ORM\EntityRepository;
use EilingIo\SyliusBatteryIncludedPlugin\Dto\ContentPageDto;
use EilingIo\SyliusBatteryIncludedPlugin\Provider\ProductDataProvider;
use Sylius\CmsPlugin\Entity\PageInterface;
use Sylius\CmsPlugin\Entity\PageTranslationInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Product\Model\ProductTranslationInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Urls of the products and CMS pages in the shop for the suggest endpoint and `bi_document_url()` of the bundle, in the
 * locale and on the host of the current request.
 */
final readonly class ShopDocumentUrlResolver implements DocumentUrlResolverInterface
{
    /**
     * @param ProductRepositoryInterface<ProductInterface> $productRepository
     * @param EntityRepository<PageInterface>|null         $pageRepository    only with the Sylius CMS plugin
     */
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private LocaleContextInterface $localeContext,
        private UrlGeneratorInterface $urlGenerator,
        private ?EntityRepository $pageRepository = null,
    ) {
    }

    public function resolve(string $id, string $type, array $data): ?string
    {
        $entityId = substr($id, strlen($type) + 1);
        if (!ctype_digit($entityId)) {
            return null;
        }

        return match ($type) {
            ProductDataProvider::TYPE => $this->getProductUrl((int) $entityId),
            ContentPageDto::TYPE => $this->getPageUrl((int) $entityId),
            default => null,
        };
    }

    private function getProductUrl(int $id): ?string
    {
        $product = $this->productRepository->find($id);
        if (!$product instanceof ProductInterface) {
            return null;
        }

        $locale = $this->localeContext->getLocaleCode();
        $translation = $product->getTranslations()->get($locale);
        $slug = $translation instanceof ProductTranslationInterface ? $translation->getSlug() : null;

        return $slug !== null && $slug !== '' ? $this->urlGenerator->generate('sylius_shop_product_show', ['slug' => $slug, '_locale' => $locale]) : null;
    }

    private function getPageUrl(int $id): ?string
    {
        $page = $this->pageRepository?->find($id);
        if (!$page instanceof PageInterface) {
            return null;
        }

        $locale = $this->localeContext->getLocaleCode();
        $translation = $page->getTranslations()->get($locale);
        $slug = $translation instanceof PageTranslationInterface ? $translation->getSlug() : null;

        return $slug !== null && $slug !== '' ? $this->urlGenerator->generate('sylius_cms_shop_page_show', ['slug' => $slug, '_locale' => $locale]) : null;
    }
}
