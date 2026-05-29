# Magento2 MCP - Google Analytics Tools

Google Analytics 4 tools for the [Magebit MCP server](https://github.com/magebitcom/magento2-mcp-module). Exposes the Google Analytics Data API and Admin API as MCP tools so AI clients (Claude Desktop, ChatGPT, Gemini, Claude Code, MCP Inspector, …) can discover properties, run reports, and inspect schema metadata against your store's analytics property.

Mirrors the official [`google-analytics-mcp`](https://github.com/googleanalytics/google-analytics-mcp) server, but configured through the Magento admin and authenticated with a connected Google account (OAuth) whose refresh token is stored encrypted in `core_config_data`.

## Tools

All tools are **read-only**. Every tool accepts an optional `property_id` argument; when omitted, the *Default GA4 Property ID* from admin config is used.

| Tool | Description |
|---|---|
| `google_analytics.account.summaries` | List every account + property visible to the connected Google account. Use this first to discover property IDs. |
| `google_analytics.property.get` | Return the GA4 Property resource (display name, currency, time zone, industry, service level, …) for the given property. |
| `google_analytics.property.google_ads_links` | List the Google Ads accounts linked to a GA4 property. |
| `google_analytics.property.custom_dimensions_and_metrics` | List the custom dimensions and custom metrics defined on a property. Use before running a report to discover what custom fields are available. |
| `google_analytics.report.run` | Run a GA4 Data API Core Report (`runReport`). Supports `date_ranges`, `dimensions`, `metrics`, filters, order-bys, aggregations, currency conversion. |
| `google_analytics.report.run_realtime` | Run a GA4 Realtime Report (`runRealtimeReport`) — events in the last 30 minutes. |
| `google_analytics.report.run_funnel` | Run a GA4 Funnel Report (`runFunnelReport`, Data API v1alpha). |

Every tool surfaces errors as a soft error (`isError: true` in the MCP response) with a human-readable message, leaving the `tools/call` JSON-RPC response status as success. Common error cases:

- Not connected → "Google Analytics is not connected. Connect a Google account…"
- Property id absent and no default → "Google Analytics property_id is required."
- API quota / permission errors → "Google Analytics API error: \<original gRPC status\>."

## Installation

```bash
composer require magebitcom/magento2-mcp-google-analytics-tools
bin/magento module:enable Magebit_McpGoogleAnalyticsTools
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

The module depends on `magebitcom/magento2-mcp-module` and the official Google PHP SDKs (`google/analytics-data`, `google/analytics-admin`, `google/auth`). Composer resolves them automatically.

## Connect a Google account

The tools act as a real Google user who already has access to your GA4 property.

1. **Enable the APIs** in a Google Cloud project:
   - [Google Analytics Data API](https://console.cloud.google.com/apis/library/analyticsdata.googleapis.com)
   - [Google Analytics Admin API](https://console.cloud.google.com/apis/library/analyticsadmin.googleapis.com)
2. **Create an OAuth client.** In [*APIs & Services → Credentials*](https://console.cloud.google.com/apis/credentials), choose *Create credentials → OAuth client ID → Web application*.
3. **Register the redirect URI.** Copy the *Authorized redirect URI* shown in the Magento admin (*Stores → Configuration → Magebit → Google Analytics MCP → Google Connection*) into the OAuth client's *Authorized redirect URIs*. It looks like `https://<your-admin-host>/<admin>/magebit_mcp_ga/oauth/callback`. Google only accepts public HTTPS hosts (or `localhost`); if your store runs on a non-public host, set **Redirect Base URL** to a public tunnel host (ngrok / cloudflared) — the displayed redirect URI updates to match.
4. **Configure the consent screen — for internal use only.** Set the *User type* to **Internal** so the OAuth client is restricted to your own Google Workspace organization. 
5. **Paste client ID + secret** into the admin config and click *Save Config*.
6. **Click *Connect with Google*** and approve the consent screen with an account that has at least **Viewer** access to the GA4 property. The status flips to "Connected as you@example.com".
7. *(Optional)* Set a *Default GA4 Property ID* — the numeric id (e.g. `123456789`) used when a tool call doesn't specify one. Discover IDs at runtime via `google_analytics.account.summaries`.

The refresh token is stored encrypted at rest. Use *Disconnect* to revoke it from the store.

## Granting tool access

Tool calls go through the standard Magebit MCP token + ACL flow:

1. Edit (or create) the admin role that owns your MCP token under *System → Permissions → User Roles*.
2. Under *Role Resources*, scroll to *Stores → Settings → Configuration → Magebit → MCP → MCP Tools* and tick the tool ACLs you want this token to use (each tool has its own resource, e.g. *Tool: Run Google Analytics Report*).
3. Save. The next tool call from that token will see the tools in `tools/list` and be allowed to call them.

The admin *Google Analytics MCP Configuration* permission gates access to the connection settings — keep it scoped to people who should manage the Google connection.

## Quick example

```jsonc
// JSON-RPC body for POST /mcp
{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "tools/call",
  "params": {
    "name": "google_analytics.report.run",
    "arguments": {
      "date_ranges": [{ "start_date": "7daysAgo", "end_date": "today" }],
      "dimensions": [{ "name": "country" }],
      "metrics": [{ "name": "activeUsers" }, { "name": "sessions" }],
      "order_bys": [{ "metric": { "metric_name": "activeUsers" }, "desc": true }],
      "limit": 10
    }
  }
}
```

## License

MIT — see [`LICENSE`](LICENSE).
