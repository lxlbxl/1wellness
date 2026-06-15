-- C.7.3 Referral system + C.7.2 password reset tokens

CREATE TABLE IF NOT EXISTS referrals (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    referrer_id     INTEGER      NOT NULL,
    referral_code   VARCHAR(16)  NOT NULL,
    condition       VARCHAR(20)  DEFAULT NULL,   -- condition of referrer (same-condition matching)
    referred_email  VARCHAR(255) DEFAULT NULL,
    referred_user_id INTEGER     DEFAULT NULL,
    status          VARCHAR(20)  NOT NULL DEFAULT 'pending',  -- pending|signed_up|purchased
    reward_issued   TINYINT      NOT NULL DEFAULT 0,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    converted_at    DATETIME     DEFAULT NULL,
    UNIQUE(referral_code),
    UNIQUE(referrer_id, referred_email)
);

CREATE INDEX IF NOT EXISTS idx_referrals_code     ON referrals(referral_code);
CREATE INDEX IF NOT EXISTS idx_referrals_referrer ON referrals(referrer_id);
CREATE INDEX IF NOT EXISTS idx_referrals_status   ON referrals(status);

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER     NOT NULL,
    token       VARCHAR(64) NOT NULL,
    expires_at  DATETIME    NOT NULL,
    used        TINYINT     NOT NULL DEFAULT 0,
    created_at  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(token)
);

CREATE INDEX IF NOT EXISTS idx_prt_token ON password_reset_tokens(token);
