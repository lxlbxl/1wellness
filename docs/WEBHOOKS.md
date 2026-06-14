# 1wellness Outbound Webhooks

Push platform events to n8n, Slack, Zapier, or any HTTPS endpoint. Webhooks are managed in
**Admin → Webhooks** (`backend/admin/webhooks.php`) — when adding a webhook URL you select
exactly which events fire it — or programmatically via the
[Webhooks API](API-REFERENCE.md#3-webhooks-api--backendapiwebhooks-apiphp-auth-required).

## How delivery works

1. Application code calls `WebhookDispatcher::dispatch(event, data)`.
2. One delivery row per subscribed **active** webhook is enqueued in `webhook_queue`.
3. The cron worker `backend/cron/process_webhooks.php` (run every minute) POSTs the payload.
4. Non-2xx / network failures are retried with exponential backoff (5m, 15m, 45m, 2h, 6h —
   `WEBHOOK_MAX_RETRIES`, default 5). Success/failure counters and last-fired time are tracked
   per webhook and shown in the admin list; per-delivery status is under the list icon
   ("Recent deliveries").

The admin **Test** button (and API `action=test`) sends a signed sample payload synchronously
and reports the HTTP status immediately.

## Event catalog

| Event | Fires when | Key payload fields |
|---|---|---|
| `assessment.completed` | A visitor finishes a funnel assessment | `email`, `name`, `funnel`, `assessment_type`, `score` |
| `user.registered` | A new member account is created (purchase flow) | `user_id`, `email`, `name`, `funnel` |
| `sale.completed` | Payment confirmed & sale recorded | `sale_id`, `user_id`, `email`, `amount`, `currency`, `product_type`, `product_name`, `plan_duration`, `transaction_id`, `tx_ref` |
| `funnel.purchase` | Server-confirmed purchase with A/B attribution | `session_id`, `funnel`, `amount`, `currency`, `experiment_id`, `variant_id` |
| `experiment.started` | An experiment begins serving traffic | `experiment_id`, `name`, `funnel`, `stage`, `primary_metric`, `status`, `variants` |
| `experiment.concluded` | An experiment reaches a decision | `experiment_id`, `name`, `winner_variant_id`, `winner_name`, `p_best`, `lift_vs_control`, `auto` |
| `experiment.variant_killed` | A losing variant is killed | `experiment_id`, `variant_id`, `variant_name`, `reason` |
| `experiment.challenger_proposed` | The AI proposes a challenger (awaiting approval) | `experiment_id`, `variant_id`, `variant_name`, `compliance_status`, `rationale` |
| `experiment.insight_ready` | Weekly AI diagnostic produced a report | `insight_id`, `funnel`, `summary`, `suggestions` |

Live catalog with sample payloads: `GET /backend/api/webhooks-api.php?action=events`.

## Payload envelope

Every delivery is a JSON POST:

```json
{
  "event": "sale.completed",
  "timestamp": "2026-06-12T14:03:22+00:00",
  "webhook_id": "wh_05c4506b751ad656",
  "data": {
    "sale_id": "ORD_6849...",
    "email": "jane@example.com",
    "amount": 97,
    "currency": "USD",
    "product_type": "pcos"
  }
}
```

Headers:

```
Content-Type: application/json
User-Agent: 1wellness-Webhooks/1.0
X-Webhook-Signature: <hmac-sha256 hex of the raw body>
<your custom headers, if configured>
```

## Verifying signatures

Each webhook has a signing secret (auto-generated at creation and shown **once**; you can also
supply your own). Verify on the receiving end:

```php
// PHP
$expected = hash_hmac('sha256', file_get_contents('php://input'), $secret);
if (!hash_equals($expected, $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '')) {
    http_response_code(401); exit;
}
```

```javascript
// Node / n8n Function node
const crypto = require('crypto');
const expected = crypto.createHmac('sha256', secret).update(rawBody).digest('hex');
const ok = crypto.timingSafeEqual(Buffer.from(expected), Buffer.from(signatureHeader));
```

## n8n recipe (winner notifications → WhatsApp/Slack)

1. n8n: create a **Webhook** node (POST), copy its production URL.
2. Admin → Webhooks → **Add Webhook**: paste the URL, tick `experiment.concluded` and
   `experiment.insight_ready`, create, store the secret.
3. In n8n verify `X-Webhook-Signature`, then branch on `{{$json.event}}`:
   - `experiment.concluded` → message: *"🏆 {{data.winner_name}} won {{data.name}} ({{data.lift_vs_control}} lift, P(best) {{data.p_best}})"*
   - `experiment.insight_ready` → forward `data.summary` + `data.suggestions`.
4. Use the **Test** button to validate the wiring end-to-end.

## Operational notes

- Endpoints must respond **2xx within 10 s** (`WEBHOOK_TIMEOUT`); anything else is retried.
- Deliveries are at-least-once — make consumers idempotent (key on `data.tx_ref`,
  `experiment_id` + `event`, etc.).
- Paused webhooks receive nothing; their queue rows are not generated.
- Deleting a webhook drops its pending deliveries.
- TLS certificates are verified in production (`APP_ENV=production`).
