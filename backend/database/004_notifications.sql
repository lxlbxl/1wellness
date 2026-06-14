-- 004_notifications.sql
-- Notification system Phase 1 tables
-- SQLite: run as-is. MySQL: replace INTEGER PRIMARY KEY AUTOINCREMENT with INT AUTO_INCREMENT PRIMARY KEY,
--         and INSERT OR IGNORE with INSERT IGNORE.
-- Postgres: replace INTEGER PRIMARY KEY AUTOINCREMENT with SERIAL PRIMARY KEY,
--           and INSERT OR IGNORE with INSERT ... ON CONFLICT DO NOTHING.

CREATE TABLE IF NOT EXISTS notification_queue (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    journey_key VARCHAR(60) NOT NULL,
    step INTEGER DEFAULT 1,
    recipient_type VARCHAR(10) NOT NULL,
    recipient_id INTEGER,
    email VARCHAR(255),
    phone VARCHAR(30),
    funnel VARCHAR(20),
    template_key VARCHAR(80) NOT NULL,
    payload TEXT,
    channel_ladder VARCHAR(60) NOT NULL,
    dedupe_key VARCHAR(120),
    send_after DATETIME NOT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    attempts INTEGER DEFAULT 0,
    next_attempt DATETIME,
    cancelled_reason VARCHAR(60),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(dedupe_key)
);
CREATE INDEX IF NOT EXISTS idx_nq_due ON notification_queue(status, send_after);
CREATE INDEX IF NOT EXISTS idx_nq_recipient ON notification_queue(email, journey_key);

CREATE TABLE IF NOT EXISTS notification_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    queue_id INTEGER,
    journey_key VARCHAR(60),
    step INTEGER,
    channel VARCHAR(20) NOT NULL,
    provider VARCHAR(30),
    provider_msg_id VARCHAR(120),
    email VARCHAR(255),
    phone VARCHAR(30),
    status VARCHAR(20) NOT NULL,
    error TEXT,
    cost_usd DECIMAL(8,5),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_nl_msgid ON notification_log(provider_msg_id);
CREATE INDEX IF NOT EXISTS idx_nl_channel ON notification_log(email, channel, created_at);

CREATE TABLE IF NOT EXISTS notification_consent (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email VARCHAR(255),
    phone VARCHAR(30),
    channel VARCHAR(20) NOT NULL,
    status VARCHAR(20) NOT NULL,
    source VARCHAR(60),
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(email, channel)
);
CREATE INDEX IF NOT EXISTS idx_nc_email ON notification_consent(email, channel);

CREATE TABLE IF NOT EXISTS notification_templates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    template_key VARCHAR(80) NOT NULL,
    channel VARCHAR(20) NOT NULL,
    funnel VARCHAR(20) DEFAULT 'all',
    subject VARCHAR(255),
    body TEXT NOT NULL,
    wa_template_name VARCHAR(120),
    active INTEGER DEFAULT 1,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(template_key, channel, funnel)
);

-- ---- Seed templates ----------------------------------------------------------

-- F1: purchase_confirm (email, all funnels)
INSERT OR IGNORE INTO notification_templates (template_key, channel, funnel, subject, body) VALUES (
'purchase_confirm_email', 'email', 'all',
'Your {{plan_label}} is confirmed — login details inside',
'<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f8f6f2;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f8f6f2;padding:40px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">
  <tr><td style="background:#0f3922;padding:32px;text-align:center;border-radius:12px 12px 0 0;">
    <h1 style="color:#F4F1EA;margin:0;font-size:24px;font-weight:400;">Welcome, {{name}}!</h1>
    <p style="color:#8DA38D;margin:8px 0 0;font-size:14px;">Your {{plan_label}} is ready</p>
  </td></tr>
  <tr><td style="background:#ffffff;padding:36px;border:1px solid #e0e0e0;">
    <p style="font-size:16px;line-height:1.6;color:#333;margin:0 0 20px;">Thank you for starting your wellness journey. Your personalized plan has been generated and is waiting for you in the member area.</p>
    <div style="background:#f8f6f2;padding:24px;border-radius:8px;margin:0 0 24px;border-left:4px solid #0f3922;">
      <p style="margin:0 0 12px;font-size:12px;color:#888;font-weight:bold;text-transform:uppercase;letter-spacing:1px;">Your Login Credentials</p>
      <p style="margin:6px 0;font-size:16px;color:#333;">Email: <strong style="color:#0f3922;">{{email}}</strong></p>
      {{#if password}}<p style="margin:6px 0;font-size:16px;color:#333;">Password: <strong style="color:#0f3922;">{{password}}</strong></p>{{/if}}
    </div>
    <div style="text-align:center;margin:28px 0;">
      <a href="{{app_url}}/member/login.html" style="background:#0f3922;color:#F4F1EA;padding:16px 40px;text-decoration:none;border-radius:30px;font-size:16px;font-weight:bold;display:inline-block;">Go to My Dashboard</a>
    </div>
    <p style="font-size:14px;color:#666;text-align:center;">Plan: <strong>{{plan_label}}</strong> · Expires {{plan_end_date}}</p>
    <p style="font-size:12px;color:#999;text-align:center;margin-top:16px;">Please save your password. We recommend changing it after your first login.</p>
  </td></tr>
  <tr><td style="background:#f8f6f2;padding:20px;text-align:center;border-radius:0 0 12px 12px;border:1px solid #e0e0e0;border-top:0;">
    <p style="margin:0 0 8px;font-size:12px;color:#999;">1wellness — Your Natural Healing Journey</p>
    <p style="margin:0;font-size:11px;color:#bbb;">This is a transactional email relating to your purchase. <a href="{{unsubscribe_url}}" style="color:#bbb;">Manage preferences</a></p>
    <p style="margin:8px 0 0;font-size:10px;color:#ccc;">The information in your plan is educational and does not constitute medical advice. Consult a qualified healthcare professional before making health decisions.</p>
  </td></tr>
</table>
</td></tr>
</table>
</body></html>'
);

-- C3: checkout_abandon step 1 (email, all funnels)
INSERT OR IGNORE INTO notification_templates (template_key, channel, funnel, subject, body) VALUES (
'checkout_abandon_1_email', 'email', 'all',
'{{name}}, your personalized plan is still waiting',
'<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f8f6f2;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f8f6f2;padding:40px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">
  <tr><td style="background:#0f3922;padding:32px;text-align:center;border-radius:12px 12px 0 0;">
    <h1 style="color:#F4F1EA;margin:0;font-size:22px;font-weight:400;">Your results are still here, {{name}}</h1>
  </td></tr>
  <tr><td style="background:#ffffff;padding:36px;border:1px solid #e0e0e0;">
    <p style="font-size:16px;line-height:1.6;color:#333;margin:0 0 20px;">You completed your assessment and your personalized plan is ready — you just did not finish checking out.</p>
    <p style="font-size:15px;line-height:1.6;color:#333;margin:0 0 24px;">A reminder of what comes with your plan:</p>
    <ul style="font-size:14px;line-height:2;color:#555;padding-left:20px;">
      <li>Personalized nutrition protocol matched to your results</li>
      <li>Herbal tea schedule and supplement guide</li>
      <li>Daily progress tracking in your member dashboard</li>
    </ul>
    <div style="background:#f0f7f3;padding:20px;border-radius:8px;margin:24px 0;text-align:center;">
      <p style="margin:0 0 6px;font-size:16px;font-weight:bold;color:#0f3922;">30-Day Money-Back Guarantee</p>
      <p style="margin:0;font-size:13px;color:#555;">If you do not see results in 30 days, we will refund you in full. No questions asked.</p>
    </div>
    <div style="text-align:center;margin:28px 0;">
      <a href="{{checkout_url}}" style="background:#0f3922;color:#F4F1EA;padding:16px 40px;text-decoration:none;border-radius:30px;font-size:16px;font-weight:bold;display:inline-block;">Complete My Order</a>
    </div>
    <p style="font-size:13px;color:#888;text-align:center;">Questions? Reply to this email or message us at <a href="mailto:hello@1wellness.club" style="color:#0f3922;">hello@1wellness.club</a></p>
  </td></tr>
  <tr><td style="background:#f8f6f2;padding:20px;text-align:center;border-radius:0 0 12px 12px;border:1px solid #e0e0e0;border-top:0;">
    <p style="margin:0 0 8px;font-size:12px;color:#999;">1wellness — Your Natural Healing Journey</p>
    <p style="margin:0;font-size:11px;color:#bbb;"><a href="{{unsubscribe_url}}" style="color:#bbb;">Unsubscribe</a> · <a href="{{prefs_url}}" style="color:#bbb;">Manage preferences</a></p>
    <p style="margin:8px 0 0;font-size:10px;color:#ccc;">Educational content only. Not medical advice.</p>
  </td></tr>
</table>
</td></tr>
</table>
</body></html>'
);

-- C1: assessment_abandon step 1 / 1h (email, all funnels)
INSERT OR IGNORE INTO notification_templates (template_key, channel, funnel, subject, body) VALUES (
'assessment_abandon_1_email', 'email', 'all',
'{{name}}, your free assessment is waiting to be finished',
'<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f8f6f2;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f8f6f2;padding:40px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">
  <tr><td style="background:#0f3922;padding:32px;text-align:center;border-radius:12px 12px 0 0;">
    <h1 style="color:#F4F1EA;margin:0;font-size:22px;font-weight:400;">You started something, {{name}}</h1>
    <p style="color:#8DA38D;margin:8px 0 0;font-size:14px;">Your personalized health insights are one step away</p>
  </td></tr>
  <tr><td style="background:#ffffff;padding:36px;border:1px solid #e0e0e0;">
    <p style="font-size:16px;line-height:1.6;color:#333;margin:0 0 20px;">You began your free health assessment but did not quite finish. Your answers are saved — it only takes 2 minutes to complete.</p>
    <p style="font-size:15px;line-height:1.6;color:#333;margin:0 0 24px;">When you finish you will receive:</p>
    <ul style="font-size:14px;line-height:2;color:#555;padding-left:20px;">
      <li>A personalized breakdown of your health profile</li>
      <li>Root-cause insights based on your symptoms</li>
      <li>A recommended natural protocol tailored to you</li>
    </ul>
    <div style="text-align:center;margin:32px 0;">
      <a href="{{resume_url}}" style="background:#0f3922;color:#F4F1EA;padding:16px 40px;text-decoration:none;border-radius:30px;font-size:16px;font-weight:bold;display:inline-block;">Finish My Assessment</a>
    </div>
    <p style="font-size:13px;color:#888;text-align:center;">Takes less than 2 minutes. It is completely free.</p>
  </td></tr>
  <tr><td style="background:#f8f6f2;padding:20px;text-align:center;border-radius:0 0 12px 12px;border:1px solid #e0e0e0;border-top:0;">
    <p style="margin:0 0 8px;font-size:12px;color:#999;">1wellness — Your Natural Healing Journey</p>
    <p style="margin:0;font-size:11px;color:#bbb;"><a href="{{unsubscribe_url}}" style="color:#bbb;">Unsubscribe</a> · <a href="{{prefs_url}}" style="color:#bbb;">Manage preferences</a></p>
    <p style="margin:8px 0 0;font-size:10px;color:#ccc;">Educational content only. Not medical advice.</p>
  </td></tr>
</table>
</td></tr>
</table>
</body></html>'
);

-- F4: order_bump_fulfil (email fallback, all funnels — Phase 1 before WhatsApp)
INSERT OR IGNORE INTO notification_templates (template_key, channel, funnel, subject, body) VALUES (
'order_bump_fulfil_email', 'email', 'all',
'Your Expert Access is confirmed — here is how to use it',
'<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f8f6f2;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f8f6f2;padding:40px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">
  <tr><td style="background:#0f3922;padding:32px;text-align:center;border-radius:12px 12px 0 0;">
    <h1 style="color:#F4F1EA;margin:0;font-size:24px;font-weight:400;">Expert Access confirmed, {{name}}</h1>
    <p style="color:#8DA38D;margin:8px 0 0;font-size:14px;">Your direct line to our wellness experts is active</p>
  </td></tr>
  <tr><td style="background:#ffffff;padding:36px;border:1px solid #e0e0e0;">
    <p style="font-size:16px;line-height:1.6;color:#333;margin:0 0 20px;">Thank you for adding Expert Access to your order. This gives you direct Q&amp;A access with our wellness team throughout your plan.</p>
    <div style="background:#f0f7f3;padding:24px;border-radius:8px;margin:0 0 24px;border-left:4px solid #0f3922;">
      <p style="margin:0 0 12px;font-size:12px;color:#888;font-weight:bold;text-transform:uppercase;letter-spacing:1px;">How to Use Expert Access</p>
      <p style="margin:0 0 8px;font-size:14px;color:#333;">1. Log in to your member dashboard</p>
      <p style="margin:0 0 8px;font-size:14px;color:#333;">2. Navigate to <strong>My Plan → Ask an Expert</strong></p>
      <p style="margin:0;font-size:14px;color:#333;">3. Submit your question — we respond within 24 hours</p>
    </div>
    <div style="text-align:center;margin:28px 0;">
      <a href="{{app_url}}/member/login.html" style="background:#0f3922;color:#F4F1EA;padding:16px 40px;text-decoration:none;border-radius:30px;font-size:16px;font-weight:bold;display:inline-block;">Go to My Dashboard</a>
    </div>
    <p style="font-size:13px;color:#888;text-align:center;">Questions about Expert Access? Email us at <a href="mailto:hello@1wellness.club" style="color:#0f3922;">hello@1wellness.club</a></p>
  </td></tr>
  <tr><td style="background:#f8f6f2;padding:20px;text-align:center;border-radius:0 0 12px 12px;border:1px solid #e0e0e0;border-top:0;">
    <p style="margin:0 0 8px;font-size:12px;color:#999;">1wellness — Your Natural Healing Journey</p>
    <p style="margin:0;font-size:11px;color:#bbb;">This is a transactional email relating to your purchase. <a href="{{unsubscribe_url}}" style="color:#bbb;">Manage preferences</a></p>
    <p style="margin:8px 0 0;font-size:10px;color:#ccc;">Educational content only. Not medical advice.</p>
  </td></tr>
</table>
</td></tr>
</table>
</body></html>'
);

-- C3: checkout_abandon step 2 / 20h (email, all funnels)
INSERT OR IGNORE INTO notification_templates (template_key, channel, funnel, subject, body) VALUES (
'checkout_abandon_2_email', 'email', 'all',
'One last thing before your plan expires, {{name}}',
'<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f8f6f2;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f8f6f2;padding:40px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">
  <tr><td style="background:#0f3922;padding:32px;text-align:center;border-radius:12px 12px 0 0;">
    <h1 style="color:#F4F1EA;margin:0;font-size:22px;font-weight:400;">Still thinking it over?</h1>
  </td></tr>
  <tr><td style="background:#ffffff;padding:36px;border:1px solid #e0e0e0;">
    <p style="font-size:16px;line-height:1.6;color:#333;">Hi {{name}}, this is the final reminder about your personalized {{funnel_name}} plan.</p>
    <p style="font-size:15px;line-height:1.6;color:#555;">Your assessment results are saved. The plan will be generated the moment you complete your order — there is no extra wait.</p>
    <div style="text-align:center;margin:32px 0;">
      <a href="{{checkout_url}}" style="background:#D97757;color:#fff;padding:16px 40px;text-decoration:none;border-radius:30px;font-size:16px;font-weight:bold;display:inline-block;">Get My Plan Now</a>
    </div>
    <p style="font-size:13px;color:#888;text-align:center;">Protected by our 30-day money-back guarantee.</p>
  </td></tr>
  <tr><td style="background:#f8f6f2;padding:20px;text-align:center;border-radius:0 0 12px 12px;border:1px solid #e0e0e0;border-top:0;">
    <p style="margin:0;font-size:11px;color:#bbb;"><a href="{{unsubscribe_url}}" style="color:#bbb;">Unsubscribe</a></p>
  </td></tr>
</table>
</td></tr>
</table>
</body></html>'
);
