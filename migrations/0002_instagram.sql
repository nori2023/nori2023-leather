-- Instagram 最新投稿の表示（Instagram API with Instagram Login）
-- 画像は Instagram 側URLの有効期限があるため R2(MEDIA) にコピーして配信する

CREATE TABLE integration_tokens (
  name        TEXT PRIMARY KEY,          -- 'instagram'
  token       TEXT NOT NULL,             -- 長期アクセストークン（画面には表示しない）
  expires_at  TEXT,
  refreshed_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now'))
);

CREATE TABLE instagram_posts (
  id          TEXT PRIMARY KEY,          -- Instagram の media id
  permalink   TEXT NOT NULL,
  caption     TEXT,
  media_type  TEXT NOT NULL,             -- IMAGE / VIDEO / CAROUSEL_ALBUM
  posted_at   TEXT NOT NULL,
  image_key   TEXT,                      -- R2(MEDIA) instagram/{id}.jpg
  synced_at   TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now'))
);
CREATE INDEX instagram_posts_posted_idx ON instagram_posts (posted_at DESC);

INSERT INTO site_settings (key, value) VALUES ('instagram.sync', '{"lastSyncAt":null,"lastError":null}');
