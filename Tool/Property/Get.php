<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpGoogleAnalyticsTools\Tool\Property;

use Google\Analytics\Admin\V1beta\GetPropertyRequest;
use Magebit\Mcp\Model\Tool\Schema\Schema;
use Magebit\McpGoogleAnalyticsTools\Tool\AbstractAnalyticsTool;

/**
 * MCP tool `google_analytics.property.get` — returns the GA4 Property resource for the configured or supplied id.
 */
class Get extends AbstractAnalyticsTool
{
    public const TOOL_NAME = 'google_analytics.property.get';
    public const ACL_RESOURCE = 'Magebit_McpGoogleAnalyticsTools::tool_google_analytics_property_get';

    public function getName(): string
    {
        return self::TOOL_NAME;
    }

    public function getTitle(): string
    {
        return 'Get GA4 Property Details';
    }

    public function getDescription(): string
    {
        return 'Return the GA4 Property resource (display name, currency, time zone, industry category, '
            . 'service level, create time, etc.) for a property. Use after `google_analytics.account.summaries` '
            . 'to inspect a specific property before running reports.';
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
        $name = $this->propertyResolver->resolve($arguments['property_id'] ?? null);
        $request = new GetPropertyRequest(['name' => $name]);
        $property = $this->clientFactory->getAdminClient()->getProperty($request);
        return $this->protoToArray($property);
    }
}
