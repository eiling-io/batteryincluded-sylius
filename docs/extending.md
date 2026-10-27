---
title: Extending the plugin
nav_order: 7
---

# Extending the plugin
{: .no_toc }

1. TOC
{:toc}

The plugin covers the shop, the [Symfony Bundle](https://bundle.batteryincluded.eiling.io) everything around the export
and the API. Most extensions use the extension points of the bundle:

| You want to | Use | Documentation |
|---|---|---|
| Change the look of a page | Template override | [Templates](#templates) |
| Move, replace or remove a block | Twig hooks | [Twig hooks](#twig-hooks) |
| Add or change fields of the products | `DocumentsEvent` | [Own fields](#own-fields), [events of the bundle](https://bundle.batteryincluded.eiling.io/events) |
| Export content pages, blog posts, stores | Own data provider | [Own documents](#own-documents), [own document types](https://bundle.batteryincluded.eiling.io/export#own-document-types) |
| Stop or monitor the export | `PreExportEvent`, `PostExportEvent` | [Events & extension points](https://bundle.batteryincluded.eiling.io/events) |
| Sync products after saving | `DocumentSynchronizer`, Messenger | [Single documents](https://bundle.batteryincluded.eiling.io/single-documents) |
| Search, suggest, recommend in own code | SDK services | [Services](#services), [SDK](https://sdk.batteryincluded.eiling.io) |
| Cache, retries, logging of API calls | Bundle configuration | [Configuration of the bundle](https://bundle.batteryincluded.eiling.io/configuration) |

## Templates

Override a template by placing a file with the same path below `templates/bundles/EilingIoSyliusBatteryIncludedPlugin/`
of your shop:

| Template | Content |
|---|---|
| `shop/header/_searchbox.html.twig` | Search box and suggestion overlay in the header |
| `shop/search/search.html.twig` | Search page with facets and pagination |
| `shop/search/search_ajax.html.twig` | Content of the suggestion overlay |
| `shop/search/_content_pages.html.twig` | [CMS pages](cms-pages) on the search page |
| `shop/taxon/index.html.twig`, `shop/taxon/browse.html.twig` | Category page and its product grid |
| `shop/_includes/_sort_bar.html.twig` | Sorting of search and category pages |
| `shop/product/recommendation.html.twig` | Recommendations on the product page |
| `shop/cart/recommendation.html.twig`, `shop/cart/offcanvas_recommendation.html.twig` | Recommendations in the cart |
| `shop/index/preset_slider.html.twig` | Slider on the homepage |
| `shop/error/404_products.html.twig` | Slider on the 404 page |

```text
templates/bundles/EilingIoSyliusBatteryIncludedPlugin/shop/search/search.html.twig
```

## Twig hooks

Every block is registered as a [Twig hook](https://stack.sylius.com/twig-hooks/getting-started) in
`config/config.yaml` of the plugin. Disable, move or replace it in the configuration of your shop:

```yaml
# config/packages/sylius_twig_hooks.yaml
sylius_twig_hooks:
    hooks:
        'sylius_shop.homepage.index':
            eiling_io_preset_slider:
                enabled: false          # removes the slider of the homepage
        'sylius_shop.product.show.content.product_listing':
            eiling_io_product_recommendation:
                priority: -100          # recommendations below the other blocks
```

| Hookable | Hook | Component |
|---|---|---|
| `eiling_io_searchbox` | `sylius_shop.base.header.content` | – |
| `eiling_io_product_recommendation` | `sylius_shop.product.show.content.product_listing` | `EilingIo:ProductRecommendation` |
| `eiling_io_cart_recommendation` | `sylius_shop.cart.index.content` | `EilingIo:CartRecommendation` |
| `eiling_io_offcanvas_cart_recommendation` | `sylius_shop.base.offcanvas.cart.body` | `EilingIo:OffcanvasCartRecommendation` |
| `eiling_io_preset_slider` | `sylius_shop.homepage.index` | `EilingIo:PresetSlider` |
| `eiling_io_error404_products` | `sylius_shop.error404.error` | `EilingIo:Error404` |

To show another preset, disable the slider and add an own component that browses with
`BrowseSearchStruct::setPresetId()`, see [Services](#services).

## Own fields

The `DocumentsEvent` of the bundle contains every batch of documents before it is sent, both for `bi:export` and for
single documents. Change the documents of the plugin there, e.g. the manufacturer from your own attribute:

```php
use Batteryincluded\BatteryincludedBundle\Event\DocumentsEvent;
use BatteryIncludedSdk\Dto\ProductBaseDto;
use EilingIo\SyliusBatteryIncludedPlugin\Provider\ProductDataProvider;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final class ProductDocumentListener
{
    public function __construct(private readonly ProductRepositoryInterface $productRepository)
    {
    }

    #[AsEventListener]
    public function __invoke(DocumentsEvent $event): void
    {
        // the products of the plugin, document id PRODUCT-<product id>
        $documents = [];
        foreach ($event->getDocuments() as $document) {
            if ($document instanceof ProductBaseDto && $document->getType() === ProductDataProvider::TYPE) {
                $documents[(int) substr($document->getIdentifier(), strlen(ProductDataProvider::TYPE . '-'))] = $document;
            }
        }

        // one query per batch
        foreach ($this->productRepository->findBy(['id' => array_keys($documents)]) as $product) {
            $brand = $product->getAttributeByCodeAndLocale('brand', 'en_US');
            $documents[$product->getId()]->setManufacture($brand !== null ? (string) $brand->getValue() : null);
        }
    }
}
```

{: .important }
Check the type of the documents, not `$event->provider`: single documents (`bi:sync`, `DocumentSynchronizer`,
Messenger) are sent without provider. `bi:sync --dry-run` prints the documents before the event, the changes of your
listener are only in the documents which are sent.

The event can also remove documents (`setDocuments()`), e.g. products which should not be found.

{: .note }
`ProductBaseDto` covers the standard fields. Fields of your own need an own DTO class
([custom fields of the SDK](https://sdk.batteryincluded.eiling.io/syncing#custom-fields)) and therefore an own
provider, see below. Fields the shop filters by have to be filterable in the portal.

## Own documents

Content pages, blog posts or stores are exported by an own data provider. The [CMS pages](cms-pages) integration of
the plugin is a complete example: `CmsPageDataProvider` with `ContentPageDto` for the export, `CmsPageFinder` for the
shop. Implement
`CollectionAwareDataProviderInterface` of the bundle so the documents go only into the collection of their channel,
and `PartialDataProviderInterface` for `bi:sync`. The [export documentation](https://bundle.batteryincluded.eiling.io/export#data-providers)
of the bundle shows a complete provider.

To replace the product export of the plugin, decorate the provider of a collection. Its service id is
`eiling_io_sylius_battery_included.product_data_provider.<collection>`, e.g.
`eiling_io_sylius_battery_included.product_data_provider.default`.

## Services

`ServiceFactory` returns the SDK services for the collection of the current channel, or of the channel passed:

```php
use BatteryIncludedSdk\Shop\BrowseSearchStruct;
use EilingIo\SyliusBatteryIncludedPlugin\Factory\ServiceFactory;

public function __construct(private readonly ServiceFactory $serviceFactory)
{
}

public function sale(): array
{
    $searchStruct = new BrowseSearchStruct();
    $searchStruct->setPresetId('my-preset-id');

    return $this->serviceFactory->getBrowseService()->browse($searchStruct)->getHits();
}
```

| Method | SDK service |
|---|---|
| `getBrowseService()` | Search and category pages, presets |
| `getSuggestService()` | Suggestions |
| `getHighlightsService()` | Highlights of an empty search |
| `getRecommendationsService()` | Recommendations of a product |
| `getCartRecommendationsService()` | Recommendations of a cart |
| `getSyncService()` | Low level import and delete, prefer the commands and the `DocumentSynchronizer` |

`ChannelCollectionResolver::resolve()` returns the name of the collection of a channel, e.g. for the
`CollectionRegistry` or the `DocumentSynchronizer` of the bundle. The [SDK documentation](https://sdk.batteryincluded.eiling.io)
describes the search structs and responses.
