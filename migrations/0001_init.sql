-- =====================================================================
-- おっさんのレザークラフト（Nori2023） 初期スキーマ（Cloudflare D1 / SQLite）
--   * 1点もの（products）と受注制作（custom_*）は分け、注文・決済・発送は共通化
--   * 確定時点の内容は snapshot（JSON文字列）で保存
--   * 二重販売防止：UPDATE ... WHERE status='on_sale' の条件付き更新（1文で原子的）
--     ＋ product_reservations の部分ユニーク索引（有効な確保は1商品1件）
--   * カード番号は一切保存しない
--   * ID は UUID（アプリ側で crypto.randomUUID() を採番）
--   * 日時は ISO8601 文字列（UTC）
-- =====================================================================

PRAGMA foreign_keys = ON;

-- ---------------------------------------------------------------------
-- サイト設定（キャッチコピー・ページ文言など。value は JSON）
-- ---------------------------------------------------------------------
CREATE TABLE site_settings (
  key        TEXT PRIMARY KEY,
  value      TEXT NOT NULL,
  updated_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now')),
  updated_by TEXT
);

-- ---------------------------------------------------------------------
-- 1点もの完成品
-- ---------------------------------------------------------------------
CREATE TABLE categories (
  id         TEXT PRIMARY KEY,
  slug       TEXT NOT NULL UNIQUE,
  name       TEXT NOT NULL,
  sort_order INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now'))
);

CREATE TABLE shipping_profiles (
  id         TEXT PRIMARY KEY,
  name       TEXT NOT NULL,
  fee        INTEGER CHECK (fee IS NULL OR fee >= 0),  -- 税込。NULL = 未確定
  note       TEXT,
  is_active  INTEGER NOT NULL DEFAULT 1,
  created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now'))
);

-- status: draft=非公開 / on_sale=販売中 / reserved=購入手続き中 / sold_out=SOLD OUT / suspended=販売停止
CREATE TABLE products (
  id                  TEXT PRIMARY KEY,
  code                TEXT NOT NULL UNIQUE,      -- 商品管理番号
  slug                TEXT NOT NULL UNIQUE,
  name                TEXT NOT NULL,
  category_id         TEXT REFERENCES categories(id) ON DELETE SET NULL,
  description         TEXT NOT NULL DEFAULT '',
  material            TEXT,
  size                TEXT,
  color               TEXT,
  price               INTEGER NOT NULL CHECK (price >= 0), -- 税込販売価格
  shipping_profile_id TEXT REFERENCES shipping_profiles(id) ON DELETE SET NULL,
  status              TEXT NOT NULL DEFAULT 'draft'
                      CHECK (status IN ('draft','on_sale','reserved','sold_out','suspended')),
  stock               INTEGER NOT NULL DEFAULT 1 CHECK (stock IN (0,1)), -- 1点もの固定
  sold_at             TEXT,
  published_at        TEXT,
  created_at          TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now')),
  updated_at          TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now'))
);
CREATE INDEX products_status_idx ON products (status, published_at DESC);

CREATE TABLE product_images (
  id          TEXT PRIMARY KEY,
  product_id  TEXT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
  key_large   TEXT NOT NULL,              -- R2(MEDIA) のキー（長辺1600px）
  key_small   TEXT NOT NULL,              -- R2(MEDIA) のキー（長辺640px）
  alt         TEXT NOT NULL DEFAULT '',
  width       INTEGER,
  height      INTEGER,
  sort_order  INTEGER NOT NULL DEFAULT 0,
  is_main     INTEGER NOT NULL DEFAULT 0,
  created_at  TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now'))
);
CREATE INDEX product_images_product_idx ON product_images (product_id, sort_order);
CREATE UNIQUE INDEX product_images_one_main ON product_images (product_id) WHERE is_main = 1;

-- ---------------------------------------------------------------------
-- 注文（完成品・受注制作 共通）
-- status: received 新規受付 / checking_data データ確認中 / awaiting_customer お客様確認待ち /
--         revising 修正対応中 / approved 承認済み / awaiting_payment 決済待ち / paid 決済完了 /
--         in_production 制作中 / completed 完成 / shipped 発送完了 / cancelled キャンセル
-- ---------------------------------------------------------------------
CREATE TABLE orders (
  id               TEXT PRIMARY KEY,
  order_number     TEXT NOT NULL UNIQUE,
  order_type       TEXT NOT NULL CHECK (order_type IN ('product','custom')),
  status           TEXT NOT NULL DEFAULT 'received' CHECK (status IN (
                     'received','checking_data','awaiting_customer','revising','approved',
                     'awaiting_payment','paid','in_production','completed','shipped','cancelled')),
  customer_name    TEXT NOT NULL,
  company_name     TEXT,
  email            TEXT NOT NULL,
  phone            TEXT NOT NULL,
  postal_code      TEXT NOT NULL,
  address          TEXT NOT NULL,
  ship_name        TEXT,
  ship_postal_code TEXT,
  ship_address     TEXT,
  ship_phone       TEXT,
  subtotal         INTEGER CHECK (subtotal >= 0),
  shipping_fee     INTEGER CHECK (shipping_fee >= 0),
  total            INTEGER CHECK (total >= 0),
  is_estimate      INTEGER NOT NULL DEFAULT 0,
  customer_note    TEXT,
  admin_note       TEXT,
  snapshot         TEXT NOT NULL DEFAULT '{}',
  cancelled_at     TEXT,
  created_at       TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now')),
  updated_at       TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now'))
);
CREATE INDEX orders_type_status_idx ON orders (order_type, status, created_at DESC);

CREATE TABLE order_items (
  id                TEXT PRIMARY KEY,
  order_id          TEXT NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
  product_id        TEXT REFERENCES products(id) ON DELETE SET NULL,
  custom_product_id TEXT REFERENCES custom_products(id) ON DELETE SET NULL,
  name_snapshot     TEXT NOT NULL,
  unit_price        INTEGER NOT NULL CHECK (unit_price >= 0),
  quantity          INTEGER NOT NULL DEFAULT 1 CHECK (quantity > 0),
  line_total        INTEGER NOT NULL CHECK (line_total >= 0),
  options_snapshot  TEXT NOT NULL DEFAULT '{}',
  created_at        TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now'))
);

CREATE TABLE product_reservations (
  id          TEXT PRIMARY KEY,
  product_id  TEXT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
  order_id    TEXT REFERENCES orders(id) ON DELETE SET NULL,
  status      TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active','released','converted','expired')),
  expires_at  TEXT NOT NULL,
  created_at  TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now')),
  released_at TEXT
);
CREATE UNIQUE INDEX product_reservations_one_active ON product_reservations (product_id) WHERE status = 'active';

-- ---------------------------------------------------------------------
-- 決済（ゼウス / テスト用モック）
-- ---------------------------------------------------------------------
CREATE TABLE payments (
  id              TEXT PRIMARY KEY,
  order_id        TEXT NOT NULL REFERENCES orders(id) ON DELETE RESTRICT,
  provider        TEXT NOT NULL CHECK (provider IN ('zeus','mock')),
  provider_txn_id TEXT,
  amount          INTEGER NOT NULL CHECK (amount >= 0),
  status          TEXT NOT NULL DEFAULT 'pending'
                  CHECK (status IN ('pending','succeeded','failed','cancelled','refunded')),
  verified_at     TEXT,
  raw_result      TEXT,            -- カード番号は含めない
  created_at      TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now')),
  updated_at      TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now')),
  UNIQUE (provider, provider_txn_id)
);

CREATE TABLE payment_events (
  id          TEXT PRIMARY KEY,
  provider    TEXT NOT NULL,
  event_key   TEXT NOT NULL,
  payload     TEXT NOT NULL,
  processed   INTEGER NOT NULL DEFAULT 0,
  received_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now')),
  UNIQUE (provider, event_key)
);

-- ---------------------------------------------------------------------
-- 受注制作（名刺入れ・カードケース）
-- ---------------------------------------------------------------------
CREATE TABLE custom_products (
  id                 TEXT PRIMARY KEY,
  slug               TEXT NOT NULL UNIQUE,
  name               TEXT NOT NULL,
  description        TEXT NOT NULL DEFAULT '',
  base_price         INTEGER CHECK (base_price IS NULL OR base_price >= 0),
  leather_colors     TEXT NOT NULL DEFAULT '[]',
  processing_options TEXT NOT NULL DEFAULT '[]',
  engraving_spec     TEXT NOT NULL DEFAULT '{}',
  lead_time_text     TEXT,
  is_active          INTEGER NOT NULL DEFAULT 0,
  sort_order         INTEGER NOT NULL DEFAULT 0,
  created_at         TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now')),
  updated_at         TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now'))
);

CREATE TABLE custom_product_images (
  id                TEXT PRIMARY KEY,
  custom_product_id TEXT NOT NULL REFERENCES custom_products(id) ON DELETE CASCADE,
  key_large         TEXT NOT NULL,
  key_small         TEXT NOT NULL,
  alt               TEXT NOT NULL DEFAULT '',
  photo_category    TEXT NOT NULL DEFAULT 'other' CHECK (photo_category IN
    ('closed','open','with_cards','side','surface','snap','name_sample','logo_sample','aging','other')),
  sort_order        INTEGER NOT NULL DEFAULT 0,
  is_main           INTEGER NOT NULL DEFAULT 0,
  created_at        TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now'))
);

CREATE TABLE custom_orders (
  id                  TEXT PRIMARY KEY,
  order_id            TEXT NOT NULL UNIQUE REFERENCES orders(id) ON DELETE CASCADE,
  custom_product_id   TEXT NOT NULL REFERENCES custom_products(id),
  leather_color       TEXT,
  quantity            INTEGER NOT NULL DEFAULT 1 CHECK (quantity > 0),
  processing_type     TEXT NOT NULL DEFAULT 'none'
                      CHECK (processing_type IN ('none','name','initial','shop_name','logo','name_and_logo')),
  engraving_text      TEXT,
  engraving_font      TEXT,
  engraving_position  TEXT,
  logo_position       TEXT,
  logo_size           TEXT,
  options_note        TEXT,
  rights_confirmed_at TEXT,
  quote_subtotal      INTEGER,
  quote_shipping      INTEGER,
  quote_total         INTEGER,
  due_date_text       TEXT,
  created_at          TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now')),
  updated_at          TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now'))
);

CREATE TABLE custom_order_files (
  id              TEXT PRIMARY KEY,
  custom_order_id TEXT NOT NULL REFERENCES custom_orders(id) ON DELETE CASCADE,
  r2_key          TEXT NOT NULL,           -- R2(PRIVATE)
  original_name   TEXT NOT NULL,
  mime_type       TEXT NOT NULL,
  size_bytes      INTEGER NOT NULL CHECK (size_bytes > 0 AND size_bytes <= 20971520), -- 20MB
  sha256          TEXT NOT NULL,
  validation      TEXT NOT NULL DEFAULT '{}',
  preview_key     TEXT,
  delete_after    TEXT,
  deleted_at      TEXT,
  created_at      TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now'))
);

CREATE TABLE custom_order_proofs (
  id               TEXT PRIMARY KEY,
  custom_order_id  TEXT NOT NULL REFERENCES custom_orders(id) ON DELETE CASCADE,
  version          INTEGER NOT NULL,
  image_keys       TEXT NOT NULL DEFAULT '[]',   -- R2(PRIVATE)
  message          TEXT,
  quote_snapshot   TEXT NOT NULL,
  token_hash       TEXT NOT NULL UNIQUE,         -- 確認URLトークンのSHA-256
  token_expires_at TEXT NOT NULL,
  sent_at          TEXT,
  superseded_at    TEXT,
  created_at       TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now')),
  UNIQUE (custom_order_id, version)
);

CREATE TABLE custom_order_approvals (
  id         TEXT PRIMARY KEY,
  proof_id   TEXT NOT NULL REFERENCES custom_order_proofs(id) ON DELETE CASCADE,
  action     TEXT NOT NULL CHECK (action IN ('approved','revision_requested')),
  comment    TEXT,
  snapshot   TEXT NOT NULL,
  ip_address TEXT,
  user_agent TEXT,
  acted_at   TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now'))
);

-- ---------------------------------------------------------------------
-- 発送
-- ---------------------------------------------------------------------
CREATE TABLE shipping_records (
  id               TEXT PRIMARY KEY,
  order_id         TEXT NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
  carrier          TEXT NOT NULL,
  tracking_number  TEXT,
  shipped_on       TEXT NOT NULL,
  address_snapshot TEXT NOT NULL,
  note             TEXT,
  notified_at      TEXT,
  created_at       TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now'))
);

-- ---------------------------------------------------------------------
-- ワークショップ
-- ---------------------------------------------------------------------
CREATE TABLE workshop_events (
  id           TEXT PRIMARY KEY,
  title        TEXT NOT NULL,
  item_name    TEXT NOT NULL,
  image_key    TEXT,
  event_date   TEXT NOT NULL,
  start_time   TEXT,
  end_time     TEXT,
  place        TEXT NOT NULL,
  fee          INTEGER CHECK (fee IS NULL OR fee >= 0),
  capacity     INTEGER NOT NULL CHECK (capacity > 0),
  accepting    INTEGER NOT NULL DEFAULT 1,
  description  TEXT NOT NULL DEFAULT '',
  is_published INTEGER NOT NULL DEFAULT 0,
  created_at   TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now')),
  updated_at   TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now'))
);

CREATE TABLE workshop_reservations (
  id         TEXT PRIMARY KEY,
  event_id   TEXT NOT NULL REFERENCES workshop_events(id) ON DELETE RESTRICT,
  name       TEXT NOT NULL,
  email      TEXT NOT NULL,
  phone      TEXT NOT NULL,
  people     INTEGER NOT NULL CHECK (people BETWEEN 1 AND 20),
  note       TEXT,
  status     TEXT NOT NULL DEFAULT 'confirmed' CHECK (status IN ('confirmed','cancelled')),
  created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now'))
);

-- ---------------------------------------------------------------------
-- お問い合わせ・オーダーメイド相談
-- ---------------------------------------------------------------------
CREATE TABLE contact_messages (
  id         TEXT PRIMARY KEY,
  kind       TEXT NOT NULL DEFAULT 'contact' CHECK (kind IN ('contact','custom_request')),
  name       TEXT NOT NULL,
  email      TEXT NOT NULL,
  phone      TEXT,
  subject    TEXT,
  body       TEXT NOT NULL,
  handled    INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now'))
);

-- ---------------------------------------------------------------------
-- 革の経年変化（同一品の実記録のみ公開）
-- ---------------------------------------------------------------------
CREATE TABLE leather_aging_photos (
  id             TEXT PRIMARY KEY,
  title          TEXT NOT NULL,
  before_key     TEXT NOT NULL,
  after_key      TEXT NOT NULL,
  period_label   TEXT,
  description    TEXT,
  is_real_record INTEGER NOT NULL DEFAULT 0,
  is_published   INTEGER NOT NULL DEFAULT 0,
  sort_order     INTEGER NOT NULL DEFAULT 0,
  created_at     TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now'))
);

-- ---------------------------------------------------------------------
-- 操作履歴
-- ---------------------------------------------------------------------
CREATE TABLE audit_logs (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  actor      TEXT,
  action     TEXT NOT NULL,
  target     TEXT,
  detail     TEXT,
  created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now'))
);

-- =====================================================================
-- 初期データ（価格・送料・販売者情報は未設定。管理画面で設定する）
-- =====================================================================
INSERT INTO categories (id, slug, name, sort_order) VALUES
  ('cat-keyholder', 'keyholder', 'レザーキーホルダー', 10),
  ('cat-coin-case', 'coin-case', 'コインケース', 20),
  ('cat-card-case', 'card-case', 'カードケース', 30),
  ('cat-other', 'other', 'その他革小物', 90);

INSERT INTO site_settings (key, value) VALUES
  ('home.hero', '{"catch":"おっさんが、ひとつひとつ作っとります。","sub":"使うほどに、ええ味が出てくる革小物。","imageKey":null}'),
  ('sales.enabled', 'false'); -- すべての機能が完成するまで本番販売は無効
