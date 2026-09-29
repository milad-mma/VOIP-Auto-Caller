-- Trunk pool managed by the app (bypasses Issabel outbound routes and their failover)
CREATE TABLE IF NOT EXISTS trunks (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(64) NOT NULL,
  tech ENUM('sip','pjsip','custom') NOT NULL DEFAULT 'sip',
  channel_id VARCHAR(64) NOT NULL,
  dial_template VARCHAR(160) NOT NULL DEFAULT '',
  dial_prefix VARCHAR(16) NOT NULL DEFAULT '',
  max_channels SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  is_enabled TINYINT(1) NOT NULL DEFAULT 1,
  sort INT NOT NULL DEFAULT 0,
  calls_total INT UNSIGNED NOT NULL DEFAULT 0,
  calls_failed INT UNSIGNED NOT NULL DEFAULT 0,
  last_used_at DATETIME NULL,
  last_error VARCHAR(128) NULL,
  UNIQUE KEY uq_channel (channel_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE call_attempts ADD COLUMN trunk_id INT UNSIGNED NULL AFTER channel;
ALTER TABLE call_attempts ADD KEY ix_trunk_live (trunk_id, result);
