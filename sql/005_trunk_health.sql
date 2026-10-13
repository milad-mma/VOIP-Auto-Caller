-- Trunk health: consecutive-failure tracking, automatic cooldown, registration/peer state from Asterisk
ALTER TABLE trunks ADD COLUMN fail_streak SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER calls_failed;
ALTER TABLE trunks ADD COLUMN cooldown_until DATETIME NULL AFTER fail_streak;
ALTER TABLE trunks ADD COLUMN cooldown_count SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER cooldown_until;
ALTER TABLE trunks ADD COLUMN last_ok_at DATETIME NULL AFTER cooldown_count;
ALTER TABLE trunks ADD COLUMN reg_state VARCHAR(24) NOT NULL DEFAULT '' AFTER last_ok_at;
ALTER TABLE trunks ADD COLUMN reg_detail VARCHAR(160) NOT NULL DEFAULT '' AFTER reg_state;
ALTER TABLE trunks ADD COLUMN reg_checked_at DATETIME NULL AFTER reg_detail;
