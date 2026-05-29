<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpGoogleAnalyticsTools\Test\Unit\Model\OAuth;

use Magebit\McpGoogleAnalyticsTools\Model\Config\ModuleConfig;
use Magebit\McpGoogleAnalyticsTools\Model\OAuth\GoogleOAuthService;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GoogleOAuthServiceTest extends TestCase
{
    private ModuleConfig&MockObject $config;
    private GoogleOAuthService $service;

    protected function setUp(): void
    {
        $this->config = $this->createMock(ModuleConfig::class);
        $this->service = new GoogleOAuthService($this->config);
    }

    public function testBuildAuthUrlContainsExpectedParameters(): void
    {
        $this->config->method('getOAuthClientId')->willReturn('cid.apps.googleusercontent.com');
        $this->config->method('getOAuthClientSecret')->willReturn('secret');

        $url = $this->service->buildAuthUrl('https://shop.test/admin/magebit_mcp_ga/oauth/callback', 'state-xyz');

        $this->assertStringContainsString('accounts.google.com', $url);
        $this->assertStringContainsString('client_id=cid.apps.googleusercontent.com', urldecode($url));
        $this->assertStringContainsString('state=state-xyz', $url);
        $this->assertStringContainsString('access_type=offline', $url);
        $this->assertStringContainsString('prompt=consent', $url);
        $this->assertStringContainsString('analytics.readonly', urldecode($url));
        $this->assertStringContainsString(
            'redirect_uri=https://shop.test/admin/magebit_mcp_ga/oauth/callback',
            urldecode($url)
        );
    }

    public function testBuildAuthUrlThrowsWhenClientNotConfigured(): void
    {
        $this->config->method('getOAuthClientId')->willReturn(null);
        $this->config->method('getOAuthClientSecret')->willReturn(null);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessageMatches('/Client ID and Client Secret/');
        $this->service->buildAuthUrl('https://shop.test/callback', 'state');
    }

    public function testExtractEmailDecodesIdTokenPayload(): void
    {
        $payloadJson = json_encode(['email' => 'owner@example.com', 'sub' => '123']);
        $this->assertIsString($payloadJson);
        $idToken = $this->base64Url('{"alg":"RS256"}') . '.' . $this->base64Url($payloadJson) . '.signature';

        $this->assertSame('owner@example.com', $this->service->extractEmail(['id_token' => $idToken]));
    }

    public function testExtractEmailReturnsNullWhenNoIdToken(): void
    {
        $this->assertNull($this->service->extractEmail(['access_token' => 'x']));
    }

    public function testExtractEmailReturnsNullWhenPayloadHasNoEmail(): void
    {
        $idToken = $this->base64Url('{}') . '.' . $this->base64Url('{"sub":"123"}') . '.sig';
        $this->assertNull($this->service->extractEmail(['id_token' => $idToken]));
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
