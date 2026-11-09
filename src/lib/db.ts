import type { Category, InstagramPost, Product, ProductImage, ProductStatus, ShippingProfile } from "./types";
import { PUBLIC_STATUSES } from "./types";

const PRODUCT_SELECT = `
  SELECT p.*, c.name AS category_name, c.slug AS category_slug,
         s.name AS shipping_name, s.fee AS shipping_fee
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    LEFT JOIN shipping_profiles s ON s.id = p.shipping_profile_id`;

type ProductRow = Omit<Product, "images">;

async function attachImages(db: D1Database, rows: ProductRow[]): Promise<Product[]> {
  if (rows.length === 0) return [];
  const ids = rows.map((r) => r.id);
  const { results } = await db
    .prepare(
      `SELECT * FROM product_images WHERE product_id IN (${ids.map(() => "?").join(",")})
       ORDER BY is_main DESC, sort_order ASC, created_at ASC`,
    )
    .bind(...ids)
    .all<ProductImage & { product_id: string }>();
  const byProduct = new Map<string, ProductImage[]>();
  for (const img of results) {
    const list = byProduct.get(img.product_id) ?? [];
    list.push(img);
    byProduct.set(img.product_id, list);
  }
  return rows.map((r) => ({ ...r, images: byProduct.get(r.id) ?? [] }));
}

// ---------------------------------------------------------------- 公開側

export async function listPublicProducts(
  db: D1Database,
  opts: { statuses?: ProductStatus[]; categorySlug?: string; limit?: number } = {},
): Promise<Product[]> {
  const statuses = (opts.statuses ?? PUBLIC_STATUSES).filter((s) => PUBLIC_STATUSES.includes(s));
  const where = [`p.status IN (${statuses.map(() => "?").join(",")})`];
  const binds: unknown[] = [...statuses];
  if (opts.categorySlug) {
    where.push("c.slug = ?");
    binds.push(opts.categorySlug);
  }
  const order =
    statuses.length === 1 && statuses[0] === "sold_out"
      ? "p.sold_at DESC"
      : "COALESCE(p.published_at, p.created_at) DESC";
  const { results } = await db
    .prepare(`${PRODUCT_SELECT} WHERE ${where.join(" AND ")} ORDER BY ${order} LIMIT ?`)
    .bind(...binds, opts.limit ?? 200)
    .all<ProductRow>();
  return attachImages(db, results);
}

export async function getPublicProduct(db: D1Database, slug: string): Promise<Product | null> {
  const row = await db
    .prepare(`${PRODUCT_SELECT} WHERE p.slug = ? AND p.status IN ('on_sale','reserved','sold_out')`)
    .bind(slug)
    .first<ProductRow>();
  if (!row) return null;
  return (await attachImages(db, [row]))[0];
}

export async function listCategories(db: D1Database): Promise<Category[]> {
  const { results } = await db.prepare("SELECT * FROM categories ORDER BY sort_order, name").all<Category>();
  return results;
}

export async function getSetting<T>(db: D1Database, key: string, fallback: T): Promise<T> {
  const row = await db.prepare("SELECT value FROM site_settings WHERE key = ?").bind(key).first<{ value: string }>();
  if (!row) return fallback;
  try {
    return JSON.parse(row.value) as T;
  } catch {
    return fallback;
  }
}

export async function setSetting(db: D1Database, key: string, value: unknown, actor: string) {
  await db
    .prepare(
      `INSERT INTO site_settings (key, value, updated_by) VALUES (?, ?, ?)
       ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_by = excluded.updated_by,
         updated_at = strftime('%Y-%m-%dT%H:%M:%fZ','now')`,
    )
    .bind(key, JSON.stringify(value), actor)
    .run();
}

export type HeroSetting = { catch: string; sub: string; imageKey: string | null };
export type AboutSetting = { body: string | null };

export async function listInstagramPosts(db: D1Database, limit = 6): Promise<InstagramPost[]> {
  const { results } = await db
    .prepare("SELECT * FROM instagram_posts WHERE image_key IS NOT NULL ORDER BY posted_at DESC LIMIT ?")
    .bind(limit)
    .all<InstagramPost>();
  return results;
}

// ---------------------------------------------------------------- 管理側

export async function listAllProducts(db: D1Database, status?: string, q?: string): Promise<Product[]> {
  const where: string[] = [];
  const binds: unknown[] = [];
  if (status) {
    where.push("p.status = ?");
    binds.push(status);
  }
  if (q) {
    where.push("(p.name LIKE ? OR p.code LIKE ?)");
    binds.push(`%${q}%`, `%${q}%`);
  }
  const { results } = await db
    .prepare(`${PRODUCT_SELECT} ${where.length ? "WHERE " + where.join(" AND ") : ""} ORDER BY p.updated_at DESC LIMIT 500`)
    .bind(...binds)
    .all<ProductRow>();
  return attachImages(db, results);
}

export async function getProductById(db: D1Database, id: string): Promise<Product | null> {
  const row = await db.prepare(`${PRODUCT_SELECT} WHERE p.id = ?`).bind(id).first<ProductRow>();
  if (!row) return null;
  return (await attachImages(db, [row]))[0];
}

export async function listShippingProfiles(db: D1Database, activeOnly = false): Promise<ShippingProfile[]> {
  const { results } = await db
    .prepare(`SELECT * FROM shipping_profiles ${activeOnly ? "WHERE is_active = 1" : ""} ORDER BY created_at`)
    .all<ShippingProfile>();
  return results;
}

export async function audit(db: D1Database, actor: string, action: string, target?: string, detail?: unknown) {
  await db
    .prepare("INSERT INTO audit_logs (actor, action, target, detail) VALUES (?, ?, ?, ?)")
    .bind(actor, action, target ?? null, detail === undefined ? null : JSON.stringify(detail))
    .run();
}

export async function dashboardCounts(db: D1Database) {
  const one = async (sql: string) => (await db.prepare(sql).first<{ n: number }>())?.n ?? 0;
  const [onSale, soldOut, draft, newOrders, awaitingPayment, unshipped, customOrders, workshop] = await Promise.all([
    one("SELECT COUNT(*) n FROM products WHERE status IN ('on_sale','reserved')"),
    one("SELECT COUNT(*) n FROM products WHERE status = 'sold_out'"),
    one("SELECT COUNT(*) n FROM products WHERE status = 'draft'"),
    one("SELECT COUNT(*) n FROM orders WHERE status = 'received'"),
    one("SELECT COUNT(*) n FROM orders WHERE status = 'awaiting_payment'"),
    one("SELECT COUNT(*) n FROM orders WHERE status IN ('paid','in_production','completed')"),
    one("SELECT COUNT(*) n FROM orders WHERE order_type = 'custom' AND status NOT IN ('shipped','cancelled')"),
    one("SELECT COUNT(*) n FROM workshop_reservations WHERE status = 'confirmed'"),
  ]);
  return { onSale, soldOut, draft, newOrders, awaitingPayment, unshipped, customOrders, workshop };
}
