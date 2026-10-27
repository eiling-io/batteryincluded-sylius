---
title: Development
nav_order: 8
---

# Development
{: .no_toc }

1. TOC
{:toc}

Contributions are welcome. This page describes the local setup and the checks a pull request has to pass.

## Setup

The plugin runs in the Sylius test application (`vendor/sylius/test-application`) with [ddev](https://ddev.com):

```bash
ddev start
ddev init     # database, migrations, fixtures, frontend build
```

The shop is available at `https://syliusbatteryincludedplugin.ddev.site/`. Put the credentials of your collection into
`tests/TestApplication/.env.local`, it is not committed:

```dotenv
BATTERYINCLUDED_COLLECTION=customer.my-shop
BATTERYINCLUDED_API_KEY=your-api-key
```

Export the fixtures with `ddev exec vendor/bin/console bi:export --force`.

## Tests

The unit tests need no database. The functional tests render the shop pages and run the console commands with the
Sylius fixtures, the API is answered by `tests/Functional/MockApi.php`, no credentials needed. Prepare the test database
once:

```bash
ddev exec 'export APP_ENV=test DATABASE_URL=mysql://root:root@db/eiling_io_sylius_battery_included_plugin_test; vendor/bin/console doctrine:database:create && vendor/bin/console doctrine:migrations:migrate -n && vendor/bin/console sylius:fixtures:load -n && vendor/bin/console sylius:fixtures:load batteryincluded_cms -n'
```

The test application enables the [Sylius CMS plugin](cms-pages) if it is installed (`require-dev`), the suite
`batteryincluded_cms` loads a few pages. The tests of the CMS pages are skipped without it. The test environment
stores its images in `media/image-test`, loading its fixtures does not delete the images of the dev shop.

```bash
ddev exec vendor/bin/phpunit --testsuite unit
ddev exec 'DATABASE_URL=mysql://root:root@db/eiling_io_sylius_battery_included_plugin_test vendor/bin/phpunit'
```

## Checks

```bash
ddev exec vendor/bin/phpstan analyse -c phpstan.neon
ddev exec 'APP_ENV=test vendor/bin/console lint:container'
ddev exec vendor/bin/console lint:twig templates
```

## Continuous integration

`.github/workflows/ci.yml` runs on every push and pull request against `main`:

| Sylius | PHP | CMS plugin |
|---|---|---|
| 2.2 | 8.2, 8.4 | yes |
| 2.3 | 8.3, 8.4 | yes |
| 2.3 | 8.4 | no |

Every job installs the dependencies for its Sylius version, validates `composer.json`, lints container, configuration
and templates, runs PHPStan (with the CMS plugin), the unit tests, loads the fixtures into MySQL and runs the
functional tests. The tests run
on Symfony 7.4 and the latest `dev-main` of the bundle and the SDK.

## Documentation

This documentation is built by GitHub Pages from the `docs` directory of `main` with the
[just-the-docs](https://just-the-docs.com) theme. Keep `llms.txt` and `llms-full.txt` up to date when a page changes.
