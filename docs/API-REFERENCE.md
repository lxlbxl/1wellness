# 1wellness API Reference

Base path: `https://<host>/backend/api/`

## Authentication

Management endpoints accept **either**:

- **Admin session** — being logged in to the admin panel (cookie-based; used by the panel's own pages), or
- **API key** — header `X-API-KEY: <key>` (or `?api_key=<key>`), matching `N8N_API_KEY` in `.env`.

Public endpoints (no auth): `track-event.php`, payment webhooks (`webhook_*.php`).

All responses are JSON with a `success` boolean. Errors: `{"success": false, "error": "..."}` with
an appropriate HTTP status (400 bad request, 401 unauthorized, 404 not found, 422 validation, 500 internal).

---

## 1. Event ingestion — `POST /backend/api/track-event.php` (public)

Records a funnel event, attributed server-side to the session's live A/B assignments.

```json
{
  "session_id": "1w_abc123_xyz",
  "funnel": "pcos",
  "event": "assessment_start",
  "url": "/pcos/assessment.html",
  "step": "optional label",
  "email": "optional@example.com",
  "metadata": { "any": "json" }
}
```

| Field | Required | Notes |
|---|---|---|
| `session_id` | yes | 8–100 chars `[A-Za-z0-9_-.]` — use the value tracking.js stores in `1w_session_id` |
| `funnel` | yes | `pcos` \| `acne` \| `weight` \| `mens` |
| `event` | yes | `view`, `assessment_start`, `assessment_complete`, `results_view`, `plan_select`, `checkout_init`. **`purchase` is rejected** (server-side only) |

Responses:
- `{"success":true,"recorded":1}` — recorded (one row per live experiment assignment)
- `{"success":true,"recorded":0,"deduped":true}` — duplicate (views: per page/day; steps: first-touch per session)
- `{"success":true,"recorded":0,"filtered":"bot"|"timing"}` — bot UA, or `assessment_start` < 2 s after `view`

---

## 2. Experiments API — `/backend/api/ab-experiments.php` (auth required)

### GET actions

| Request | Returns |
|---|---|
| `?action=list[&funnel=pcos][&status=active][&include_archived=1]` | experiments with variants |
| `?action=get&id=1` | one experiment + variants |
| `?action=stats&id=1` | full stats: per-variant waterfall, CR, RPV, traffic share, P(best), 30-day daily trend, latest AI insight, approval queue |
| `?action=approval_queue` | all AI challengers pending approval |
| `?action=meta` | valid funnels / stages / metrics / events |

### POST actions (JSON body)

**Create**
```json
{
  "action": "create",
  "experiment": {
    "funnel_name": "pcos",
    "name": "Hero headline test",
    "stage": "landing",
    "primary_metric": "assessment_start",
    "hypothesis": "optional",
    "burn_in_hours": 48,
    "min_exposure_floor": 0.10,
    "min_samples_per_variant": 1000,
    "decision_p_best": 0.95,
    "decision_expected_loss": 0.005
  },
  "variants": [
    { "name": "Control", "type": "control" },
    { "name": "B: urgency", "type": "element",
      "overrides": { "text": { "[data-exp='headline']": "New headline" } } },
    { "name": "C: longform", "type": "structural", "directory": "pcos__longform" }
  ]
}
```
Returns `{"success":true,"id":<experiment_id>}` (created as `draft`).
Validation enforced: known funnel/stage/metric, ≥2 variants, exactly one control, structural dirs
must exist as `{funnel}__{slug}/index.html`, override keys limited to `text|html|attr|style|config`,
and max one live experiment per funnel stage (`"force":1` to override).

**Lifecycle**

| Body | Effect |
|---|---|
| `{"action":"start","id":1}` | draft/paused → `burn_in` (or `active` if no burn-in) — fires `experiment.started` |
| `{"action":"pause","id":1}` | running → `paused` (stops assignment) |
| `{"action":"conclude","id":1,"winner_variant_id":2}` | manual conclusion + winner promotion — fires `experiment.concluded` |
| `{"action":"archive","id":1}` | concluded/draft/paused → `archived` |
| `{"action":"delete","id":1}` | delete (draft/archived only) |
| `{"action":"update","id":1,"experiment":{...}}` | edit guardrails (stage/metric/funnel only while draft) |
| `{"action":"recompute"}` | force posterior recompute now (normally hourly cron) |

**Variants**

| Body | Effect |
|---|---|
| `{"action":"add_variant","id":1,"variant":{...}}` | add a variant to an experiment |
| `{"action":"update_overrides","variant_id":2,"overrides":{...},"name":"optional"}` | edit overrides |
| `{"action":"kill_variant","variant_id":2,"generate_challenger":true}` | kill (control cannot be killed); optionally have the AI propose a replacement — fires `experiment.variant_killed` (+`experiment.challenger_proposed`) |
| `{"action":"approve_variant","variant_id":4}` | approve pending AI challenger → serves traffic |
| `{"action":"reject_variant","variant_id":4}` | reject pending AI challenger |

### Example (n8n / curl)

```bash
curl -s "https://1wellness.club/backend/api/ab-experiments.php?action=stats&id=1" \
  -H "X-API-KEY: $KEY"

curl -s -X POST "https://1wellness.club/backend/api/ab-experiments.php" \
  -H "X-API-KEY: $KEY" -H "Content-Type: application/json" \
  -d '{"action":"start","id":1}'
```

---

## 3. Webhooks API — `/backend/api/webhooks-api.php` (auth required)

Manage outbound webhook subscriptions programmatically (same data as the admin Webhooks page).
Full event catalog, payload schemas and signature verification: [WEBHOOKS.md](WEBHOOKS.md).

### GET actions

| Request | Returns |
|---|---|
| `?action=list` | all subscriptions (secrets masked) |
| `?action=get&id=wh_xxx` | one subscription |
| `?action=events` | event catalog with labels, descriptions and sample payloads |
| `?action=deliveries&id=wh_xxx[&limit=20]` | recent delivery attempts |

### POST actions

| Body | Effect |
|---|---|
| `{"action":"create","name":"...","url":"https://...","events":["sale.completed"],"secret":"optional","headers":{"X-Custom":"v"}}` | create; **the signing secret is returned only in this response** |
| `{"action":"update","id":"wh_xxx", ...fields}` | update name/url/events/secret/headers/status |
| `{"action":"pause","id":"wh_xxx"}` / `{"action":"resume","id":"wh_xxx"}` | toggle delivery |
| `{"action":"test","id":"wh_xxx"}` | send a signed sample payload synchronously; returns HTTP code + response |
| `{"action":"delete","id":"wh_xxx"}` | delete + drop pending deliveries |
| `{"action":"dispatch","event":"sale.completed","data":{...}}` | manually enqueue an event to all subscribers (testing / n8n round-trips) |

---

## 4. Payment webhooks — `POST /backend/api/webhook_{pcos|acne|weight|mens}.php` (public)

Called by the checkout flow (and n8n) after a confirmed Flutterwave payment. Creates/updates the
member account, records the sale, generates the plan, and — for the A/B engine — logs the
server-side `purchase` event with revenue attributed to the session's variant assignments, then
fires `sale.completed`, `funnel.purchase` and (for new accounts) `user.registered` webhooks.

```json
{
  "email": "jane@example.com",
  "name": "Jane D.",
  "transaction_id": "FLW123",
  "tx_ref": "1w-pcos-...",
  "amount": 97,
  "currency": "USD",
  "product": "PCOS 90-Day Plan",
  "session_id": "1w_abc123_xyz"
}
```

`session_id` is what links the purchase to the visitor's experiment assignments — the frontend
(`js/webhook-manager.js`) sends it automatically.

---

## 5. Existing endpoints (unchanged, for completeness)

| Endpoint | Purpose |
|---|---|
| `n8n-export.php` | Data export for automation (users/sales/assessments) — X-API-KEY auth |
| `form-handler.php` | Assessment form submission |
| `get-pricing.php`, `get-settings.php`, `get-assessment.php`, `get-sale.php`, `get-credentials.php` | Frontend data fetchers |
| `sales-stats.php`, `sales-list.php` | Sales reporting |
| `health.php` | Health check |
| `sync-funnels.php` | Funnel discovery sync |
