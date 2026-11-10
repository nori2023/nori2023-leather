-- 「これまでに作ったもん」の写真（トップページの横スクロール）。管理画面から追加・並び替え・非公開にできる
CREATE TABLE IF NOT EXISTS works_photos (
  id CHAR(36) NOT NULL PRIMARY KEY,
  image_key VARCHAR(255) NULL,
  static_path VARCHAR(255) NULL,
  caption VARCHAR(100) NOT NULL,
  alt VARCHAR(200) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_published TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_works_order (is_published, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- これまでサイトに載せていた7枚を最初から登録しておく
INSERT IGNORE INTO works_photos (id, image_key, static_path, caption, alt, sort_order, is_published) VALUES
  ('6b1d0c3e-8a47-4e0f-9c11-000000000001', NULL, '/images/works/coincase-natural-1.jpg', 'コインケース（ナチュラル）', 'ナチュラルのヌメ革コインケース', 10, 1),
  ('6b1d0c3e-8a47-4e0f-9c11-000000000002', NULL, '/images/works/coincase-red-1.jpg', 'コインケース（レッド）', '赤い革のコインケース', 20, 1),
  ('6b1d0c3e-8a47-4e0f-9c11-000000000003', NULL, '/images/works/coincase-brown-open.jpg', 'コインケース（ブラウン）', 'ダークブラウンのコインケースを開いたところ', 30, 1),
  ('6b1d0c3e-8a47-4e0f-9c11-000000000004', NULL, '/images/works/keyholder-4colors.jpg', '編み込みキーホルダー', '4色の編み込みレザーキーホルダー', 40, 1),
  ('6b1d0c3e-8a47-4e0f-9c11-000000000005', NULL, '/images/works/leather-box.jpg', 'ヌメ革のボックス', '鋲留めのヌメ革ボックス', 50, 1),
  ('6b1d0c3e-8a47-4e0f-9c11-000000000006', NULL, '/images/works/backpack-collage.jpg', 'ヌメ革のリュック', 'ヌメ革のリュックサックと細部', 60, 1),
  ('6b1d0c3e-8a47-4e0f-9c11-000000000007', NULL, '/images/works/wallet-open.jpg', '二つ折り財布', '二つ折り財布の内側', 70, 1)
