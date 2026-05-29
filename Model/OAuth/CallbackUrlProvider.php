<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpGoogleAnalyticsTools\Model\OAuth;

use Magebit\McpGoogleAnalyticsTools\Model\Config\ModuleConfig;
use Magento\Backend\Model\UrlInterface;

class CallbackUrlProvider
{
    private const ROUTE_PATH = 'magebit_mcp_ga/oauth/callback';

    /**
     * @param UrlInterface $backendUrl
     * @param ModuleConfig $config
     */
    public function __construct(
        private readonly UrlInterface $backendUrl,
        private readonly ModuleConfig $config
    ) {
    }

    /**
     * @return string
     */
    public function get(): string
    {
        $default = $this->backendUrl->getUrl(
            self::ROUTE_PATH,
            ['_nosecret' => true, '_secure' => true]
        );

        $override = $this->config->getOAuthRedirectBaseUrl();
        if ($override === null) {
            return $default;
        }

        $path = parse_url($default, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = '/' . self::ROUTE_PATH;
        }
        return $override . $path;
    }
}
