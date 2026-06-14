#!/bin/bash
# ============================================================
# 1wellness Cron Installer
# Run once on your server as the web user (e.g. www-data):
#   sudo -u www-data bash backend/cron/install-cron.sh
# ============================================================

set -e

# Resolve the absolute path to this repo
REPO_DIR="$(cd "$(dirname "$0")/../.." && pwd)"
PHP_BIN="${PHP_BIN:-php}"
LOG_DIR="$REPO_DIR/backend/logs"
CRON_TAG="# 1wellness-cron"

mkdir -p "$LOG_DIR"

echo "[1wellness] Installing cron jobs for: $REPO_DIR"
echo "[1wellness] Using PHP binary: $($PHP_BIN -r 'echo PHP_VERSION;' 2>/dev/null || echo 'NOT FOUND — set PHP_BIN env var')"

# ---- Build the cron block ----
CRON_BLOCK="
# ============================================================
$CRON_TAG — DO NOT EDIT THIS BLOCK MANUALLY (re-run install-cron.sh to update)
# ============================================================

# Journey engine: evaluate who to enqueue next (every 5 min)
*/5 * * * * $PHP_BIN $REPO_DIR/backend/cron/journeys.php >> $LOG_DIR/journeys.log 2>&1 $CRON_TAG

# Notification sender: dispatch queued items (every 1 min)
* * * * * $PHP_BIN $REPO_DIR/backend/cron/send_notifications.php >> $LOG_DIR/send_notifications.log 2>&1 $CRON_TAG

# Webhook retry worker: re-attempt failed webhook deliveries (every 10 min)
*/10 * * * * $PHP_BIN $REPO_DIR/backend/cron/process_webhooks.php >> $LOG_DIR/process_webhooks.log 2>&1 $CRON_TAG

# A/B posterior recompute: update Thompson Sampling priors from new data (every 30 min)
*/30 * * * * $PHP_BIN $REPO_DIR/backend/cron/recompute_posteriors.php >> $LOG_DIR/recompute_posteriors.log 2>&1 $CRON_TAG

# Payment reconciliation: verify Flutterwave charges vs DB (every 6 hours)
0 */6 * * * $PHP_BIN $REPO_DIR/backend/cron/reconcile_payments.php >> $LOG_DIR/reconcile_payments.log 2>&1 $CRON_TAG

# Weekly member plans: generate upcoming-week plans for active members (Sunday 02:00)
0 2 * * 0 $PHP_BIN $REPO_DIR/backend/cron/generate_weekly_plans.php >> $LOG_DIR/generate_weekly_plans.log 2>&1 $CRON_TAG

# AI diagnostics: nightly health check on AI/DB subsystems (03:30 daily)
30 3 * * * $PHP_BIN $REPO_DIR/backend/cron/ai_diagnostics.php >> $LOG_DIR/ai_diagnostics.log 2>&1 $CRON_TAG

# ============================================================
"

# ---- Remove any previous 1wellness block and append fresh one ----
TMPFILE=$(mktemp)
# Remove lines between cron block markers
crontab -l 2>/dev/null | sed "/$CRON_TAG/d" > "$TMPFILE" || true
# Remove any blank lines left at end
sed -i 's/^[[:space:]]*$//' "$TMPFILE"
printf "%s\n" "$CRON_BLOCK" >> "$TMPFILE"
crontab "$TMPFILE"
rm "$TMPFILE"

echo "[1wellness] Cron jobs installed. Current crontab:"
crontab -l | grep -v "^$"

echo ""
echo "[1wellness] Log files will appear in: $LOG_DIR"
echo "[1wellness] Rotate logs by adding to /etc/logrotate.d/1wellness:"
echo "  $LOG_DIR/*.log {"
echo "      daily"
echo "      rotate 14"
echo "      compress"
echo "      missingok"
echo "      notifempty"
echo "  }"
