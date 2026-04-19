-- SRF monitor — run once against mediadb_ (or your DB).
-- mysql -u ... -p mediadb_ < sql/schema.sql

CREATE TABLE IF NOT EXISTS srf_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  urn VARCHAR(512) NOT NULL,
  bu VARCHAR(16) NOT NULL DEFAULT 'srf',
  episode_id VARCHAR(128) NOT NULL,
  show_id VARCHAR(128) DEFAULT NULL,
  show_title VARCHAR(512) DEFAULT NULL,
  channel_id VARCHAR(128) DEFAULT NULL,
  channel_title VARCHAR(512) DEFAULT NULL,
  title VARCHAR(512) DEFAULT NULL,
  description TEXT,
  subtitle_text LONGTEXT,
  subtitle_lang VARCHAR(16) DEFAULT NULL,
  permalink VARCHAR(1024) DEFAULT NULL,
  published_at DATETIME DEFAULT NULL,
  subtitles_available TINYINT(1) NOT NULL DEFAULT 0,
  fetched_subtitles_at DATETIME DEFAULT NULL,
  raw_metadata JSON DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_srf_items_urn (urn),
  KEY idx_srf_items_published (published_at),
  KEY idx_srf_items_bu (bu),
  KEY idx_srf_items_show (show_id),
  KEY idx_srf_items_channel (channel_id),
  FULLTEXT KEY ft_srf_items_search (title, description, subtitle_text)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS srf_fetch_state (
  state_key VARCHAR(128) NOT NULL,
  state_value LONGTEXT,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (state_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
