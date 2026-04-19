-- Email subscriptions (Tier 1): domain-first with per-address overrides.
-- Run manually if initDatabase() did not apply (e.g. locked schema_version).

CREATE TABLE IF NOT EXISTS email_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    match_type ENUM('domain', 'email') NOT NULL,
    match_value VARCHAR(255) NOT NULL,
    display_name VARCHAR(255) NOT NULL,
    category VARCHAR(100) DEFAULT NULL,
    disabled TINYINT(1) NOT NULL DEFAULT 0,
    auto_detected TINYINT(1) NOT NULL DEFAULT 1,
    unsubscribe_url VARCHAR(1000) DEFAULT NULL,
    unsubscribe_mailto VARCHAR(500) DEFAULT NULL,
    unsubscribe_one_click TINYINT(1) NOT NULL DEFAULT 0,
    first_seen_at DATETIME DEFAULT NULL,
    last_seen_at DATETIME DEFAULT NULL,
    item_count INT NOT NULL DEFAULT 0,
    removed_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_match (match_type, match_value),
    INDEX idx_disabled (disabled),
    INDEX idx_removed_at (removed_at),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
