<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpGoogleAnalyticsTools\Model\OAuth;

use Magebit\McpGoogleAnalyticsTools\Model\Config\ModuleConfig;
use Magento\Framework\App\Cache\Type\Config as ConfigCache;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Encryption\EncryptorInterface;

/**
 * Persists and clears the OAuth refresh token (encrypted) and connected-account email written by the callback flow.
 */
class GoogleOAuthStorage
{
    /**
     * @param WriterInterface $configWriter
     * @param EncryptorInterface $encryptor
     * @param TypeListInterface $cacheTypeList
     */
    public function __construct(
        private readonly WriterInterface $configWriter,
        private readonly EncryptorInterface $encryptor,
        private readonly TypeListInterface $cacheTypeList
    ) {
    }

    /**
     * Stores the refresh token (encrypted) and connected email, then invalidates the config cache.
     *
     * @param string $refreshToken
     * @param ?string $email
     * @return void
     */
    public function store(string $refreshToken, ?string $email): void
    {
        $this->configWriter->save(
            ModuleConfig::XML_PATH_OAUTH_REFRESH_TOKEN,
            $this->encryptor->encrypt($refreshToken)
        );
        $this->configWriter->save(
            ModuleConfig::XML_PATH_OAUTH_CONNECTED_EMAIL,
            $email ?? ''
        );
        $this->invalidateConfigCache();
    }

    /**
     * Clears the stored refresh token and connected email, then invalidates the config cache.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->configWriter->delete(ModuleConfig::XML_PATH_OAUTH_REFRESH_TOKEN);
        $this->configWriter->delete(ModuleConfig::XML_PATH_OAUTH_CONNECTED_EMAIL);
        $this->invalidateConfigCache();
    }

    /**
     * @return void
     */
    private function invalidateConfigCache(): void
    {
        // cleanType() (not invalidate()) forces the config cache to be rebuilt from the DB on the
        // next request — invalidate() only flags it stale while still serving the old snapshot.
        $this->cacheTypeList->cleanType(ConfigCache::TYPE_IDENTIFIER);
    }
}
