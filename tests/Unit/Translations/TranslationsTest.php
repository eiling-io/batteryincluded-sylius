<?php

declare(strict_types=1);

namespace Tests\EilingIo\SyliusBatteryIncludedPlugin\Unit\Translations;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class TranslationsTest extends TestCase
{
    private const DIRECTORY = __DIR__ . '/../../../translations';

    /**
     * @return iterable<string, array{string}>
     */
    public static function locales(): iterable
    {
        foreach (glob(self::DIRECTORY . '/messages.*.yaml') ?: [] as $file) {
            $locale = explode('.', basename($file))[1];
            if ($locale !== 'en') {
                yield $locale => [$locale];
            }
        }
    }

    #[DataProvider('locales')]
    public function testEveryLocaleHasTheKeysOfTheEnglishTranslation(string $locale): void
    {
        self::assertSame($this->keys('en'), $this->keys($locale));
    }

    /**
     * @return list<string>
     */
    private function keys(string $locale): array
    {
        $keys = $this->flatten(Yaml::parseFile(sprintf('%s/messages.%s.yaml', self::DIRECTORY, $locale)));
        sort($keys);

        return $keys;
    }

    /**
     * @param array<string, mixed> $messages
     *
     * @return list<string>
     */
    private function flatten(array $messages, string $prefix = ''): array
    {
        $keys = [];
        foreach ($messages as $key => $value) {
            $keys = [...$keys, ...(is_array($value) ? $this->flatten($value, $prefix . $key . '.') : [$prefix . $key])];
        }

        return $keys;
    }
}
