-- AutoCaller schema v1 (MySQL 5.5+/MariaDB 5.5+ compatible)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS schema_version (
  version INT NOT NULL PRIMARY KEY,
  applied_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(64) NOT NULL,
  display_name VARCHAR(128) NOT NULL DEFAULT '',
  password_hash VARCHAR(255) NOT NULL DEFAULT '',
  role ENUM('admin','operator','viewer') NOT NULL DEFAULT 'viewer',
  auth_source ENUM('local','issabel') NOT NULL DEFAULT 'local',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  last_login_at DATETIME NULL,
  last_login_ip VARCHAR(45) NULL,
  UNIQUE KEY uq_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS login_attempts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(64) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  attempted_at DATETIME NOT NULL,
  KEY ix_user (username, attempted_at),
  KEY ix_ip (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
  k VARCHAR(64) NOT NULL PRIMARY KEY,
  v TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audio_files (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(128) NOT NULL,
  original_name VARCHAR(255) NOT NULL DEFAULT '',
  path VARCHAR(255) NOT NULL,
  duration_sec INT NOT NULL DEFAULT 0,
  size_bytes INT UNSIGNED NOT NULL DEFAULT 0,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS campaigns (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(128) NOT NULL,
  kind ENUM('campaign','quick') NOT NULL DEFAULT 'campaign',
  description TEXT NULL,
  status ENUM('draft','scheduled','running','paused','completed','stopped') NOT NULL DEFAULT 'draft',
  audio_id INT UNSIGNED NULL,
  ivr_config TEXT NULL,
  max_repeats TINYINT UNSIGNED NOT NULL DEFAULT 1,
  max_retries TINYINT UNSIGNED NOT NULL DEFAULT 1,
  retry_delay_min INT UNSIGNED NOT NULL DEFAULT 15,
  retry_on VARCHAR(64) NOT NULL DEFAULT 'noanswer,busy,congestion,failed',
  concurrent TINYINT UNSIGNED NOT NULL DEFAULT 2,
  gap_ms INT UNSIGNED NOT NULL DEFAULT 1500,
  ring_timeout INT UNSIGNED NOT NULL DEFAULT 30,
  start_at DATETIME NULL,
  end_at DATETIME NULL,
  work_start CHAR(5) NULL,
  work_end CHAR(5) NULL,
  work_days VARCHAR(20) NULL,
  respect_holidays TINYINT(1) NOT NULL DEFAULT 1,
  priority TINYINT NOT NULL DEFAULT 5,
  callerid_name VARCHAR(64) NULL,
  callerid_number VARCHAR(32) NULL,
  dial_prefix VARCHAR(16) NULL,
  channel_tech VARCHAR(16) NULL,
  trunk_name VARCHAR(64) NULL,
  amd TINYINT(1) NOT NULL DEFAULT 0,
  total_contacts INT UNSIGNED NOT NULL DEFAULT 0,
  cnt_done INT UNSIGNED NOT NULL DEFAULT 0,
  cnt_answered INT UNSIGNED NOT NULL DEFAULT 0,
  last_dial_at DATETIME NULL,
  started_at DATETIME NULL,
  finished_at DATETIME NULL,
  last_error VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY ix_status (status, priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS campaign_contacts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  campaign_id INT UNSIGNED NOT NULL,
  phone VARCHAR(20) NOT NULL,
  raw_phone VARCHAR(64) NOT NULL DEFAULT '',
  name VARCHAR(128) NOT NULL DEFAULT '',
  audio_id INT UNSIGNED NULL,
  extra TEXT NULL,
  status ENUM('pending','dialing','answered','completed','noanswer','busy','congestion','failed','dnc','cancelled','invalid','machine') NOT NULL DEFAULT 'pending',
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  next_attempt_at DATETIME NULL,
  last_attempt_at DATETIME NULL,
  answered_at DATETIME NULL,
  dtmf VARCHAR(16) NULL,
  result_tag VARCHAR(64) NULL,
  duration_sec INT UNSIGNED NOT NULL DEFAULT 0,
  hangup_cause VARCHAR(64) NULL,
  amd_result VARCHAR(16) NULL,
  uniqueid VARCHAR(64) NULL,
  last_error VARCHAR(255) NULL,
  updated_at DATETIME NOT NULL,
  KEY ix_pick (campaign_id, status, next_attempt_at),
  KEY ix_phone (phone),
  KEY ix_updated (campaign_id, updated_at),
  KEY ix_uniqueid (uniqueid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS call_attempts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  contact_id INT UNSIGNED NOT NULL,
  campaign_id INT UNSIGNED NOT NULL,
  attempt_no TINYINT UNSIGNED NOT NULL DEFAULT 1,
  action_id VARCHAR(64) NOT NULL DEFAULT '',
  channel VARCHAR(160) NOT NULL DEFAULT '',
  uniqueid VARCHAR(64) NULL,
  started_at DATETIME NOT NULL,
  answered_at DATETIME NULL,
  ended_at DATETIME NULL,
  result VARCHAR(16) NOT NULL DEFAULT 'dialing',
  reason_code VARCHAR(16) NULL,
  hangup_cause VARCHAR(64) NULL,
  duration_sec INT UNSIGNED NOT NULL DEFAULT 0,
  dtmf VARCHAR(16) NULL,
  amd_result VARCHAR(16) NULL,
  KEY ix_contact (contact_id),
  KEY ix_campaign (campaign_id, started_at),
  KEY ix_action (action_id),
  KEY ix_uniqueid (uniqueid),
  KEY ix_result (result, started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS dnc (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  phone VARCHAR(20) NOT NULL,
  reason VARCHAR(255) NOT NULL DEFAULT '',
  source ENUM('manual','ivr','api','import') NOT NULL DEFAULT 'manual',
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS holidays (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  hdate DATE NOT NULL,
  title VARCHAR(128) NOT NULL DEFAULT '',
  UNIQUE KEY uq_date (hdate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS api_keys (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  name VARCHAR(64) NOT NULL DEFAULT '',
  key_hash CHAR(64) NOT NULL,
  key_prefix VARCHAR(12) NOT NULL DEFAULT '',
  allowed_ips VARCHAR(255) NOT NULL DEFAULT '',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  last_used_at DATETIME NULL,
  UNIQUE KEY uq_hash (key_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  username VARCHAR(64) NOT NULL DEFAULT '',
  ip VARCHAR(45) NOT NULL DEFAULT '',
  action VARCHAR(64) NOT NULL,
  object_type VARCHAR(32) NOT NULL DEFAULT '',
  object_id INT UNSIGNED NULL,
  details TEXT NULL,
  created_at DATETIME NOT NULL,
  KEY ix_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS daemon_status (
  id TINYINT NOT NULL PRIMARY KEY,
  pid INT NULL,
  started_at DATETIME NULL,
  heartbeat_at DATETIME NULL,
  ami_connected TINYINT(1) NOT NULL DEFAULT 0,
  active_calls INT NOT NULL DEFAULT 0,
  version VARCHAR(16) NOT NULL DEFAULT '',
  last_error VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO daemon_status (id) VALUES (1);
