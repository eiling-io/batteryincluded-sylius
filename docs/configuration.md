---
title: Configuration
nav_order: 3
---

# Configuration
{: .no_toc }

1. TOC
{:toc}

## Channels and collections

Every channel uses one collection of the bundle. Channels without entry use the collection `default`, so a shop with
one channel needs no configuration at all.

```yaml
# config/packages/batteryincluded.yaml
batteryincluded:
    # default collection, from BATTERYINCLUDED_COLLECTION and BATTERYINCLUDED_API_KEY
    collections:
        ch:
            collection: '%env(BATTERYINCLUDED_COLLECTION_CH)%'
            api_key: '%env(BATTERYINCLUDED_API_KEY_CH)%'

eiling_io_sylius_battery_included:
    channels:              # channel code => collection
        FASHION_WEB: default
        FASHION_CH: ch
```

| Where | What happens |
|---|---|
| Shop | Search, suggestions, category pages and recommendations use the collection of the current channel |
| Export | `bi:export -c ch` exports the products of `FASHION_CH`, `bi:export` those of `FASHION_WEB` |

Rules:

- Channels may share a collection, see [Shared collections](#shared-collections).
- Without `channels` the products of the first enabled channel are exported into `default`.
- Collection names match `[a-z][a-z0-9_]*`, see [several collections](https://bundle.batteryincluded.eiling.io/configuration#several-collections).

## Shared collections

Channels selling mostly the same products can share one collection instead of maintaining one per channel:

```yaml
eiling_io_sylius_battery_included:
    channels:
        FASHION_WEB: default     # first channel: defaults of the documents
        FASHION_CH: default
```

| Where | What happens |
|---|---|
| Export | One document per product of any of the channels. The first channel of the product (in the order of `channels`) delivers price, image host and default language of `_PRODUCT`, the languages of all channels are exported to `_PRODUCT_i18n` |
| Availability | Every channel exports its status and price to `_PRODUCT_availability.<channel code>`, e.g. `fashion-ch: {active: false}` for a product not sold in `FASHION_CH` |
| Shop | Search, suggestions, sliders and recommendations request the channel as availability (`v[availability]=fashion-ch`), products which are inactive there are not shown |
| CMS pages | Pages of all channels are exported, the shop shows only the pages of its channel |

Configure the availability variable in the BatteryIncluded portal, the gateway then delivers only the scope of the
request:

```json
{"name": "availability", "default": "fashion-web", "root": ["_PRODUCT_availability"]}
```

{: .note }
The hits of other channels are hidden after the search: the number of hits, the pages and the facets still count them,
filters and sortings on `_PRODUCT.price` use the price of the first channel. Shops whose channels sell clearly different
products use one collection per channel.

## Bundle options

Everything else is configured in the bundle: base url, Symfony http client with timeouts and retries, cache of
rarely changing responses, logging and the batch size of the export.

```bash
bin/console config:dump-reference batteryincluded
```

The [configuration reference of the bundle](https://bundle.batteryincluded.eiling.io/configuration) describes every
option.

## Timeouts

Every shop page with a search, a slider or recommendations waits for the API. The plugin sets shorter curl timeouts
than the bundle (10 and 5 seconds), your configuration overrides them:

```yaml
# config/packages/batteryincluded.yaml
batteryincluded:
    curl:
        timeout: 5             # seconds, searches, suggestions and recommendations, 0 for no limit
        connect_timeout: 2     # seconds, 0 for no limit
```

The timeout also applies to the read requests of `bi:diff` and `bi:status`. Imports, updates and deletes have no
limit. With `http_client` the Symfony http client sends the requests, configure its timeouts there.

If the API is unreachable, the shop renders the pages without the results of BatteryIncluded: no products on the
search and category pages, no suggestions, sliders and recommendations. The failed request is logged to the
`batteryincluded` channel. Error responses of the API (4xx/5xx) are handled the same way.

## Plugin options

```bash
bin/console config:dump-reference eiling_io_sylius_battery_included
```

| Option | Default | Meaning |
|---|---|---|
| `channels` | `{}` | Channel code => collection name of the bundle |
| `presets` | `{}` | Collection name => preset IDs of the sliders, see [Presets](#presets) |
| `sync_changes` | `true` | Syncs products and CMS pages after they were saved or removed, see [Automatic sync](export#automatic-sync) |
| `cms_pages` | `true` | Exports and searches the pages of the Sylius CMS plugin, only if it is installed, see [CMS pages](cms-pages) |

## Presets

The [sliders](shop-features#sliders) show the products of a preset of the BatteryIncluded portal. Preset IDs belong to
a collection, so they are configured per collection:

```yaml
eiling_io_sylius_battery_included:
    presets:
        default:
            homepage: 4474bb20-a291-4ecb-9e56-59afb061864b   # slider on the homepage
            not_found: 29ca74c9-2b4e-4cda-a524-1e928c1b95bc  # 404 page and search without hits
        ch:
            homepage: …
```

A slider without preset ID is not shown.
