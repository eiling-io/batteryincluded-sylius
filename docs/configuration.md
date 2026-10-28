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

- A collection can only be assigned to one channel, the configuration is rejected otherwise.
- Without `channels` the products of the first enabled channel are exported into `default`.
- Collection names match `[a-z][a-z0-9_]*`, see [several collections](https://bundle.batteryincluded.eiling.io/configuration#several-collections).

## Bundle options

Everything else is configured in the bundle: base url, Symfony http client with timeouts and retries, cache of
rarely changing responses, logging and the batch size of the export.

```bash
bin/console config:dump-reference batteryincluded
```

The [configuration reference of the bundle](https://bundle.batteryincluded.eiling.io/configuration) describes every
option.

## Plugin options

```bash
bin/console config:dump-reference eiling_io_sylius_battery_included
```

| Option | Default | Meaning |
|---|---|---|
| `channels` | `{}` | Channel code => collection name of the bundle |
| `presets` | `{}` | Collection name => preset IDs of the sliders, see [Presets](#presets) |
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
