-- Run once against the OVH MySQL database (via Manager phpMyAdmin or SSH mysql client).

CREATE TABLE IF NOT EXISTS stats_daily (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  stat_date          DATE NOT NULL,
  subscribers        INT UNSIGNED DEFAULT NULL,
  subscribers_delta  INT DEFAULT NULL,
  views_total        BIGINT UNSIGNED DEFAULT NULL,
  views_delta        INT DEFAULT NULL,
  video_count        INT UNSIGNED DEFAULT NULL,
  watch_minutes      INT UNSIGNED DEFAULT NULL,
  extra              JSON DEFAULT NULL,
  created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_stat_date (stat_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS kanban_cards (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  brand           VARCHAR(255) NOT NULL,
  contact         VARCHAR(255) DEFAULT NULL,
  deadline        DATE DEFAULT NULL,
  payment_status  ENUM('unpaid','pending','partial','paid') NOT NULL DEFAULT 'unpaid',
  payment_amount  DECIMAL(10,2) DEFAULT NULL,
  currency        CHAR(3) NOT NULL DEFAULT 'EUR',
  notes           TEXT,
  stage           VARCHAR(30) NOT NULL DEFAULT 'lead',
  position        INT NOT NULL DEFAULT 0,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_stage (stage)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
