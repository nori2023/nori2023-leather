-- 購入の仕組み（ゼウス決済）用に注文へ項目を追加
ALTER TABLE orders
  ADD COLUMN access_token CHAR(40) NULL AFTER order_number,
  ADD COLUMN zeus_xid VARCHAR(120) NULL,
  ADD COLUMN pay_expires_at DATETIME NULL,
  ADD COLUMN paid_at DATETIME NULL,
  ADD COLUMN shipped_at DATETIME NULL,
  ADD UNIQUE KEY uq_orders_token (access_token);

-- 決済の設定（最初は「停止」）
INSERT IGNORE INTO site_settings (`key`, value, updated_by) VALUES
  ('payment.zeus', JSON_OBJECT('mode', 'off', 'endpoint', 'sandbox', 'clientip', '', 'key', ''), 'system')
