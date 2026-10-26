---
title: Home
layout: home
nav_order: 1
---

# BatteryIncluded Sylius Plugin

Brings [BatteryIncluded](https://batteryincluded.ai) into your Sylius 2 shop: a search page with filters and sorting,
search-as-you-type suggestions in the header, category pages served by BatteryIncluded and product recommendations on
the product page and in the cart. The plugin is built on the
[BatteryIncluded Symfony Bundle](https://bundle.batteryincluded.eiling.io), which exports your catalog and talks to
the API.

```bash
composer require eiling-io/sylius-battery-included-plugin:dev-main
bin/console bi:export --force
```

## What's inside

| Topic | Content |
|---|---|
| [Getting started](getting-started) | Installation, credentials, routes, assets, first export |
| [Configuration](configuration) | Channels and collections, all options of the bundle |
| [Shop features](shop-features) | Search page, suggestions, category pages, recommendations, sliders |
| [Export & sync](export) | Exported fields, `bi:export`, `bi:sync`, portal configuration |
| [Extending the plugin](extending) | Templates, Twig hooks, own fields and documents, services |
| [Development](development) | ddev, tests, static analysis, CI |

## How it fits together

| Package | Responsibility |
|---|---|
| **Sylius Plugin** (this one) | Products of a channel as documents, shop pages, Twig components, channel to collection mapping |
| [Symfony Bundle](https://bundle.batteryincluded.eiling.io) | Configuration, collections, console commands, events, profiler |
| [PHP SDK](https://sdk.batteryincluded.eiling.io) | API client, DTOs, search structs and responses |

Whatever the plugin does not cover itself is configured and extended through the bundle, see
[Extending the plugin](extending).

## For AI coding agents

Let your agent read [`llms-full.txt`](llms-full.txt): installation, configuration, exported fields and extension points
in one file. [`llms.txt`](llms.txt) lists all pages of this documentation.

## Requirements

- PHP 8.2 or higher (8.3 with Sylius 2.3)
- Sylius 2.2 or 2.3, Symfony 7.4
- A BatteryIncluded collection and its API key
