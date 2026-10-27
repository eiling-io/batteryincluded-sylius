---
title: CMS pages
nav_order: 6
---

# CMS pages
{: .no_toc }

1. TOC
{:toc}

With the [Sylius CMS plugin](https://github.com/Sylius/CmsPlugin) installed, its pages are exported to BatteryIncluded
and found by the search: shipping, returns, size guides, FAQ. Without the CMS plugin nothing changes.

## Activation

The integration switches itself on when the bundle `Sylius\CmsPlugin\SyliusCmsPlugin` is enabled in
`config/bundles.php`, there is nothing to configure. To keep the pages out of the search although the CMS plugin is
installed:

```yaml
# config/packages/eiling_io_sylius_battery_included.yaml
eiling_io_sylius_battery_included:
    cms_pages: false
```

## Exported pages

Every enabled and published page (`publish_at` empty or in the past) assigned to the channel becomes a document of the
type `CONTENT` with the id `CONTENT-<page id>`, in the collection of the channel like the products:

| Field | Source |
|---|---|
| `code` | Code of the page, used by the shop to find the page of a hit |
| `title` | Title of the translation, otherwise the name of the page |
| `teaser` | Teaser content, without markup |
| `content` | Text of the `heading` and `textarea` content elements of the locale, without markup |
| `url` | Route `sylius_cms_shop_page_show` |

The default locale of the channel fills `_CONTENT`, every translation is exported to `_CONTENT_i18n`. Other content
elements (media, product carousels, …) are not searchable.

```bash
bin/console bi:export --dry-run    # lists ProductDataProvider and CmsPageDataProvider
bin/console bi:sync CONTENT-3      # a single page
```

{: .note }
Pages with a future `publish_at` are exported by the first export after that date, run `bi:export` regularly.

## In the shop

| Where | What |
|---|---|
| Suggestions | Section "Pages" below the products, linked to the page |
| Search page | Up to 4 matching pages above the products of the first page |
| Product search, category pages | Filtered by `type` = `PRODUCT`, so pages do not take the place of products |

Like the products, the shop loads the pages of the hits from the CMS plugin, only enabled and published pages of the
current channel are shown. The block on the search page is the template `shop/search/_content_pages.html.twig`, see
[Templates](extending#templates).

## Portal configuration

In addition to the [fields of the products](export#portal-configuration):

| Setting | Fields |
|---|---|
| Filterable | `type`, the product search is filtered by it |
| Searchable | `_CONTENT.title`, `_CONTENT.teaser`, `_CONTENT.content` |
| Suggest | `_CONTENT.title` and `_CONTENT.code`, otherwise the suggestions cannot be matched to pages |

{: .important }
Make `type` filterable before you install the CMS plugin: from then on the product search, the category pages and the
search page filter by it.

## Other CMS

The integration is an example of an [own document type](extending#own-documents). For another CMS write a data
provider like `CmsPageDataProvider` with the `ContentPageDto` of the plugin, the shop templates then need the pages of
your CMS instead of the `CmsPageFinder`.
