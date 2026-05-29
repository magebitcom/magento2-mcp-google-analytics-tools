<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpGoogleAnalyticsTools\Test\Unit\Model\Ga;

use Magebit\McpGoogleAnalyticsTools\Model\Config\ModuleConfig;
use Magebit\McpGoogleAnalyticsTools\Model\Ga\PropertyResolver;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PropertyResolverTest extends TestCase
{
    private ModuleConfig&MockObject $config;
    private PropertyResolver $resolver;

    protected function setUp(): void
    {
        $this->config = $this->createMock(ModuleConfig::class);
        $this->resolver = new PropertyResolver($this->config);
    }

    public function testExplicitStringArgumentWins(): void
    {
        $this->config->expects($this->never())->method('getDefaultPropertyId');
        $this->assertSame('properties/123', $this->resolver->resolve('123'));
    }

    public function testExplicitIntArgumentWins(): void
    {
        $this->assertSame('properties/456', $this->resolver->resolve(456));
    }

    public function testFullyQualifiedNameStripsPrefix(): void
    {
        $this->assertSame('properties/789', $this->resolver->resolve('properties/789'));
    }

    public function testFallsBackToConfigWhenNull(): void
    {
        $this->config->method('getDefaultPropertyId')->willReturn('555');
        $this->assertSame('properties/555', $this->resolver->resolve(null));
    }

    public function testFallsBackToConfigWhenBlankString(): void
    {
        $this->config->method('getDefaultPropertyId')->willReturn('555');
        $this->assertSame('properties/555', $this->resolver->resolve('   '));
    }

    public function testThrowsWhenMissingArgumentAndConfig(): void
    {
        $this->config->method('getDefaultPropertyId')->willReturn(null);
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessageMatches('/property_id is required/');
        $this->resolver->resolve(null);
    }

    public function testThrowsOnNonNumericValue(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessageMatches('/must be numeric/');
        $this->resolver->resolve('abc');
    }

    public function testThrowsOnUnsupportedArgumentType(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessageMatches('/must be a string or integer/');
        $this->resolver->resolve(['nope']);
    }

    public function testResolveIdReturnsBareValue(): void
    {
        $this->assertSame('999', $this->resolver->resolveId('properties/999'));
    }
}
