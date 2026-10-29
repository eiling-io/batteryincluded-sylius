<?php

declare(strict_types=1);

namespace Tests\EilingIo\SyliusBatteryIncludedPlugin\Unit\Channel;

use EilingIo\SyliusBatteryIncludedPlugin\Channel\ChannelCollectionResolver;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;

final class ChannelCollectionResolverTest extends TestCase
{
    public function testMappedChannelUsesItsCollection(): void
    {
        $resolver = new ChannelCollectionResolver($this->channelContext('FASHION_WEB'), ['FASHION_WEB' => 'web', 'FASHION_CH' => 'ch']);

        self::assertSame('ch', $resolver->resolve('FASHION_CH'));
    }

    public function testUnmappedChannelUsesDefaultCollection(): void
    {
        $resolver = new ChannelCollectionResolver($this->channelContext('FASHION_WEB'), ['FASHION_CH' => 'ch']);

        self::assertSame('default', $resolver->resolve('OTHER'));
        self::assertSame('default', $resolver->resolve());
    }

    public function testWithoutChannelCodeTheCurrentChannelIsUsed(): void
    {
        $resolver = new ChannelCollectionResolver($this->channelContext('FASHION_CH'), ['FASHION_CH' => 'ch']);

        self::assertSame('ch', $resolver->resolve());
    }

    public function testChannelsSharingACollectionRequestTheirAvailability(): void
    {
        $resolver = new ChannelCollectionResolver($this->channelContext('FASHION_CH'), ['FASHION_WEB' => 'default', 'FASHION_CH' => 'default', 'FASHION_AT' => 'at']);

        self::assertSame('FASHION_CH', $resolver->resolveAvailability());
        self::assertSame('FASHION_WEB', $resolver->resolveAvailability('FASHION_WEB'));
    }

    public function testAChannelWithItsOwnCollectionRequestsNoAvailability(): void
    {
        $resolver = new ChannelCollectionResolver($this->channelContext('FASHION_AT'), ['FASHION_WEB' => 'default', 'FASHION_CH' => 'default', 'FASHION_AT' => 'at']);

        self::assertNull($resolver->resolveAvailability());
        self::assertNull($resolver->resolveAvailability('OTHER'));
        self::assertNull((new ChannelCollectionResolver($this->channelContext('FASHION_WEB')))->resolveAvailability());
    }

    private function channelContext(string $code): ChannelContextInterface
    {
        $channel = $this->createStub(ChannelInterface::class);
        $channel->method('getCode')->willReturn($code);
        $channelContext = $this->createStub(ChannelContextInterface::class);
        $channelContext->method('getChannel')->willReturn($channel);

        return $channelContext;
    }
}
