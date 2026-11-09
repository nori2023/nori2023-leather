-- =====================================================================
-- おっさんのレザークラフト（Nori2023） 初期スキーマ（MySQL / MariaDB）
--   1点もの（products）と受注制作（custom_*）は分け、注文・決済・発送は共通化
--   確定時点の内容は snapshot（JSON）で保存
--   二重販売防止：トランザクション＋SELECT ... FOR UPDATE（STEP 3）
--   カード番号は一切保存しない
--   ※ このファイルの文字列の中にセミコロンを書かないこと（文の区切りに使うため）
-- =====================================================================

CREATE TABLE admin_users (
  id            CHAR(36)     NOT NULL PRIMARY KEY,
  email         VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  last_login_at DATETIME     NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
  id           BIGINT AUTO_INCREMENT PRIMARY KEY,
  ip           VARCHAR(45)  NOT NULL,
  email        VARCHAR(255) NOT NULL,
  succeeded    TINYINT(1)   NOT NULL DEFAULT 0,
  attempted_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (ip, attempted_at),
  INDEX (email, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE site_settings (
  `key`      VARCHAR(100) NOT NULL PRIMARY KEY,
  value      TEXT         NOT NULL,
  updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  updated_by VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------- 1点もの完成品
CREATE TABLE categories (
  id         CHAR(36)    NOT NULL PRIMARY KEY,
  slug       VARCHAR(80) NOT NULL UNIQUE,
  name       VARCHAR(80) NOT NULL,
  sort_order INT         NOT NULL DEFAULT 0,
  created_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE shipping_profiles (
  id         CHAR(36)     NOT NULL PRIMARY KEY,
  name       VARCHAR(100) NOT NULL,
  fee        INT          NULL,
  note       VARCHAR(255) NULL,
  is_active  TINYINT(1)   NOT NULL DEFAULT 1,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- status: draft=非公開 / on_sale=販売中 / reserved=購入手続き中 / sold_out=SOLD OUT / suspended=販売停止
CREATE TABLE products (
  id                  CHAR(36)     NOT NULL PRIMARY KEY,
  code                VARCHAR(40)  NOT NULL UNIQUE,
  slug                VARCHAR(80)  NOT NULL UNIQUE,
  name                VARCHAR(120) NOT NULL,
  category_id         CHAR(36)     NULL,
  description         TEXT         NOT NULL,
  material            VARCHAR(200) NULL,
  size                VARCHAR(200) NULL,
  color               VARCHAR(100) NULL,
  price               INT          NOT NULL,
  shipping_profile_id CHAR(36)     NULL,
  status              ENUM('draft','on_sale','reserved','sold_out','suspended') NOT NULL DEFAULT 'draft',
  stock               TINYINT      NOT NULL DEFAULT 1,
  sold_at             DATETIME     NULL,
  sold_note           TEXT         NULL,
  published_at        DATETIME     NULL,
  created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (status, published_at),
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_products_shipping FOREIGN KEY (shipping_profile_id) REFERENCES shipping_profiles(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_images (
  id          CHAR(36)     NOT NULL PRIMARY KEY,
  product_id  CHAR(36)     NOT NULL,
  key_large   VARCHAR(255) NOT NULL,
  key_small   VARCHAR(255) NOT NULL,
  alt         VARCHAR(200) NOT NULL DEFAULT '',
  width       INT          NULL,
  height      INT          NULL,
  sort_order  INT          NOT NULL DEFAULT 0,
  is_main     TINYINT(1)   NOT NULL DEFAULT 0,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (product_id, sort_order),
  CONSTRAINT fk_images_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------- 注文（完成品・受注制作 共通）
CREATE TABLE orders (
  id               CHAR(36)     NOT NULL PRIMARY KEY,
  order_number     VARCHAR(40)  NOT NULL UNIQUE,
  order_type       ENUM('product','custom') NOT NULL,
  status           ENUM('received','checking_data','awaiting_customer','revising','approved',
                        'awaiting_payment','paid','in_production','completed','shipped','cancelled') NOT NULL DEFAULT 'received',
  customer_name    VARCHAR(100) NOT NULL,
  company_name     VARCHAR(150) NULL,
  email            VARCHAR(255) NOT NULL,
  phone            VARCHAR(30)  NOT NULL,
  postal_code      VARCHAR(10)  NOT NULL,
  address          VARCHAR(255) NOT NULL,
  ship_name        VARCHAR(100) NULL,
  ship_postal_code VARCHAR(10)  NULL,
  ship_address     VARCHAR(255) NULL,
  ship_phone       VARCHAR(30)  NULL,
  subtotal         INT          NULL,
  shipping_fee     INT          NULL,
  total            INT          NULL,
  is_estimate      TINYINT(1)   NOT NULL DEFAULT 0,
  customer_note    TEXT         NULL,
  admin_note       TEXT         NULL,
  snapshot         JSON         NULL,
  cancelled_at     DATETIME     NULL,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (order_type, status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE custom_products (
  id                 CHAR(36)     NOT NULL PRIMARY KEY,
  slug               VARCHAR(80)  NOT NULL UNIQUE,
  name               VARCHAR(120) NOT NULL,
  description        TEXT         NOT NULL,
  base_price         INT          NULL,
  leather_colors     JSON         NULL,
  processing_options JSON         NULL,
  engraving_spec     JSON         NULL,
  lead_time_text     VARCHAR(200) NULL,
  is_active          TINYINT(1)   NOT NULL DEFAULT 0,
  sort_order         INT          NOT NULL DEFAULT 0,
  created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_items (
  id                CHAR(36)     NOT NULL PRIMARY KEY,
  order_id          CHAR(36)     NOT NULL,
  product_id        CHAR(36)     NULL,
  custom_product_id CHAR(36)     NULL,
  name_snapshot     VARCHAR(150) NOT NULL,
  unit_price        INT          NOT NULL,
  quantity          INT          NOT NULL DEFAULT 1,
  line_total        INT          NOT NULL,
  options_snapshot  JSON         NULL,
  created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
  CONSTRAINT fk_items_custom FOREIGN KEY (custom_product_id) REFERENCES custom_products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_reservations (
  id          CHAR(36)  NOT NULL PRIMARY KEY,
  product_id  CHAR(36)  NOT NULL,
  order_id    CHAR(36)  NULL,
  status      ENUM('active','released','converted','expired') NOT NULL DEFAULT 'active',
  expires_at  DATETIME  NOT NULL,
  created_at  DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP,
  released_at DATETIME  NULL,
  INDEX (product_id, status),
  CONSTRAINT fk_res_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_res_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
  id              CHAR(36)     NOT NULL PRIMARY KEY,
  order_id        CHAR(36)     NOT NULL,
  provider        ENUM('zeus','mock') NOT NULL,
  provider_txn_id VARCHAR(100) NULL,
  amount          INT          NOT NULL,
  status          ENUM('pending','succeeded','failed','cancelled','refunded') NOT NULL DEFAULT 'pending',
  verified_at     DATETIME     NULL,
  raw_result      JSON         NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_provider_txn (provider, provider_txn_id),
  CONSTRAINT fk_pay_order FOREIGN KEY (order_id) REFERENCES orders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payment_events (
  id          CHAR(36)     NOT NULL PRIMARY KEY,
  provider    VARCHAR(20)  NOT NULL,
  event_key   VARCHAR(150) NOT NULL,
  payload     JSON         NOT NULL,
  processed   TINYINT(1)   NOT NULL DEFAULT 0,
  received_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_event (provider, event_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------- 受注制作（名刺入れ・カードケース）
CREATE TABLE custom_product_images (
  id                CHAR(36)     NOT NULL PRIMARY KEY,
  custom_product_id CHAR(36)     NOT NULL,
  key_large         VARCHAR(255) NOT NULL,
  key_small         VARCHAR(255) NOT NULL,
  alt               VARCHAR(200) NOT NULL DEFAULT '',
  photo_category    ENUM('closed','open','with_cards','side','surface','snap','name_sample','logo_sample','aging','other') NOT NULL DEFAULT 'other',
  sort_order        INT          NOT NULL DEFAULT 0,
  is_main           TINYINT(1)   NOT NULL DEFAULT 0,
  created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_cpi_product FOREIGN KEY (custom_product_id) REFERENCES custom_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE custom_orders (
  id                  CHAR(36)     NOT NULL PRIMARY KEY,
  order_id            CHAR(36)     NOT NULL UNIQUE,
  custom_product_id   CHAR(36)     NOT NULL,
  leather_color       VARCHAR(50)  NULL,
  quantity            INT          NOT NULL DEFAULT 1,
  processing_type     ENUM('none','name','initial','shop_name','logo','name_and_logo') NOT NULL DEFAULT 'none',
  engraving_text      VARCHAR(200) NULL,
  engraving_font      VARCHAR(100) NULL,
  engraving_position  VARCHAR(100) NULL,
  logo_position       VARCHAR(100) NULL,
  logo_size           VARCHAR(100) NULL,
  options_note        TEXT         NULL,
  rights_confirmed_at DATETIME     NULL,
  quote_subtotal      INT          NULL,
  quote_shipping      INT          NULL,
  quote_total         INT          NULL,
  due_date_text       VARCHAR(100) NULL,
  created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_co_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_co_product FOREIGN KEY (custom_product_id) REFERENCES custom_products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 入稿ファイルは公開フォルダの外（storage/）に保存する（STEP 4）
CREATE TABLE custom_order_files (
  id              CHAR(36)     NOT NULL PRIMARY KEY,
  custom_order_id CHAR(36)     NOT NULL,
  storage_path    VARCHAR(255) NOT NULL,
  original_name   VARCHAR(255) NOT NULL,
  mime_type       VARCHAR(100) NOT NULL,
  size_bytes      INT          NOT NULL,
  sha256          CHAR(64)     NOT NULL,
  validation      JSON         NULL,
  preview_path    VARCHAR(255) NULL,
  delete_after    DATE         NULL,
  deleted_at      DATETIME     NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_cof_order FOREIGN KEY (custom_order_id) REFERENCES custom_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE custom_order_proofs (
  id               CHAR(36)    NOT NULL PRIMARY KEY,
  custom_order_id  CHAR(36)    NOT NULL,
  version          INT         NOT NULL,
  image_paths      JSON        NULL,
  message          TEXT        NULL,
  quote_snapshot   JSON        NOT NULL,
  token_hash       CHAR(64)    NOT NULL UNIQUE,
  token_expires_at DATETIME    NOT NULL,
  sent_at          DATETIME    NULL,
  superseded_at    DATETIME    NULL,
  created_at       DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_proof_version (custom_order_id, version),
  CONSTRAINT fk_cop_order FOREIGN KEY (custom_order_id) REFERENCES custom_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE custom_order_approvals (
  id         CHAR(36)     NOT NULL PRIMARY KEY,
  proof_id   CHAR(36)     NOT NULL,
  action     ENUM('approved','revision_requested') NOT NULL,
  comment    TEXT         NULL,
  snapshot   JSON         NOT NULL,
  ip_address VARCHAR(45)  NULL,
  user_agent VARCHAR(255) NULL,
  acted_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_coa_proof FOREIGN KEY (proof_id) REFERENCES custom_order_proofs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------- 発送
CREATE TABLE shipping_records (
  id               CHAR(36)     NOT NULL PRIMARY KEY,
  order_id         CHAR(36)     NOT NULL,
  carrier          VARCHAR(50)  NOT NULL,
  tracking_number  VARCHAR(100) NULL,
  shipped_on       DATE         NOT NULL,
  address_snapshot JSON         NOT NULL,
  note             TEXT         NULL,
  notified_at      DATETIME     NULL,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ship_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------- ワークショップ
CREATE TABLE workshop_events (
  id           CHAR(36)     NOT NULL PRIMARY KEY,
  title        VARCHAR(150) NOT NULL,
  item_name    VARCHAR(100) NOT NULL,
  image_key    VARCHAR(255) NULL,
  event_date   DATE         NOT NULL,
  start_time   TIME         NULL,
  end_time     TIME         NULL,
  place        VARCHAR(200) NOT NULL,
  fee          INT          NULL,
  capacity     INT          NOT NULL,
  accepting    TINYINT(1)   NOT NULL DEFAULT 1,
  description  TEXT         NOT NULL,
  is_published TINYINT(1)   NOT NULL DEFAULT 0,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE workshop_reservations (
  id         CHAR(36)     NOT NULL PRIMARY KEY,
  event_id   CHAR(36)     NOT NULL,
  name       VARCHAR(100) NOT NULL,
  email      VARCHAR(255) NOT NULL,
  phone      VARCHAR(30)  NOT NULL,
  people     INT          NOT NULL,
  note       TEXT         NULL,
  status     ENUM('confirmed','cancelled') NOT NULL DEFAULT 'confirmed',
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_wr_event FOREIGN KEY (event_id) REFERENCES workshop_events(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------- お問い合わせ・経年変化・Instagram・操作履歴
CREATE TABLE contact_messages (
  id         CHAR(36)     NOT NULL PRIMARY KEY,
  kind       ENUM('contact','custom_request') NOT NULL DEFAULT 'contact',
  name       VARCHAR(100) NOT NULL,
  email      VARCHAR(255) NOT NULL,
  phone      VARCHAR(30)  NULL,
  subject    VARCHAR(200) NULL,
  body       TEXT         NOT NULL,
  handled    TINYINT(1)   NOT NULL DEFAULT 0,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE leather_aging_photos (
  id             CHAR(36)     NOT NULL PRIMARY KEY,
  title          VARCHAR(150) NOT NULL,
  before_key     VARCHAR(255) NOT NULL,
  after_key      VARCHAR(255) NOT NULL,
  period_label   VARCHAR(50)  NULL,
  description    TEXT         NULL,
  is_real_record TINYINT(1)   NOT NULL DEFAULT 0,
  is_published   TINYINT(1)   NOT NULL DEFAULT 0,
  sort_order     INT          NOT NULL DEFAULT 0,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE integration_tokens (
  name         VARCHAR(50) NOT NULL PRIMARY KEY,
  token        TEXT        NOT NULL,
  expires_at   DATETIME    NULL,
  refreshed_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE instagram_posts (
  id         VARCHAR(50)  NOT NULL PRIMARY KEY,
  permalink  VARCHAR(255) NOT NULL,
  caption    TEXT         NULL,
  media_type VARCHAR(30)  NOT NULL,
  posted_at  DATETIME     NOT NULL,
  image_key  VARCHAR(255) NULL,
  synced_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (posted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
  id         BIGINT AUTO_INCREMENT PRIMARY KEY,
  actor      VARCHAR(255) NULL,
  action     VARCHAR(100) NOT NULL,
  target     VARCHAR(255) NULL,
  detail     TEXT         NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------- 初期データ
INSERT INTO categories (id, slug, name, sort_order) VALUES
  ('cat-keyholder', 'keyholder', 'レザーキーホルダー', 10),
  ('cat-coin-case', 'coin-case', 'コインケース', 20),
  ('cat-card-case', 'card-case', 'カードケース', 30),
  ('cat-other', 'other', 'その他革小物', 90);

INSERT INTO site_settings (`key`, value) VALUES
  ('home.hero', '{"catch":"おっさんが、ひとつひとつ作っとります。","sub":"使やぁ使うほど、ええ味が出てくる革小物じゃけぇ。","imageKey":null}'),
  ('sales.enabled', 'false'),
  ('instagram.sync', '{"lastSyncAt":null,"lastError":null}');
