<?php

declare(strict_types=1);

namespace EilingIo\SyliusBatteryIncludedPlugin\Search;

use BatteryIncludedSdk\Shop\BrowseSearchStruct;
use BatteryIncludedSdk\Suggest\SuggestSearchStruct;
use EilingIo\SyliusBatteryIncludedPlugin\Channel\ChannelCollectionResolver;
use Sylius\Component\Locale\Context\LocaleContextInterface;

/**
 * Search structs for the locale of the shop, the gateway delivers the translations of `_PRODUCT_i18n` and
 * `_CONTENT_i18n` for it. Without locale the structs of the SDK request `DE`. Channels sharing a collection request
 * their availability, see ChannelCollectionResolver::resolveAvailability().
 */
final readonly class SearchStructFactory
{
    /**
     * Translated fields are filtered, sorted and faceted without locale, the gateway adds it.
     */
    public const CATEGORY_FIELD = '_PRODUCT_i18n.categories';

    public const NAME_FIELD = '_PRODUCT_i18n.name';

    public function __construct(
        private LocaleContextInterface $localeContext,
        private ChannelCollectionResolver $channelCollectionResolver,
    ) {
    }

    public function createBrowseSearchStruct(): BrowseSearchStruct
    {
        return (new BrowseSearchStruct())
            ->setLocale($this->localeContext->getLocaleCode())
            ->setAvailability($this->channelCollectionResolver->resolveAvailability());
    }

    public function createSuggestSearchStruct(): SuggestSearchStruct
    {
        return (new SuggestSearchStruct())
            ->setLocale($this->localeContext->getLocaleCode())
            ->setAvailability($this->channelCollectionResolver->resolveAvailability());
    }
}
