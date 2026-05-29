<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpGoogleAnalyticsTools\Model\Ga;

use Google\Analytics\Admin\V1beta\Client\AnalyticsAdminServiceClient;
use Google\Analytics\Data\V1alpha\Client\AlphaAnalyticsDataClient;
use Google\Analytics\Data\V1beta\Client\BetaAnalyticsDataClient;
use Magebit\McpGoogleAnalyticsTools\Model\Config\ModuleConfig;
use Magento\Framework\Exception\LocalizedException;

/**
 * Lazily constructs Google Analytics Data/Admin clients with credentials sourced from admin config.
 */
class ClientFactory
{
    private ?BetaAnalyticsDataClient $dataClient = null;
    private ?AlphaAnalyticsDataClient $alphaDataClient = null;
    private ?AnalyticsAdminServiceClient $adminClient = null;

    /**
     * @param ModuleConfig $config
     */
    public function __construct(
        private readonly ModuleConfig $config
    ) {
    }

    /**
     * @return BetaAnalyticsDataClient
     * @throws LocalizedException
     */
    public function getDataClient(): BetaAnalyticsDataClient
    {
        if ($this->dataClient === null) {
            $this->dataClient = new BetaAnalyticsDataClient($this->clientOptions());
        }
        return $this->dataClient;
    }

    /**
     * @return AlphaAnalyticsDataClient
     * @throws LocalizedException
     */
    public function getAlphaDataClient(): AlphaAnalyticsDataClient
    {
        if ($this->alphaDataClient === null) {
            $this->alphaDataClient = new AlphaAnalyticsDataClient($this->clientOptions());
        }
        return $this->alphaDataClient;
    }

    /**
     * @return AnalyticsAdminServiceClient
     * @throws LocalizedException
     */
    public function getAdminClient(): AnalyticsAdminServiceClient
    {
        if ($this->adminClient === null) {
            $this->adminClient = new AnalyticsAdminServiceClient($this->clientOptions());
        }
        return $this->adminClient;
    }

    /**
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    private function clientOptions(): array
    {
        return [
            'credentials' => $this->buildCredentialsArray(),
            'transport' => 'rest',
        ];
    }

    /**
     * Builds the authorized_user credentials array to hand the Google Auth library.
     *
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    public function buildCredentialsArray(): array
    {
        if (!$this->config->isOAuthConnected()) {
            throw new LocalizedException(__(
                'Google Analytics is not connected. Connect a Google account under '
                . 'Stores → Configuration → Magebit → Google Analytics MCP.'
            ));
        }

        return [
            'type' => 'authorized_user',
            'client_id' => $this->config->getOAuthClientId(),
            'client_secret' => $this->config->getOAuthClientSecret(),
            'refresh_token' => $this->config->getOAuthRefreshToken(),
        ];
    }
}
