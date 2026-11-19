-- トップページの訪問者カウンター（1行だけのテーブル）
CREATE TABLE IF NOT EXISTS visitor_counter (
  id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  total BIGINT UNSIGNED NOT NULL DEFAULT 0,
  updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO visitor_counter (id, total, updated_at) VALUES (1, 0, NOW())
