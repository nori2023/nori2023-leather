-- 画像の保存先（カード登録不要の構成）
-- R2 を有効化しない場合、公開画像はこの表に保存して配信する（1ファイル 1.9MB まで）
CREATE TABLE media_objects (
  key          TEXT PRIMARY KEY,
  content_type TEXT NOT NULL,
  size         INTEGER NOT NULL CHECK (size > 0 AND size <= 1992294),
  data         BLOB NOT NULL,
  created_at   TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now'))
);
