# Sylius BatteryIncluded Integration

## Documentation

https://sylius.batteryincluded.eiling.io/ – installation, configuration, shop features, export and extension points.


## Requirements:
* PHP 8.2 or higher
* Sylius 2.0 or higher

## Installation

1. Run
```bash 
composer require eiling-io/sylius-battery-included-plugin:dev-main
```

   The plugin uses `batteryincluded/batteryincluded-bundle` in `dev-main`, until it is released the `composer.json` of
   the shop needs `"minimum-stability": "dev"` and `"prefer-stable": true`.

2. enable the plugin and the [BatteryIncluded bundle](https://bundle.batteryincluded.eiling.io/) in `config/bundles.php`:

```php
<?php

return [
    // ...
    Batteryincluded\BatteryincludedBundle\BatteryincludedBundle::class => ['all' => true],
    EilingIo\SyliusBatteryIncludedPlugin\EilingIoSyliusBatteryIncludedPlugin::class => ['all' => true],
];
```

3. add the credentials of the default collection to the `.env` file:
```dotenv
BATTERYINCLUDED_COLLECTION=""
BATTERYINCLUDED_API_KEY=""
#BATTERYINCLUDED_BASE_URL="https://api.batteryincluded.io/api/v1/collections/"   # optional, this is the default
```

   All further settings (cache, logging, http client, ...) are described in the
   [bundle configuration](https://bundle.batteryincluded.eiling.io/configuration).

   With more than one channel configure one collection per channel and assign them to the channels:

```yaml
# config/packages/batteryincluded.yaml
batteryincluded:
    collections:
        ch:
            collection: '%env(BATTERYINCLUDED_COLLECTION_CH)%'
            api_key: '%env(BATTERYINCLUDED_API_KEY_CH)%'

eiling_io_sylius_battery_included:
    channels:              # channel code => collection, channels without collection use "default"
        FASHION_WEB: default
        FASHION_CH: ch
```

   Without `channels` the first enabled channel is exported into the `default` collection.

4. Import config in `config/packages/_sylius.yaml`:
```yaml
# 
imports:
    # ...

  - { resource: "@EilingIoSyliusBatteryIncludedPlugin/config/config.yaml" }
    
    # ...
```

5. Import routing `config/routes/eiling_io_batteryincluded.yaml`, the routes are prefixed with `/{_locale}`:
```yaml
eiling_io_sylius_battery_included_plugin:
  resource: "@EilingIoSyliusBatteryIncludedPlugin/config/routes.yaml"
```

6. Import assets in `assets/shop/assets.yaml`:
```yaml
...
import '@vendor/eiling-io/sylius-battery-included-plugin/assets/shop/entrypoint.js';
import '@vendor/eiling-io/sylius-battery-included-plugin/assets/shop/styles.css';
```

7. Export the products, one collection per call:
```bash
bin/console bi:export --force           # default collection
bin/console bi:export -c ch --force     # collection "ch"
bin/console bi:sync PRODUCT-5           # single products, e.g. after an update
```

   Only enabled products of the channel are exported. See the
   [console commands](https://bundle.batteryincluded.eiling.io/console) of the bundle for `bi:status`,
   `bi:search` and more.

### Contributing

1. `ddev start`
2. `ddev init`
3. Open your browser and navigate to `https://syliusbatteryincludedplugin.ddev.site/`.

### Tests

The functional tests render the shop with the Sylius fixtures, the BatteryIncluded api is mocked
(`tests/Functional/MockApi.php`). Prepare the test database once:

```bash
ddev exec 'export APP_ENV=test DATABASE_URL=mysql://root:root@db/eiling_io_sylius_battery_included_plugin_test; vendor/bin/console doctrine:database:create && vendor/bin/console doctrine:migrations:migrate -n && vendor/bin/console sylius:fixtures:load -n'
```

```bash
ddev exec vendor/bin/phpunit --testsuite unit
ddev exec 'DATABASE_URL=mysql://root:root@db/eiling_io_sylius_battery_included_plugin_test vendor/bin/phpunit'
ddev exec vendor/bin/phpstan analyse -c phpstan.neon
```

The GitHub workflow (`.github/workflows/ci.yml`) runs them for Sylius 2.2 and 2.3.

