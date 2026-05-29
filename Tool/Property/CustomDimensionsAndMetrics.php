<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpGoogleAnalyticsTools\Tool\Property;

use Google\Analytics\Admin\V1beta\ListCustomDimensionsRequest;
use Google\Analytics\Admin\V1beta\ListCustomMetricsRequest;
use Magebit\Mcp\Model\Tool\Schema\Schema;
use Magebit\McpGoogleAnalyticsTools\Tool\AbstractAnalyticsTool;

/**
 * MCP tool `google_analytics.property.custom_dimensions_and_metrics` — lists custom dimensions and metrics defined on a GA4 property.
 */
class CustomDimensionsAndMetrics extends AbstractAnalyticsTool
{
    public const TOOL_NAME = 'google_analytics.property.custom_dimensions_and_metrics';
    public const ACL_RESOURCE = 'Magebit_McpGoogleAnalyticsTools::tool_google_analytics_property_custom_dimensions_and_metrics';

    public function getName(): string
    {
        return self::TOOL_NAME;
    }

    public function getTitle(): string
    {
        return 'List Custom Dimensions and Metrics';
    }

    public function getDescription(): string
    {
        return 'List the custom dimensions and custom metrics defined on a GA4 property (parameter name, scope, '
            . 'display name, description, measurement unit). Use this before running a report to discover what '
            . 'custom fields are available for inclusion in `dimensions` / `metrics`.';
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
        $admin = $this->clientFactory->getAdminClient();

        $dimensionResponse = $admin->listCustomDimensions(new ListCustomDimensionsRequest(['parent' => $parent]));
        $metricResponse = $admin->listCustomMetrics(new ListCustomMetricsRequest(['parent' => $parent]));

        return [
            'custom_dimensions' => $this->protosToList($dimensionResponse->iterateAllElements()),
            'custom_metrics' => $this->protosToList($metricResponse->iterateAllElements()),
        ];
    }

    /**
     * @inheritDoc
     */
    protected function buildAuditSummary(array $payload): array
    {
        $dimensions = $payload['custom_dimensions'] ?? [];
        $metrics = $payload['custom_metrics'] ?? [];
        return [
            'custom_dimensions_count' => is_array($dimensions) ? count($dimensions) : 0,
            'custom_metrics_count' => is_array($metrics) ? count($metrics) : 0,
        ];
    }
}
