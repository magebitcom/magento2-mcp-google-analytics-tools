<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpGoogleAnalyticsTools\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;

/**
 * Reader for every `magebit_mcp_google_analytics/*` store-config value.
 */
class ModuleConfig
{
    public const XML_PATH_DEFAULT_PROPERTY_ID = 'magebit_mcp_google_analytics/general/default_property_id';
    public const XML_PATH_OAUTH_CLIENT_ID = 'magebit_mcp_google_analytics/oauth/client_id';
    public const XML_PATH_OAUTH_CLIENT_SECRET = 'magebit_mcp_google_analytics/oauth/client_secret';
    public const XML_PATH_OAUTH_REDIRECT_BASE_URL = 'magebit_mcp_google_analytics/oauth/redirect_base_url';
    public const XML_PATH_OAUTH_REFRESH_TOKEN = 'magebit_mcp_google_analytics/oauth/refresh_token';
    public const XML_PATH_OAUTH_CONNECTED_EMAIL = 'magebit_mcp_google_analytics/oauth/connected_email';

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor,
    ) {
    }

    /**
     * Default GA4 numeric property id used when a tool call omits `property_id`.
     *
     * @return ?string
     */
    public function getDefaultPropertyId(): ?string
    {
        return $this->readNonEmptyString(self::XML_PATH_DEFAULT_PROPERTY_ID);
    }

    /**
     * OAuth Web-application client id, as entered in admin config.
     *
     * @return ?string
     */
    public function getOAuthClientId(): ?string
    {
        return $this->readNonEmptyString(self::XML_PATH_OAUTH_CLIENT_ID);
    }

    /**
     * Decrypted OAuth client secret.
     *
     * @return ?string
     */
    public function getOAuthClientSecret(): ?string
    {
        return $this->readDecryptedNonEmptyString(self::XML_PATH_OAUTH_CLIENT_SECRET);
    }

    /**
     * Optional base URL (scheme + host) used to build the OAuth redirect URI, overriding the store
     * base URL. Useful for local tunnels or when the admin is reached via a different external host.
     *
     * @return ?string
     */
    public function getOAuthRedirectBaseUrl(): ?string
    {
        $value = $this->readNonEmptyString(self::XML_PATH_OAUTH_REDIRECT_BASE_URL);
        return $value !== null ? rtrim($value, '/') : null;
    }

    /**
     * Decrypted OAuth refresh token obtained from the Connect-with-Google flow.
     *
     * @return ?string
     */
    public function getOAuthRefreshToken(): ?string
    {
        return $this->readDecryptedNonEmptyString(self::XML_PATH_OAUTH_REFRESH_TOKEN);
    }

    /**
     * Email of the connected Google account, stored for admin display only.
     *
     * @return ?string
     */
    public function getOAuthConnectedEmail(): ?string
    {
        return $this->readNonEmptyString(self::XML_PATH_OAUTH_CONNECTED_EMAIL);
    }

    /**
     * Whether a usable OAuth connection (client id + secret + refresh token) is configured.
     *
     * @return bool
     */
    public function isOAuthConnected(): bool
    {
        return $this->getOAuthClientId() !== null
            && $this->getOAuthClientSecret() !== null
            && $this->getOAuthRefreshToken() !== null;
    }

    /**
     * @param string $path
     * @return ?string
     */
    private function readNonEmptyString(string $path): ?string
    {
        $value = $this->scopeConfig->getValue($path);
        $value = is_string($value) ? trim($value) : '';
        return $value !== '' ? $value : null;
    }

    /**
     * @param string $path
     * @return ?string
     */
    private function readDecryptedNonEmptyString(string $path): ?string
    {
        $stored = $this->scopeConfig->getValue($path);
        if (!is_string($stored) || trim($stored) === '') {
            return null;
        }
        $decrypted = trim($this->encryptor->decrypt($stored));
        return $decrypted !== '' ? $decrypted : null;
    }
}
