<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpGoogleAnalyticsTools\Tool\Report;

use Google\Analytics\Data\V1beta\RunReportRequest;
use Magebit\Mcp\Model\Tool\Schema\Builder\ArrayBuilder;
use Magebit\Mcp\Model\Tool\Schema\Builder\IntegerBuilder;
use Magebit\Mcp\Model\Tool\Schema\Builder\ObjectBuilder;
use Magebit\Mcp\Model\Tool\Schema\Builder\StringBuilder;
use Magebit\Mcp\Model\Tool\Schema\Schema;
use Magebit\McpGoogleAnalyticsTools\Tool\AbstractAnalyticsTool;

/**
 * MCP tool `google_analytics.report.run` — runs a GA4 Data API Core Report.
 */
class Run extends AbstractAnalyticsTool
{
    public const TOOL_NAME = 'google_analytics.report.run';
    public const ACL_RESOURCE = 'Magebit_McpGoogleAnalyticsTools::tool_google_analytics_report_run';

    public function getName(): string
    {
        return self::TOOL_NAME;
    }

    public function getTitle(): string
    {
        return 'Run Google Analytics Report';
    }

    public function getDescription(): string
    {
        return 'Run a Google Analytics 4 Core Report against the configured property. Returns rows of '
            . 'dimension/metric values for the requested date ranges. Use `google_analytics.account.summaries` '
            . 'to discover properties and `google_analytics.property.custom_dimensions_and_metrics` to discover '
            . 'custom field names. Filters, order-bys, and metric aggregations follow the GA4 Data API v1beta '
            . 'request shape — see https://developers.google.com/analytics/devguides/reporting/data/v1/rest/v1beta/properties/runReport.';
    }

    public function getInputSchema(): array
    {
        $schema = Schema::object();
        $schema = $this->withPropertyIdField($schema);
        return $schema
            ->array('date_ranges', fn (ArrayBuilder $a) => $a
                ->ofObjects(fn (ObjectBuilder $o) => $o
                    ->string('start_date', fn (StringBuilder $s) => $s
                        ->required()
                        ->description('Inclusive start date. YYYY-MM-DD, or relative ("today", "yesterday", "NdaysAgo").'))
                    ->string('end_date', fn (StringBuilder $s) => $s
                        ->required()
                        ->description('Inclusive end date. YYYY-MM-DD, or relative ("today", "yesterday", "NdaysAgo").'))
                    ->string('name', fn (StringBuilder $s) => $s
                        ->description('Optional label echoed back as `dateRange` in each row.')))
                ->minItems(1)
                ->description('One or more date ranges. Required.'))
            ->array('dimensions', fn (ArrayBuilder $a) => $a
                ->ofObjects(fn (ObjectBuilder $o) => $o
                    ->string('name', fn (StringBuilder $s) => $s
                        ->required()
                        ->description('GA4 dimension API name (e.g. "country", "deviceCategory", "customEvent:plan").')))
                ->description('Dimensions to group by.'))
            ->array('metrics', fn (ArrayBuilder $a) => $a
                ->ofObjects(fn (ObjectBuilder $o) => $o
                    ->string('name', fn (StringBuilder $s) => $s
                        ->required()
                        ->description('GA4 metric API name (e.g. "activeUsers", "sessions", "purchaseRevenue").'))
                    ->string('expression', fn (StringBuilder $s) => $s
                        ->description('Optional arithmetic expression for a derived metric.')))
                ->description('Metrics to aggregate.'))
            ->rawProperty('dimension_filter', [
                'type' => 'object',
                'description' => 'GA4 FilterExpression for dimensions (filter / andGroup / orGroup / notExpression). '
                    . 'See https://developers.google.com/analytics/devguides/reporting/data/v1/rest/v1beta/FilterExpression.',
            ])
            ->rawProperty('metric_filter', [
                'type' => 'object',
                'description' => 'GA4 FilterExpression for metrics. Same shape as `dimension_filter`.',
            ])
            ->rawProperty('order_bys', [
                'type' => 'array',
                'description' => 'GA4 OrderBy entries (metric / dimension / pivot, plus `desc`). '
                    . 'See https://developers.google.com/analytics/devguides/reporting/data/v1/rest/v1beta/OrderBy.',
                'items' => ['type' => 'object'],
            ])
            ->integer('limit', fn (IntegerBuilder $i) => $i
                ->minimum(1)
                ->maximum(100000)
                ->description('Max rows. GA4 caps at 100,000.'))
            ->integer('offset', fn (IntegerBuilder $i) => $i
                ->minimum(0)
                ->description('Row offset for pagination.'))
            ->array('metric_aggregations', fn (ArrayBuilder $a) => $a
                ->ofStrings(fn (StringBuilder $s) => $s
                    ->enum(['TOTAL', 'MINIMUM', 'MAXIMUM', 'COUNT']))
                ->description('Aggregations to compute alongside the row data.'))
            ->string('currency_code', fn (StringBuilder $s) => $s
                ->pattern('^[A-Z]{3}$')
                ->description('ISO 4217 currency code to convert revenue metrics into.'))
            ->boolean('keep_empty_rows', fn ($b) => $b
                ->description('Return rows where every metric is zero. Defaults to false.'))
            ->boolean('return_property_quota', fn ($b) => $b
                ->description('Include the property\'s remaining quota in the response.'))
            ->toArray();
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
        $property = $this->propertyResolver->resolve($arguments['property_id'] ?? null);

        $request = new RunReportRequest();
        $this->mergeArguments($request, $arguments);
        // Set the resolved property AFTER the merge so a caller-supplied `property` cannot override it.
        $request->setProperty($property);

        $response = $this->clientFactory->getDataClient()->runReport($request);
        return $this->protoToArray($response);
    }

    /**
     * @inheritDoc
     */
    protected function buildAuditSummary(array $payload): array
    {
        $rowCount = $payload['rowCount'] ?? $payload['row_count'] ?? null;
        return ['row_count' => is_numeric($rowCount) ? (int) $rowCount : 0];
    }
}
