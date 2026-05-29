<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpGoogleAnalyticsTools\Test\Unit\Model\Ga;

use Magebit\McpGoogleAnalyticsTools\Model\Config\ModuleConfig;
use Magebit\McpGoogleAnalyticsTools\Model\Ga\ClientFactory;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ClientFactoryCredentialsTest extends TestCase
{
    private ModuleConfig&MockObject $config;
    private ClientFactory $factory;

    protected function setUp(): void
    {
        $this->config = $this->createMock(ModuleConfig::class);
        $this->factory = new ClientFactory($this->config);
    }

    public function testBuildsAuthorizedUserCredentialsWhenConnected(): void
    {
        $this->config->method('isOAuthConnected')->willReturn(true);
        $this->config->method('getOAuthClientId')->willReturn('cid');
        $this->config->method('getOAuthClientSecret')->willReturn('secret');
        $this->config->method('getOAuthRefreshToken')->willReturn('refresh');

        $this->assertSame(
            [
                'type' => 'authorized_user',
                'client_id' => 'cid',
                'client_secret' => 'secret',
                'refresh_token' => 'refresh',
            ],
            $this->factory->buildCredentialsArray()
        );
    }

    public function testThrowsWhenNotConnected(): void
    {
        $this->config->method('isOAuthConnected')->willReturn(false);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessageMatches('/not connected/');
        $this->factory->buildCredentialsArray();
    }
}
