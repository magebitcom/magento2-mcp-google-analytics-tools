<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpGoogleAnalyticsTools\Tool\Report;

use Google\Analytics\Data\V1beta\RunRealtimeReportRequest;
use Magebit\Mcp\Model\Tool\Schema\Builder\ArrayBuilder;
use Magebit\Mcp\Model\Tool\Schema\Builder\IntegerBuilder;
use Magebit\Mcp\Model\Tool\Schema\Builder\ObjectBuilder;
use Magebit\Mcp\Model\Tool\Schema\Builder\StringBuilder;
use Magebit\Mcp\Model\Tool\Schema\Schema;
use Magebit\McpGoogleAnalyticsTools\Tool\AbstractAnalyticsTool;

/**
 * MCP tool `google_analytics.report.run_realtime` — runs a GA4 Realtime Report (events in the last 30 minutes).
 */
class RunRealtime extends AbstractAnalyticsTool
{
    public const TOOL_NAME = 'google_analytics.report.run_realtime';
    public const ACL_RESOURCE = 'Magebit_McpGoogleAnalyticsTools::tool_google_analytics_report_run_realtime';

    public function getName(): string
    {
        return self::TOOL_NAME;
    }

    public function getTitle(): string
    {
        return 'Run Realtime Google Analytics Report';
    }

    public function getDescription(): string
    {
        return 'Run a Google Analytics 4 Realtime Report — current activity across the last 30 minutes. '
            . 'Supports a subset of dimensions and metrics relative to Core reports. See '
            . 'https://developers.google.com/analytics/devguides/reporting/data/v1/rest/v1beta/properties/runRealtimeReport.';
    }

    public function getInputSchema(): array
    {
        $schema = Schema::object();
        $schema = $this->withPropertyIdField($schema);
        return $schema
            ->array('dimensions', fn (ArrayBuilder $a) => $a
                ->ofObjects(fn (ObjectBuilder $o) => $o
                    ->string('name', fn (StringBuilder $s) => $s
                        ->required()
                        ->description('Realtime-eligible dimension API name (e.g. "country", "deviceCategory").')))
                ->description('Dimensions to group by.'))
            ->array('metrics', fn (ArrayBuilder $a) => $a
                ->ofObjects(fn (ObjectBuilder $o) => $o
                    ->string('name', fn (StringBuilder $s) => $s
                        ->required()
                        ->description('Realtime-eligible metric API name (e.g. "activeUsers", "screenPageViews").'))
                    ->string('expression', fn (StringBuilder $s) => $s
                        ->description('Optional arithmetic expression for a derived metric.')))
                ->description('Metrics to aggregate.'))
            ->rawProperty('dimension_filter', [
                'type' => 'object',
                'description' => 'GA4 FilterExpression for dimensions. Same shape as Core reports.',
            ])
            ->rawProperty('metric_filter', [
                'type' => 'object',
                'description' => 'GA4 FilterExpression for metrics.',
            ])
            ->rawProperty('order_bys', [
                'type' => 'array',
                'description' => 'GA4 OrderBy entries.',
                'items' => ['type' => 'object'],
            ])
            ->integer('limit', fn (IntegerBuilder $i) => $i
                ->minimum(1)
                ->maximum(100000)
                ->description('Max rows.'))
            ->array('metric_aggregations', fn (ArrayBuilder $a) => $a
                ->ofStrings(fn (StringBuilder $s) => $s
                    ->enum(['TOTAL', 'MINIMUM', 'MAXIMUM', 'COUNT'])))
            ->array('minute_ranges', fn (ArrayBuilder $a) => $a
                ->ofObjects(fn (ObjectBuilder $o) => $o
                    ->integer('start_minutes_ago', fn (IntegerBuilder $i) => $i
                        ->minimum(0)
                        ->maximum(29)
                        ->description('Inclusive start, 0–29 minutes ago. Defaults to 29.'))
                    ->integer('end_minutes_ago', fn (IntegerBuilder $i) => $i
                        ->minimum(0)
                        ->maximum(29)
                        ->description('Inclusive end, 0–29 minutes ago. Defaults to 0.'))
                    ->string('name', fn (StringBuilder $s) => $s
                        ->description('Optional label echoed back in each row.')))
                ->description('Up to two minute ranges to compare; omit for the default last-30-minutes window.'))
            ->boolean('return_property_quota', fn ($b) => $b
                ->description('Include the property\'s remaining realtime quota in the response.'))
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

        $request = new RunRealtimeReportRequest();
        $this->mergeArguments($request, $arguments);
        // Set the resolved property AFTER the merge so a caller-supplied `property` cannot override it.
        $request->setProperty($property);

        $response = $this->clientFactory->getDataClient()->runRealtimeReport($request);
        return $this->protoToArray($response);
    }

    /**
     * @inheritDoc
     */
    protected function buildAuditSummary(array $payload): array
    {
        $rowCount = $payload['rowCount'] ?? $payload['row_count'] ?? null;
        if (is_numeric($rowCount)) {
            return ['row_count' => (int) $rowCount];
        }
        $rows = $payload['rows'] ?? null;
        return ['row_count' => is_array($rows) ? count($rows) : 0];
    }
}
