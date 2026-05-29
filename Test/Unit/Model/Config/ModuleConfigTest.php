<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpGoogleAnalyticsTools\Test\Unit\Model\Config;

use Magebit\McpGoogleAnalyticsTools\Model\Config\ModuleConfig;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ModuleConfigTest extends TestCase
{
    private ScopeConfigInterface&MockObject $scopeConfig;
    private EncryptorInterface&MockObject $encryptor;
    private ModuleConfig $config;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->encryptor = $this->createMock(EncryptorInterface::class);
        $this->config = new ModuleConfig($this->scopeConfig, $this->encryptor);
    }

    public function testGetDefaultPropertyIdTrimsValue(): void
    {
        $this->scopeConfig->method('getValue')
            ->with(ModuleConfig::XML_PATH_DEFAULT_PROPERTY_ID)
            ->willReturn('  123456  ');
        $this->assertSame('123456', $this->config->getDefaultPropertyId());
    }

    public function testGetDefaultPropertyIdReturnsNullWhenBlank(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('');
        $this->assertNull($this->config->getDefaultPropertyId());
    }

    public function testGetOAuthClientIdTrims(): void
    {
        $this->scopeConfig->method('getValue')
            ->with(ModuleConfig::XML_PATH_OAUTH_CLIENT_ID)
            ->willReturn('  client-123.apps.googleusercontent.com  ');
        $this->assertSame('client-123.apps.googleusercontent.com', $this->config->getOAuthClientId());
    }

    public function testGetOAuthClientSecretDecrypts(): void
    {
        $this->scopeConfig->method('getValue')
            ->with(ModuleConfig::XML_PATH_OAUTH_CLIENT_SECRET)
            ->willReturn('enc-secret');
        $this->encryptor->method('decrypt')->with('enc-secret')->willReturn('plain-secret');
        $this->assertSame('plain-secret', $this->config->getOAuthClientSecret());
    }

    public function testGetOAuthRefreshTokenReturnsNullWhenBlank(): void
    {
        $this->scopeConfig->method('getValue')
            ->with(ModuleConfig::XML_PATH_OAUTH_REFRESH_TOKEN)
            ->willReturn('');
        $this->encryptor->expects($this->never())->method('decrypt');
        $this->assertNull($this->config->getOAuthRefreshToken());
    }

    public function testIsOAuthConnectedTrueWhenAllPresent(): void
    {
        $this->stubGetValues([
            ModuleConfig::XML_PATH_OAUTH_CLIENT_ID => 'cid',
            ModuleConfig::XML_PATH_OAUTH_CLIENT_SECRET => 'enc-secret',
            ModuleConfig::XML_PATH_OAUTH_REFRESH_TOKEN => 'enc-refresh',
        ]);
        $this->encryptor->method('decrypt')->willReturnCallback(
            static fn (string $v): string => 'plain-' . $v
        );
        $this->assertTrue($this->config->isOAuthConnected());
    }

    public function testIsOAuthConnectedFalseWhenRefreshTokenMissing(): void
    {
        $this->stubGetValues([
            ModuleConfig::XML_PATH_OAUTH_CLIENT_ID => 'cid',
            ModuleConfig::XML_PATH_OAUTH_CLIENT_SECRET => 'enc-secret',
            ModuleConfig::XML_PATH_OAUTH_REFRESH_TOKEN => '',
        ]);
        $this->encryptor->method('decrypt')->willReturnCallback(
            static fn (string $v): string => 'plain-' . $v
        );
        $this->assertFalse($this->config->isOAuthConnected());
    }

    /**
     * @param array<string, string> $map
     */
    private function stubGetValues(array $map): void
    {
        $this->scopeConfig->method('getValue')->willReturnCallback(
            static fn (string $path): ?string => $map[$path] ?? null
        );
    }
}
