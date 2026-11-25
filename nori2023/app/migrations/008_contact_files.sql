-- 注文書・お問い合わせに付けてもらったファイル（ロゴなど）。ファイル本体は app/storage/customer_files に置く（ブラウザからは見られない）
CREATE TABLE IF NOT EXISTS contact_files (
  id            CHAR(36)     NOT NULL PRIMARY KEY,
  message_id    CHAR(36)     NOT NULL,
  original_name VARCHAR(200) NOT NULL,
  stored_path   VARCHAR(255) NOT NULL,
  mime          VARCHAR(100) NOT NULL,
  size          INT UNSIGNED NOT NULL,
  is_image      TINYINT(1)   NOT NULL DEFAULT 0,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_contact_files_message (message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
