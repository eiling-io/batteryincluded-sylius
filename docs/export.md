---
title: Export & sync
nav_order: 5
---

# Export & sync
{: .no_toc }

1. TOC
{:toc}

The plugin registers one `ProductDataProvider` per configured channel. The commands and services of the bundle use it
like every other [data provider](https://bundle.batteryincluded.eiling.io/export#data-providers).

## Exported products

Only enabled products assigned to the channel are exported. Every product becomes a document of the type `PRODUCT`
with the id `PRODUCT-<product id>`:

| Field | Source |
|---|---|
| `name`, `description` | Translation of the product, translated |
| `ordernumber` | Product code, used by the shop to find the product of a hit |
| `price` | Channel pricing of the first variant |
| `instock` | On hand minus on hold of the first variant |
| `imageUrl` | First image, filter `sylius_shop_product_thumbnail`, host name of the channel |
| `shopUrl` | Route `sylius_shop_product_show` in the locale, translated |
| `rating` | Average rating |
| `categories` | Path of every taxon without children, e.g. `T-shirts > Women`, translated |
| `properties` | Option values of all variants, every value once, e.g. `t_shirt_size: [S, M, L]`, translated |
| `manufacture` | Attribute `cap_brand`, `t_shirt_brand`, `jeans_brand` or `dress_brand`, translated |

To add or change fields, see [Own fields](extending#own-fields).

## Translations

The default locale of the channel fills `_PRODUCT`, every locale of the channel is exported to `_PRODUCT_i18n` with the
translated fields of the table above (locales normalized, `de_DE` => `de-de`):

```json
{
    "id": "PRODUCT-215",
    "type": "PRODUCT",
    "_PRODUCT_i18n": {
        "en-us": {"name": "Sunshine Strappy Delight", "categories": ["Dresses"], "properties": [{"dress_height": ["Petite", "Regular", "Tall"]}]},
        "de-de": {"name": "Sunshine Strappy Delight Kleid", "categories": ["Kleider"], "properties": [{"dress_height": ["Kurz", "Normal", "Lang"]}]}
    },
    "_PRODUCT": {"name": "Sunshine Strappy Delight", "ordernumber": "Sunshine_Strappy_Delight", "price": 95.43}
}
```

Missing translations fall back to Sylius' default. The [translations of the SDK](https://sdk.batteryincluded.eiling.io/translations)
explain how the gateway resolves them.

## Full import

```bash
bin/console bi:export --dry-run   # loads and serializes the products, nothing is sent
bin/console bi:export --force     # default collection
bin/console bi:export -c ch -f    # collection "ch", i.e. the channel assigned to it
```

{: .warning }
A full import replaces the collection: all documents which are not exported are deleted. It stops before the commit if
the API rejects a document, the collection is then unchanged.

Run it after deployments or regularly by cron. The [export documentation](https://bundle.batteryincluded.eiling.io/export)
of the bundle explains the transaction, the lock and all options.

## Single products

```bash
bin/console bi:sync PRODUCT-5 PRODUCT-6                  # creates or replaces the documents
bin/console bi:sync PRODUCT-5 --delete-missing           # deletes it if the product is disabled or removed from the channel
bin/console bi:sync PRODUCT-5 --dry-run                  # prints the document
bin/console bi:delete PRODUCT-5 --force
```

From your code use the `DocumentSynchronizer` or dispatch a `SyncDocumentsMessage`, e.g. after a product was saved in
the admin, see [single documents](https://bundle.batteryincluded.eiling.io/single-documents).

## Automatic sync

Products and CMS pages are synced as soon as they are saved, in the admin, by the API or by your own code. The
`DocumentChangeListener` of the plugin watches Doctrine and hands the changed documents to the
[`DocumentChangeQueue`](https://bundle.batteryincluded.eiling.io/single-documents#documentchangequeue) of the bundle,
which sends them at the end of the request:

| Change | Result |
|---|---|
| Product, its translations, variants, prices, images, taxons and attributes | `PRODUCT-<id>` is synced into every collection of the plugin |
| Product disabled or removed from a channel | Deleted from the collections whose channel does not deliver it anymore (`deleteMissing`) |
| Product removed | Deleted from every collection |
| CMS page, its translations and content elements | `CONTENT-<id>`, the same way |

With symfony/messenger the queue dispatches `SyncDocumentsMessage`s: route them to an async transport in production,
see [Messenger](https://bundle.batteryincluded.eiling.io/single-documents#messenger). Errors are logged to the
`batteryincluded` channel and never break the admin.

{: .note }
Changes which affect many products are not tracked: taxon names, option values, attributes of a product type. Run
`bi:export` after such changes and regularly, e.g. with the [scheduler of the bundle](https://bundle.batteryincluded.eiling.io/export#scheduler).

{: .warning }
Loading fixtures (`sylius:fixtures:load`) syncs every product of the fixtures into the configured collection. Disable
the sync with `sync_changes: false` in environments which must not change the collection.

## Portal configuration

The shop needs these fields in the BatteryIncluded portal, the
[backend configuration of the SDK](https://sdk.batteryincluded.eiling.io/backend-configuration) shows where to set them:

| Setting | Fields |
|---|---|
| Variable `locale` | `{"name": "locale", "default": "en-us", "root": ["_PRODUCT_i18n", "_CONTENT_i18n"]}`, see [collection setup](https://sdk.batteryincluded.eiling.io/translations#collection-setup) |
| Searchable | `_PRODUCT_i18n.<locale>.name` and `.description` of every locale |
| Filterable | `_PRODUCT_i18n.<locale>.categories` (category pages) and `.properties.*` of every locale, `_PRODUCT.price` (price range), `type` with CMS pages |
| Sortable | `_PRODUCT.price`, `_PRODUCT_i18n.<locale>.name`; sorting by string fields like the name is always activated by the BatteryIncluded support |
| Suggest | `_PRODUCT.ordernumber`, otherwise the suggestions cannot be matched to products |
| Presets | The presets of the [sliders](shop-features#sliders), their IDs go into the [configuration](configuration#presets) |
