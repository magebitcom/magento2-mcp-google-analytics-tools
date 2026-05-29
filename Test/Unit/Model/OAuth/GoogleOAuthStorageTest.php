<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpGoogleAnalyticsTools\Test\Unit\Model\OAuth;

use Magebit\McpGoogleAnalyticsTools\Model\Config\ModuleConfig;
use Magebit\McpGoogleAnalyticsTools\Model\OAuth\GoogleOAuthStorage;
use Magento\Framework\App\Cache\Type\Config as ConfigCache;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GoogleOAuthStorageTest extends TestCase
{
    private WriterInterface&MockObject $writer;
    private EncryptorInterface&MockObject $encryptor;
    private TypeListInterface&MockObject $cacheTypeList;
    private GoogleOAuthStorage $storage;

    protected function setUp(): void
    {
        $this->writer = $this->createMock(WriterInterface::class);
        $this->encryptor = $this->createMock(EncryptorInterface::class);
        $this->cacheTypeList = $this->createMock(TypeListInterface::class);
        $this->storage = new GoogleOAuthStorage($this->writer, $this->encryptor, $this->cacheTypeList);
    }

    public function testStoreEncryptsRefreshTokenAndSavesEmail(): void
    {
        $this->encryptor->method('encrypt')->with('refresh-abc')->willReturn('enc-refresh');

        $saved = [];
        $this->writer->method('save')->willReturnCallback(
            function (string $path, string $value) use (&$saved): void {
                $saved[$path] = $value;
            }
        );
        $this->cacheTypeList->expects($this->once())->method('cleanType')->with(ConfigCache::TYPE_IDENTIFIER);

        $this->storage->store('refresh-abc', 'owner@example.com');

        $this->assertSame('enc-refresh', $saved[ModuleConfig::XML_PATH_OAUTH_REFRESH_TOKEN]);
        $this->assertSame('owner@example.com', $saved[ModuleConfig::XML_PATH_OAUTH_CONNECTED_EMAIL]);
    }

    public function testStoreWritesEmptyEmailWhenNull(): void
    {
        $this->encryptor->method('encrypt')->willReturn('enc');

        $saved = [];
        $this->writer->method('save')->willReturnCallback(
            function (string $path, string $value) use (&$saved): void {
                $saved[$path] = $value;
            }
        );

        $this->storage->store('refresh', null);

        $this->assertSame('', $saved[ModuleConfig::XML_PATH_OAUTH_CONNECTED_EMAIL]);
    }

    public function testClearDeletesBothPathsAndInvalidatesCache(): void
    {
        $deleted = [];
        $this->writer->method('delete')->willReturnCallback(
            function (string $path) use (&$deleted): void {
                $deleted[] = $path;
            }
        );
        $this->cacheTypeList->expects($this->once())->method('cleanType')->with(ConfigCache::TYPE_IDENTIFIER);

        $this->storage->clear();

        $this->assertContains(ModuleConfig::XML_PATH_OAUTH_REFRESH_TOKEN, $deleted);
        $this->assertContains(ModuleConfig::XML_PATH_OAUTH_CONNECTED_EMAIL, $deleted);
    }
}
