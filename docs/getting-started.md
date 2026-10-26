---
title: Getting started
nav_order: 2
---

# Getting started
{: .no_toc }

1. TOC
{:toc}

## Installation

Install the plugin with [Composer](https://getcomposer.org):

```bash
composer require eiling-io/sylius-battery-included-plugin:dev-main
```

{: .important }
The plugin and the [BatteryIncluded Symfony Bundle](https://bundle.batteryincluded.eiling.io) are not released yet.
Until then the `composer.json` of your shop needs `"minimum-stability": "dev"` and `"prefer-stable": true`.

Enable the bundle and the plugin in `config/bundles.php`, the bundle first:

```php
return [
    // ...
    Batteryincluded\BatteryincludedBundle\BatteryincludedBundle::class => ['all' => true],
    EilingIo\SyliusBatteryIncludedPlugin\EilingIoSyliusBatteryIncludedPlugin::class => ['all' => true],
];
```

## Credentials

The bundle reads the credentials of the default collection from the environment:

```dotenv
# .env.local, never commit credentials
BATTERYINCLUDED_COLLECTION=customer.my-shop
BATTERYINCLUDED_API_KEY=your-api-key
# optional, this is the default
BATTERYINCLUDED_BASE_URL=https://api.batteryincluded.io/api/v1/collections/
```

With one channel nothing else is needed. Shops with several channels assign a collection to each channel, see
[Configuration](configuration).

## Configuration, routes and assets

Import the configuration of the plugin, it registers the Twig hooks of the shop:

```yaml
# config/packages/_sylius.yaml
imports:
    # ...
    - { resource: "@EilingIoSyliusBatteryIncludedPlugin/config/config.yaml" }
```

Import the routes of the search page and the suggestions:

```yaml
# config/routes/eiling_io_batteryincluded.yaml
eiling_io_sylius_battery_included_plugin:
    resource: "@EilingIoSyliusBatteryIncludedPlugin/config/routes/shop.yaml"
    prefix: /
```

Add the script of the suggestions and the styles to the shop entrypoint and rebuild the assets:

```js
// assets/shop/entrypoint.js
import '@vendor/eiling-io/sylius-battery-included-plugin/assets/shop/entrypoint.js';
```

```bash
yarn build
bin/console cache:clear
```

## First export

Check the connection and send the products to BatteryIncluded:

```bash
bin/console bi:status            # configuration and connection of every collection
bin/console bi:export --dry-run  # loads and serializes the products, nothing is sent
bin/console bi:export --force    # full import of the default collection
```

{: .warning }
A full import replaces the collection: all documents which are not exported are deleted.

Before the shop shows results, the fields used by the plugin have to be configured in the BatteryIncluded portal, see
[Portal configuration](export#portal-configuration).

## Next steps

- [Shop features](shop-features): what changes in your shop
- [Export & sync](export): exported fields and keeping products up to date
- [Extending the plugin](extending): templates, own fields, own documents
