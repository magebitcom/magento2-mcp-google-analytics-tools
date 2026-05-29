<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpGoogleAnalyticsTools\Test\Unit\Model\OAuth;

use Magebit\McpGoogleAnalyticsTools\Model\Config\ModuleConfig;
use Magebit\McpGoogleAnalyticsTools\Model\OAuth\CallbackUrlProvider;
use Magento\Backend\Model\UrlInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CallbackUrlProviderTest extends TestCase
{
    private UrlInterface&MockObject $backendUrl;
    private ModuleConfig&MockObject $config;
    private CallbackUrlProvider $provider;

    protected function setUp(): void
    {
        $this->backendUrl = $this->createMock(UrlInterface::class);
        $this->config = $this->createMock(ModuleConfig::class);
        $this->provider = new CallbackUrlProvider($this->backendUrl, $this->config);
    }

    public function testReturnsStoreUrlWhenNoOverride(): void
    {
        $this->backendUrl->method('getUrl')
            ->willReturn('https://mcp-module.docker/admin/magebit_mcp_ga/oauth/callback');
        $this->config->method('getOAuthRedirectBaseUrl')->willReturn(null);

        $this->assertSame(
            'https://mcp-module.docker/admin/magebit_mcp_ga/oauth/callback',
            $this->provider->get()
        );
    }

    public function testSwapsHostKeepingPathWhenOverridden(): void
    {
        $this->backendUrl->method('getUrl')
            ->willReturn('https://mcp-module.docker/admin/magebit_mcp_ga/oauth/callback');
        // ModuleConfig already strips a trailing slash; mock returns the normalized form.
        $this->config->method('getOAuthRedirectBaseUrl')->willReturn('https://abc123.trycloudflare.com');

        $this->assertSame(
            'https://abc123.trycloudflare.com/admin/magebit_mcp_ga/oauth/callback',
            $this->provider->get()
        );
    }
}
