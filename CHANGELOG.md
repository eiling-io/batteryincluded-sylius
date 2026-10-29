# Changelog

All notable changes of the plugin. Changes up to here are listed in the git history only.

## Unreleased

Needs batteryincluded/batteryincluded-php-sdk 1.0.0 (`ce6f51c` or newer) and the current `dev-main` of
batteryincluded/batteryincluded-bundle (`964f98e` or newer), update both packages together:
`composer update batteryincluded/*`.

### Upgrade

No reimport is needed: the plugin never set `_PRODUCT.id`, the types `PRODUCT` and `CONTENT` are upper case already.

### Added

- Channels can share a collection: every product of the channels is exported once, the first channel of the
  product delivers the defaults, every channel exports status and price to `_PRODUCT_availability.<channel code>`.
  The shop requests its channel as availability and hides the products which are inactive there. See
  `ChannelCollectionResolver::resolveAvailability()` and `ProductHits`.
- Shop pages are rendered without the results of BatteryIncluded if the API is unreachable (timeout, connection
  error): search and category pages without products, no suggestions, sliders and recommendations. The error is
  logged to the `batteryincluded` channel, see `ApiCallGuard`.
- Shorter curl timeouts for the shop: `batteryincluded.curl.timeout` 5 seconds and `connect_timeout` 2 seconds instead
  of 10 and 5 seconds of the bundle, the configuration of the project overrides them.

### Breaking

- The data providers are registered per collection: `ProductDataProvider` and `CmsPageDataProvider` take the
  channels of the collection as `$channelCodes` (list) instead of `$channelCode`. `SearchStructFactory` takes the
  `ChannelCollectionResolver`, the recommendation components take it as well.
- `BatteryIncludedBaseController`, `SearchController`, `TaxonController`, `PresetProductFinder` and the recommendation
  components take the `ApiCallGuard` as new constructor argument. Autowired services need no change, own subclasses
  and decorations have to pass it.

### Changed

- The facets of other locales (`_PRODUCT_i18n.en-us.*`) are skipped by `BrowseResponse::getFacets()` of the SDK, the
  own filter of the plugin is removed.
- `bi:export`, `bi:sync`, `bi:delete` and `bi:diff` handle every exception of the SDK, a network error stops a full
  import before the commit. The dry run of `bi:export` reports a product that cannot be encoded with its id.
- Products that cannot be encoded (e.g. a price of `NAN`) are not retried by the messenger, invalid UTF-8 in product
  data is replaced instead of sending an empty document.
- Every request sends a `User-Agent` with the version of the SDK and of PHP.
