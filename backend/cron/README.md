# Webhook Processor Setup

To ensure webhooks are sent reliably, you must configure a Cron Job (Linux) or Scheduled Task (Windows) to run the processor script every minute.

## Linux / cPanel Setup

1. Open your terminal or cPanel Cron Jobs interface.
2. Add the following line to your crontab:

```bash
* * * * * /usr/local/bin/php /path/to/your/backend/cron/process_webhooks.php >> /dev/null 2>&1
```

*Replace `/usr/local/bin/php` with the actual path to your PHP executable (often `/usr/bin/php`).*
*Replace `/path/to/your/backend/...` with the absolute path to the file.*

## Windows Setup (Local Development)

1. Open Task Scheduler.
2. Create a Basic Task named "OJG Webhook Processor".
3. Trigger: Daily -> Repeat task every 5 minutes (Windows Task Scheduler minimum is usually 5 mins, or use advanced settings for 1 min).
4. Action: Start a program.
   - Program/script: `php.exe` (or full path to php.exe)
   - Add arguments: `c:\Users\Alex\TraeCoder\OJG-Herbal\backend\cron\process_webhooks.php`

## How it Works

1. **Queueing**: When a form is submitted, the data is saved to the database and a job is added to the `webhook_queue` table with status `pending`.
2. **Processing**: The cron script runs every minute, picks up pending jobs, and attempts to send them.
3. **Retries**: If a webhook fails (e.g., destination server is down), it is marked as `failed` but scheduled for retry.
   - Retry 1: +5 minutes
   - Retry 2: +15 minutes
   - Retry 3: +45 minutes
   - ...up to 5 retries.
4. **Duplicates**: The system uses database locks (`status='processing'`) to ensure the same job isn't processed by multiple workers simultaneously.
