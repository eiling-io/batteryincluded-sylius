# Sylius BatteryIncluded Integration

## Documentation


## Requirements:
* PHP 8.2 or higher
* Sylius 2.0 or higher

## Installation

1. Run
```bash 
composer require eiling-io/sylius-battery-included-plugin
```

2. add the env variables in .env file:
```dotenv
{$channelCode}_BATTERYINCLUDED_BASE_URL=""
{$channelCode}_BATTERYINCLUDED_COLLECTION=""
{$channelCode}_BATTERYINCLUDED_API_KEY=""

BATTERYINCLUDED_BASE_URL=""
BATTERYINCLUDED_COLLECTION=""
BATTERYINCLUDED_API_KEY=""
```

3. enable the plugin in `config/bundles.php`:

```php
<?php

return [
    // ...
    EilingIo\SyliusBatteryIncludedPlugin\EilingIoSyliusBatteryIncludedPlugin::class => ['all' => true],
];
```

4. Import config in `config/packages/_sylius.yaml`:
```yaml
# 
imports:
    # ...

  - { resource: "@EilingIoSyliusBatteryIncludedPlugin/config/config.yaml" }
    
    # ...
```

5. Import routing `config/routes/eiling_io_batteryincluded.yaml`:
```yaml
eiling_io_sylius_battery_included_plugin:
  resource: "@EilingIoSyliusBatteryIncludedPlugin/config/routes/shop.yaml"
  prefix: /
```

6. Import assets in `assets/shop/assets.yaml`:
```yaml
...
import '@vendor/eiling-io/sylius-battery-included-plugin/assets/shop/entrypoint.js';
import '@vendor/eiling-io/sylius-battery-included-plugin/assets/shop/styles.css';
```

### Contributing

1. `ddev start`
2. `ddev init`
3. Open your browser and navigate to `https://syliusbatteryincludedplugin.ddev.site/`.

