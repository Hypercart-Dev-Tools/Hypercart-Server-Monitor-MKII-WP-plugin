# Cron Health API (External Monitoring)

This plugin exposes a small public REST endpoint you can poll from external monitoring tools to confirm that WP-Cron is running.

## Endpoint

- Path: `/wp-json/cron-health/v1/status`
- Method: `GET`
- Auth: none required (public, read-only)

Example full URL:

```text
https://your-site.com/wp-json/cron-health/v1/status
```

## Example Response

```json
{
  "status": "healthy",
  "last_run": "2026-01-25T14:23:03+00:00"
}
```

- `status` is usually `healthy` or `unhealthy`, but may also be `rate_limited` when per-IP rate limits are exceeded.
- `last_run` is an ISO-8601 UTC timestamp, or `null` if it has never run.

## How `healthy` Is Determined

The endpoint returns `healthy` only when all of the following are true:

- A scheduled run has executed at least once (`last_run` exists).
- The next cron run is currently scheduled in WordPress.
- A health transient exists and has not expired.

Important detail: the health transient is updated only during scheduled runs (not manual tests).

## Quick Tests

### curl

```bash
curl -sS https://your-site.com/wp-json/cron-health/v1/status
```

### Bash health check

This exits non-zero when status is not `healthy` **or** when the endpoint returns a non-2xx HTTP status (including rate limiting).

```bash
#!/usr/bin/env bash
set -euo pipefail

URL="https://your-site.com/wp-json/cron-health/v1/status"

status="$(curl -fsS "$URL" | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["status"] ?? "";')"

if [[ "$status" != "healthy" ]]; then
  echo "CRON UNHEALTHY: $status"
  exit 1
fi

echo "CRON HEALTHY"
```

## Monitoring Recommendations

- Poll every 5 to 15 minutes.
- Alert when `status != healthy` for 2 or more consecutive checks.
- Expect `unhealthy` briefly after plugin activation until the first scheduled run completes.

## Rate Limiting

The endpoint is protected by a simple per-IP rate limit to prevent abuse:

- Default limit: **6 requests per 5 minutes per IP**.
- Window: **300 seconds** (5 minutes), tracked per IP.
- When the limit is exceeded, the endpoint returns **HTTP 429** with a JSON body like:

  ```json
  { "status": "rate_limited", "message": "Rate limit exceeded, try again later." }
  ```

Advanced users can tune these values via WordPress filters:

- `hypercart_server_monitor_cron_health_rate_limit` (default `6`).
- `hypercart_server_monitor_cron_health_window_seconds` (default `300`).

## Troubleshooting When Unhealthy

Check these first:

- WP-Cron is not disabled (`DISABLE_WP_CRON` should not be `true`, unless you have a real system cron hitting `wp-cron.php`).
- The event is scheduled: in WP-Admin, go to the plugin's **Debug** tab.
- The site receives traffic (WP-Cron runs on requests) or you have a real cron configured.

