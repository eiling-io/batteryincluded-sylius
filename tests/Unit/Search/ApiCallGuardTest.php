<?php

declare(strict_types=1);

namespace Tests\EilingIo\SyliusBatteryIncludedPlugin\Unit\Search;

use BatteryIncludedSdk\Exception\NetworkException;
use EilingIo\SyliusBatteryIncludedPlugin\Search\ApiCallGuard;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

final class ApiCallGuardTest extends TestCase
{
    public function testTheResultOfTheCallIsReturned(): void
    {
        self::assertSame(['hit'], (new ApiCallGuard())->call('search', static fn () => ['hit'], []));
    }

    public function testAnErrorOfTheSdkReturnsTheFallbackAndIsLogged(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<array{level: mixed, message: string, context: array<mixed>}> */
            public array $records = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
            }
        };

        $result = (new ApiCallGuard($logger))->call('search', static fn () => throw new NetworkException('GET https://api.test/ failed: timeout'), ['fallback']);

        self::assertSame(['fallback'], $result);
        self::assertCount(1, $logger->records);
        self::assertSame('error', $logger->records[0]['level']);
        self::assertSame('search', $logger->records[0]['context']['action']);
        self::assertSame('GET https://api.test/ failed: timeout', $logger->records[0]['context']['message']);
    }

    public function testOtherExceptionsAreNotCaught(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new ApiCallGuard())->call('search', static fn () => throw new \InvalidArgumentException('Unknown collection'), []);
    }
}
