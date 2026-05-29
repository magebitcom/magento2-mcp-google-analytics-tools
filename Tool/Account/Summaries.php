<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpGoogleAnalyticsTools\Tool\Account;

use Google\Analytics\Admin\V1beta\ListAccountSummariesRequest;
use Magebit\Mcp\Model\Tool\Schema\Schema;
use Magebit\McpGoogleAnalyticsTools\Tool\AbstractAnalyticsTool;

/**
 * MCP tool `google_analytics.account.summaries` — lists every Google Analytics account and property
 * visible to the configured service account.
 */
class Summaries extends AbstractAnalyticsTool
{
    public const TOOL_NAME = 'google_analytics.account.summaries';
    public const ACL_RESOURCE = 'Magebit_McpGoogleAnalyticsTools::tool_google_analytics_account_summaries';

    public function getName(): string
    {
        return self::TOOL_NAME;
    }

    public function getTitle(): string
    {
        return 'List Google Analytics Account Summaries';
    }

    public function getDescription(): string
    {
        return 'List every Google Analytics account, with each account\'s property summaries (display name, '
            . 'fully-qualified resource name, property type). Call this first to discover which property_id '
            . 'values you can pass to the other tools.';
    }

    public function getInputSchema(): array
    {
        return Schema::object()->toArray();
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
        unset($arguments);
        $client = $this->clientFactory->getAdminClient();
        $request = new ListAccountSummariesRequest();
        $pagedResponse = $client->listAccountSummaries($request);

        return ['accounts' => $this->protosToList($pagedResponse->iterateAllElements())];
    }
}
