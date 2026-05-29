<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpGoogleAnalyticsTools\Tool\Property;

use Google\Analytics\Admin\V1beta\ListGoogleAdsLinksRequest;
use Magebit\Mcp\Model\Tool\Schema\Schema;
use Magebit\McpGoogleAnalyticsTools\Tool\AbstractAnalyticsTool;

/**
 * MCP tool `google_analytics.property.google_ads_links` — lists Google Ads accounts linked to a GA4 property.
 */
class GoogleAdsLinks extends AbstractAnalyticsTool
{
    public const TOOL_NAME = 'google_analytics.property.google_ads_links';
    public const ACL_RESOURCE = 'Magebit_McpGoogleAnalyticsTools::tool_google_analytics_property_google_ads_links';

    public function getName(): string
    {
        return self::TOOL_NAME;
    }

    public function getTitle(): string
    {
        return 'List Google Ads Links';
    }

    public function getDescription(): string
    {
        return 'List the Google Ads accounts linked to a GA4 property (customer id, link state, ads personalization '
            . 'preference, creator email). Use to check campaign attribution coverage before running reports that '
            . 'reference Google Ads dimensions or metrics.';
    }

    public function getInputSchema(): array
    {
        return $this->withPropertyIdField(Schema::object())->toArray();
    }

    public function getAclResource(): string
    {
        return self::ACL_RESOURCE;
    }

    /**
     * @inheritDoc
     */
    protected function executeChecked(array $arguments): array
    {
        $parent = $this->propertyResolver->resolve($arguments['property_id'] ?? null);
        $request = new ListGoogleAdsLinksRequest(['parent' => $parent]);
        $pagedResponse = $this->clientFactory->getAdminClient()->listGoogleAdsLinks($request);

        return ['google_ads_links' => $this->protosToList($pagedResponse->iterateAllElements())];
    }
}
