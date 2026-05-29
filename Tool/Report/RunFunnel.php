<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpGoogleAnalyticsTools\Tool\Report;

use Google\Analytics\Data\V1alpha\RunFunnelReportRequest;
use Magebit\Mcp\Model\Tool\Schema\Builder\ArrayBuilder;
use Magebit\Mcp\Model\Tool\Schema\Builder\IntegerBuilder;
use Magebit\Mcp\Model\Tool\Schema\Builder\ObjectBuilder;
use Magebit\Mcp\Model\Tool\Schema\Builder\StringBuilder;
use Magebit\Mcp\Model\Tool\Schema\Schema;
use Magebit\McpGoogleAnalyticsTools\Tool\AbstractAnalyticsTool;

/**
 * MCP tool `google_analytics.report.run_funnel` — runs a GA4 Data API v1alpha Funnel Report.
 */
class RunFunnel extends AbstractAnalyticsTool
{
    public const TOOL_NAME = 'google_analytics.report.run_funnel';
    public const ACL_RESOURCE = 'Magebit_McpGoogleAnalyticsTools::tool_google_analytics_report_run_funnel';

    public function getName(): string
    {
        return self::TOOL_NAME;
    }

    public function getTitle(): string
    {
        return 'Run Google Analytics Funnel Report';
    }

    public function getDescription(): string
    {
        return 'Run a Google Analytics 4 Funnel Report (Data API v1alpha). Define an ordered sequence of '
            . '`funnel.steps`, each with a filterExpression, and the report returns per-step user counts plus '
            . 'optional breakdown/next-action dimensions. See '
            . 'https://developers.google.com/analytics/devguides/reporting/data/v1/rest/v1alpha/properties/runFunnelReport. '
            . 'This is an alpha API — request shape may change.';
    }

    public function getInputSchema(): array
    {
        $schema = Schema::object();
        $schema = $this->withPropertyIdField($schema);
        return $schema
            ->array('date_ranges', fn (ArrayBuilder $a) => $a
                ->ofObjects(fn (ObjectBuilder $o) => $o
                    ->string('start_date', fn (StringBuilder $s) => $s->required())
                    ->string('end_date', fn (StringBuilder $s) => $s->required())
                    ->string('name', fn (StringBuilder $s) => $s))
                ->minItems(1)
                ->description('One or more date ranges. Required.'))
            ->rawProperty('funnel', [
                'type' => 'object',
                'description' => 'Funnel definition. Must include `steps` (each step has `name`, `filterExpression`, '
                    . 'optional `isDirectlyFollowedBy`, `withinDurationFromPriorStep`). Optionally `isOpenFunnel`.',
            ], required: true)
            ->rawProperty('funnel_breakdown', [
                'type' => 'object',
                'description' => 'Optional `breakdownDimension` to break each step\'s users down by.',
            ])
            ->rawProperty('funnel_next_action', [
                'type' => 'object',
                'description' => 'Optional `nextActionDimension` to compute the next dimension value after each step.',
            ])
            ->string('funnel_visualization_type', fn (StringBuilder $s) => $s
                ->enum(['STANDARD_FUNNEL', 'TRENDED_FUNNEL'])
                ->description('Defaults to STANDARD_FUNNEL.'))
            ->rawProperty('segments', [
                'type' => 'array',
                'items' => ['type' => 'object'],
                'description' => 'Optional list of segments to evaluate the funnel for each.',
            ])
            ->rawProperty('dimension_filter', [
                'type' => 'object',
                'description' => 'GA4 FilterExpression applied to events before the funnel evaluates.',
            ])
            ->integer('limit', fn (IntegerBuilder $i) => $i
                ->minimum(1)
                ->maximum(100000))
            ->boolean('return_property_quota', fn ($b) => $b)
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

        $request = new RunFunnelReportRequest();
        $this->mergeArguments($request, $arguments);
        // Set the resolved property AFTER the merge so a caller-supplied `property` cannot override it.
        $request->setProperty($property);

        $response = $this->clientFactory->getAlphaDataClient()->runFunnelReport($request);
        return $this->protoToArray($response);
    }

    /**
     * @inheritDoc
     */
    protected function buildAuditSummary(array $payload): array
    {
        $table = $payload['funnelTable'] ?? $payload['funnel_table'] ?? null;
        $rows = is_array($table) ? ($table['rows'] ?? null) : null;
        return ['funnel_table_row_count' => is_array($rows) ? count($rows) : 0];
    }
}
