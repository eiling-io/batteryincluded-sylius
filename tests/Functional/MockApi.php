<?php

declare(strict_types=1);

namespace Tests\EilingIo\SyliusBatteryIncludedPlugin\Functional;

use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Response factory of the MockHttpClient of the test environment, see tests/TestApplication/config/config.yaml.
 * Every endpoint answers with the products of $productCodes and the content pages of $contentPageCodes, the filter
 * `type` restricts browse to one of them, browse with a preset delivers $presetProductCodes. Every request is recorded.
 */
final class MockApi
{
    /**
     * @var list<string> ordernumbers (Sylius product codes) delivered as hits
     */
    public static array $productCodes = [];

    /**
     * @var list<string> ordernumbers delivered by browse with a preset
     */
    public static array $presetProductCodes = [];

    /**
     * @var list<string> codes of CMS pages delivered as hits
     */
    public static array $contentPageCodes = [];

    /**
     * @var list<array<string, mixed>> facet_counts of the browse response
     */
    public static array $facets = [];

    /**
     * @var list<array{method: string, url: string, body: string}>
     */
    public static array $requests = [];

    public static function reset(): void
    {
        self::$productCodes = [];
        self::$presetProductCodes = [];
        self::$contentPageCodes = [];
        self::$facets = [];
        self::$requests = [];
    }

    /**
     * @param string ...$paths e.g. `/documents/browse`
     *
     * @return list<string> urls of the requests to these paths
     */
    public static function requestedUrls(string ...$paths): array
    {
        $urls = [];
        foreach (self::$requests as $request) {
            $path = (string) parse_url($request['url'], PHP_URL_PATH);
            foreach ($paths as $expected) {
                if (str_ends_with($path, $expected)) {
                    $urls[] = $request['url'];
                }
            }
        }

        return $urls;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function __invoke(string $method, string $url, array $options = []): MockResponse
    {
        $body = is_string($options['body'] ?? null) ? $options['body'] : '';
        self::$requests[] = ['method' => $method, 'url' => $url, 'body' => $body];

        $path = (string) parse_url($url, PHP_URL_PATH);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $types = (array) ($query['f']['type'] ?? ['PRODUCT', 'CONTENT']);

        $documents = [];
        if (in_array('PRODUCT', $types, true)) {
            foreach (($query['preset'] ?? '') !== '' ? self::$presetProductCodes : self::$productCodes as $code) {
                $documents[] = ['id' => 'PRODUCT-' . $code, 'type' => 'PRODUCT', '_PRODUCT' => ['ordernumber' => $code]];
            }
        }
        if (in_array('CONTENT', $types, true)) {
            foreach (self::$contentPageCodes as $code) {
                $documents[] = ['id' => 'CONTENT-' . $code, 'type' => 'CONTENT', '_CONTENT' => ['code' => $code]];
            }
        }

        return match (true) {
            // single documents (bi:sync, DocumentChangeQueue), every document is accepted
            str_ends_with($path, '/documents/import') => self::json(array_map(
                static fn (string $line) => ['id' => json_decode($line, true)['id'] ?? null, 'success' => true],
                array_values(array_filter(explode("\n", $body))),
            )),
            str_ends_with($path, '/documents/delete') => self::json(['num_deleted' => count((array) json_decode($body, true))]),
            str_ends_with($path, '/documents/browse') => self::json([
                'found' => count($documents),
                'out_of' => count($documents),
                'page' => 1,
                'search_time_ms' => 1,
                'hits' => array_map(static fn (array $document) => ['document' => $document], $documents),
                'facet_counts' => self::$facets,
            ]),
            str_ends_with($path, '/documents/suggest') => self::json([
                ['kind' => 'query-completion', 'hits' => []],
                ['kind' => 'document', 'found' => count($documents), 'hits' => array_map(static fn (array $document) => ['highlighted' => $document], $documents)],
            ]),
            str_ends_with($path, '/documents/recommendations'),
            str_ends_with($path, '/documents/recommendations/cart') => self::json(
                array_map(static fn (array $document) => ['type' => 'related', 'document' => $document], $documents),
            ),
            // like the gateway: query suggestions only for a locale
            str_ends_with($path, '/documents/highlights') => self::json(['searches' => [], 'querySuggestions' => match ($query['v']['locale'] ?? null) {
                'en-us' => ['Jeans in Size XL'],
                'de-de' => ['Jeans in Größe XL'],
                default => [],
            }]),
            default => new MockResponse('{"message":"not mocked"}', ['http_code' => 404]),
        };
    }

    /**
     * @param string ...$paths e.g. `/documents/import`
     *
     * @return list<string> bodies of the requests to these paths
     */
    public static function requestedBodies(string ...$paths): array
    {
        $bodies = [];
        foreach (self::$requests as $request) {
            $path = (string) parse_url($request['url'], PHP_URL_PATH);
            foreach ($paths as $expected) {
                if (str_ends_with($path, $expected)) {
                    $bodies[] = $request['body'];
                }
            }
        }

        return $bodies;
    }

    /**
     * Value of a parameter of a recorded request, e.g. `v[locale]` or `f[type]`.
     */
    public static function parameter(string $url, string $name, string $key): mixed
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query[$name][$key] ?? null;
    }

    /**
     * @param array<mixed> $body
     */
    private static function json(array $body): MockResponse
    {
        return new MockResponse((string) json_encode($body), ['response_headers' => ['content-type' => 'application/json']]);
    }
}
