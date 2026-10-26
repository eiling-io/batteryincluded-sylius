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
| `name`, `description` | Translation of the product |
| `ordernumber` | Product code, used by the shop to find the product of a hit |
| `price` | Channel pricing of the first variant |
| `instock` | On hand minus on hold of the first variant |
| `imageUrl` | First image, filter `sylius_shop_product_thumbnail`, host name of the channel |
| `shopUrl` | Route `sylius_shop_product_show` |
| `rating` | Average rating |
| `categories` | Path of every taxon without children, e.g. `T-shirts > Women` |
| `properties` | Option values of all variants, e.g. `t_shirt_size: [S, M, L]` |
| `manufacture` | Attribute `cap_brand`, `t_shirt_brand`, `jeans_brand` or `dress_brand` (`de_DE`) |

To add or change fields, see [Own fields](extending#own-fields).

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

{: .note }
The plugin does not sync products automatically when they are changed in the admin yet, run `bi:export` regularly or
call the `DocumentSynchronizer` from a listener of the Sylius resource events.

## Portal configuration

The shop needs these fields in the BatteryIncluded portal, the
[backend configuration of the SDK](https://sdk.batteryincluded.eiling.io/backend-configuration) shows where to set them:

| Setting | Fields |
|---|---|
| Filterable | `_PRODUCT.categories` (category pages), `_PRODUCT.price` (price range) and every field shown as facet |
| Sortable | `_PRODUCT.price`, `_PRODUCT.name` |
| Suggest | `_PRODUCT.ordernumber`, otherwise the suggestions cannot be matched to products |
| Presets | The presets of the [sliders](shop-features#sliders) |
