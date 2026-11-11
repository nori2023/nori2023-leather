-- ワークショップで参加者が作った作品の写真。掲載の許可をもらったものだけ公開できる
CREATE TABLE IF NOT EXISTS workshop_photos (
  id CHAR(36) NOT NULL PRIMARY KEY,
  workshop_id CHAR(36) NULL,
  image_key VARCHAR(255) NOT NULL,
  caption VARCHAR(200) NULL,
  has_consent TINYINT(1) NOT NULL DEFAULT 0,
  is_published TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_wsphoto_ws (workshop_id),
  KEY idx_wsphoto_pub (is_published, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
