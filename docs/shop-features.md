---
title: Shop features
nav_order: 4
---

# Shop features
{: .no_toc }

1. TOC
{:toc}

All features use the collection of the current channel. The hits of BatteryIncluded are matched to Sylius products by
their `ordernumber`, which is the product code, so the shop renders its own product cards with prices, images and
links.

## Search page

`/{locale}/search?search=<query>` (route `eiling_io_sylius_battery_included_search`) shows the hits with facets, sorting and
pagination, 9 products per page.

| Parameter | Example | Meaning |
|---|---|---|
| `search` | `shirt` | Search term |
| `filter[<field>]` | `filter[_PRODUCT.manufacture]=Nike` | Filter of a facet |
| `filter[_PRODUCT.price][min]`, `[max]` | `filter[_PRODUCT.price][min]=10` | Price range |
| `sort` | `_PRODUCT.price:asc`, `_PRODUCT_i18n.name:asc` | Relevance (empty), price or name, ascending or descending |
| `page` | `2` | Page |

Without hits the page shows the products of the 404 preset (see [Sliders](#sliders)). With the Sylius CMS plugin the
matching pages are shown above the products, see [CMS pages](cms-pages).

## Suggestions

The plugin adds a search box to the header (hook `sylius_shop.base.header.content`). While typing, the
script of the plugin loads `/{locale}/searchAjax?search=<query>` (route `eiling_io_sylius_battery_included_search_ajax`) and
shows query completions, products and, with the Sylius CMS plugin, [pages](cms-pages) in an overlay. An empty search
box shows the highlights of the collection.

## Languages

Every search sends the locale of the URL (`/de_DE/…` => `de-de`), the gateway searches and filters the translations of
that language, see [Translations](export#translations). Facets of the other languages are hidden.

## Category pages

The product list of a taxon (route `sylius_shop_product_index`) is served by BatteryIncluded: the plugin filters by
`_PRODUCT_i18n.categories` with the path of the taxon in the language of the URL, e.g. `T-shirts > Women` or
`T-Shirts > Damen`, and shows the facets, sorting and 12
products per page. The search filters of Sylius on this page are disabled.

## Recommendations

| Page | Hook | Products per type |
|---|---|---|
| Product page | `sylius_shop.product.show.content.product_listing` | 4 |
| Cart | `sylius_shop.cart.index.content` | 6, replaces the suggested products of Sylius |
| Offcanvas cart | `sylius_shop.base.offcanvas.cart.body` | 3 |

The types are `related`, `also` and `together`, each with its own heading. Types without products are not shown.

## Sliders

| Page | Hook | Preset |
|---|---|---|
| Homepage | `sylius_shop.homepage.index` | `5ec20093-4d66-4ab7-80a3-632835e02c12` |
| 404 page and search without hits | `sylius_shop.error404.error` | `0b2fdce1-0809-450c-a522-cef26ce5a283` |

{: .note }
The preset IDs are fixed in `PresetSliderComponent` and `Error404Component`. With another collection create presets
with these IDs in the portal, or replace the sliders as described in [Extending the plugin](extending#twig-hooks).

## Translations

All texts use the domain `messages` with the prefix `eiling_io_sylius_battery_included.`, the English translations are
in `translations/messages.en.yaml` of the plugin. Override or translate them in the `translations` directory of your
shop.
