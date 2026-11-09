import { Hono, type Context } from "hono";
import { csrf } from "hono/csrf";
import { z } from "zod";
import type { AppEnv } from "../env";
import { requireAdmin } from "../lib/auth";
import {
  type AboutSetting,
  type HeroSetting,
  audit,
  dashboardCounts,
  getProductById,
  getSetting,
  listAllProducts,
  listCategories,
  listShippingProfiles,
  setSetting,
} from "../lib/db";
import { formatDateTime, formatPrice, mediaUrl, newId, nowIso } from "../lib/format";
import { getInstagramToken, saveInstagramToken, syncInstagram } from "../lib/instagram";
import { MAX_MEDIA_BYTES, deleteMedia, putMedia } from "../lib/media";
import { SITE } from "../lib/site";
import { PHOTO_RULE_TEXT, STATUS_LABEL, photoCountOk, type Product, type ProductStatus } from "../lib/types";
import { AdminLayout, Field, btnDanger, btnGhost, btnPrimary, card, input } from "../views/admin-layout";

export const adminRoutes = new Hono<AppEnv>();

adminRoutes.use("*", requireAdmin);
adminRoutes.use("*", csrf());
adminRoutes.use("*", async (c, next) => {
  await next();
  c.header("Cache-Control", "no-store");
  c.header("X-Frame-Options", "DENY");
});

const page = (c: Context<AppEnv>, title: string, body: unknown, opts: { error?: string } = {}) =>
  c.html(
    <AdminLayout
      title={title}
      path={new URL(c.req.url).pathname}
      email={c.get("adminEmail")}
      notice={c.req.query("notice")}
      error={opts.error}
    >
      {body as never}
    </AdminLayout>,
  );

// ================================================================== ダッシュボード
adminRoutes.get("/", async (c) => {
  const n = await dashboardCounts(c.env.DB);
  const tiles: [string, number, string?][] = [
    ["販売中の商品", n.onSale, "/admin/products?status=on_sale"],
    ["SOLD OUT", n.soldOut, "/admin/products?status=sold_out"],
    ["非公開（下書き）", n.draft, "/admin/products?status=draft"],
    ["新規注文", n.newOrders],
    ["決済待ち", n.awaitingPayment],
    ["未発送", n.unshipped],
    ["名刺入れ受注", n.customOrders],
    ["ワークショップ申込", n.workshop],
  ];
  return page(
    c,
    "ダッシュボード",
    <>
      <ul class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        {tiles.map(([t, v, href]) => (
          <li>
            {href ? (
              <a href={href} class={`${card} block hover:border-camel`}>
                <p class="text-xs text-muted">{t}</p>
                <p class="mt-1 font-serif text-3xl text-leather">{v}</p>
              </a>
            ) : (
              <div class={card}>
                <p class="text-xs text-muted">{t}</p>
                <p class="mt-1 font-serif text-3xl text-leather">{v}</p>
              </div>
            )}
          </li>
        ))}
      </ul>
      <div class="mt-6 flex flex-wrap gap-3">
        <a href="/admin/products/new" class={btnPrimary}>＋ 完成品を登録する</a>
        <a href="/admin/settings" class={btnGhost}>ページ設定</a>
      </div>
      <p class="mt-8 rounded border border-amber-300 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900">
        オンライン購入（STEP 3）は未実装のため、販売中の商品も購入ボタンは押せません。注文・受注制作・ワークショップの件数は、各機能の完成後に表示されます。
      </p>
    </>,
  );
});

// ================================================================== 完成品
adminRoutes.get("/products", async (c) => {
  const status = c.req.query("status") || undefined;
  const q = c.req.query("q")?.trim() || undefined;
  const products = await listAllProducts(c.env.DB, status, q);
  return page(
    c,
    "完成品（1点もの）",
    <>
      <div class="mb-4 flex flex-wrap items-end gap-3">
        <a href="/admin/products/new" class={btnPrimary}>＋ 新しく登録</a>
        <form method="get" class="flex flex-1 flex-wrap items-end gap-2">
          <select name="status" class={`${input} !mt-0 !w-auto`} aria-label="販売状態で絞り込み">
            <option value="">すべての状態</option>
            {(Object.keys(STATUS_LABEL) as ProductStatus[]).map((s) => (
              <option value={s} selected={s === status}>{STATUS_LABEL[s]}</option>
            ))}
          </select>
          <input name="q" value={q ?? ""} placeholder="商品名・管理番号" class={`${input} !mt-0 !w-auto flex-1`} aria-label="検索" />
          <button class={btnGhost}>絞り込む</button>
        </form>
      </div>
      {products.length === 0 ? (
        <p class={`${card} text-center text-muted`}>商品はまだありません。「＋ 新しく登録」から登録してください。</p>
      ) : (
        <ul class="space-y-2">
          {products.map((p) => (
            <li>
              <a href={`/admin/products/${p.id}`} class={`${card} flex items-center gap-3 !p-3 hover:border-camel`}>
                <div class="relative h-16 w-16 shrink-0 overflow-hidden rounded bg-sand">
                  {p.images[0] && <img src={mediaUrl(p.images[0].key_small)} alt="" class="h-full w-full object-cover" loading="lazy" />}
                </div>
                <div class="min-w-0 flex-1">
                  <p class="truncate font-medium">{p.name}</p>
                  <p class="text-xs text-muted">{p.code}　{formatPrice(p.price)}　写真{p.images.length}枚</p>
                </div>
                <StatusBadge status={p.status} />
              </a>
            </li>
          ))}
        </ul>
      )}
    </>,
  );
});

function StatusBadge({ status }: { status: ProductStatus }) {
  const color: Record<ProductStatus, string> = {
    draft: "bg-gray-100 text-gray-700",
    on_sale: "bg-green-100 text-green-800",
    reserved: "bg-amber-100 text-amber-900",
    sold_out: "bg-charcoal text-ivory",
    suspended: "bg-red-100 text-red-800",
  };
  return <span class={`shrink-0 rounded px-2 py-1 text-xs ${color[status]}`}>{STATUS_LABEL[status]}</span>;
}

const productSchema = z.object({
  code: z.string().trim().min(1, "商品管理番号を入力してください").max(40),
  name: z.string().trim().min(1, "商品名を入力してください").max(120),
  slug: z
    .string()
    .trim()
    .max(80)
    .regex(/^[a-z0-9-]*$/, "URL用の名前は半角英小文字・数字・ハイフンのみ使えます"),
  category_id: z.string().trim().max(60),
  description: z.string().max(5000),
  material: z.string().trim().max(200),
  size: z.string().trim().max(200),
  color: z.string().trim().max(100),
  price: z.coerce.number({ error: "価格は数字で入力してください" }).int("価格は整数で入力してください").min(0).max(10_000_000),
  shipping_profile_id: z.string().trim().max(60),
  status: z.enum(["draft", "on_sale", "sold_out", "suspended"]),
  sold_note: z.string().max(300, "販売終了後のコメントは300文字以内にしてください"),
});
type ProductForm = z.infer<typeof productSchema>;

const toSlug = (code: string) =>
  code.toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "") || `item-${Date.now().toString(36)}`;

async function productFormPage(c: Context<AppEnv>, product: Product | null, values?: Partial<ProductForm>, error?: string) {
  const [categories, shipping] = await Promise.all([listCategories(c.env.DB), listShippingProfiles(c.env.DB)]);
  const v: Partial<ProductForm> & { price?: number | string } = values ?? (product ? { ...product, status: product.status === "reserved" ? "on_sale" : product.status } as never : { status: "draft" });
  const isNew = !product;
  return page(
    c,
    isNew ? "完成品を登録" : `編集：${product!.name}`,
    <>
      {!isNew && (
        <div class="mb-4 flex flex-wrap items-center gap-3 text-sm">
          <StatusBadge status={product!.status} />
          {["on_sale", "reserved", "sold_out"].includes(product!.status) && (
            <a href={`/shop/${product!.slug}`} target="_blank" class="text-leather underline">公開ページを見る ↗</a>
          )}
          <span class="text-muted">最終更新 {formatDateTime(product!.updated_at)}</span>
        </div>
      )}
      {!isNew && product!.status === "reserved" && (
        <p class="mb-4 rounded border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
          この商品は購入手続き中です。決済の結果が出るまで、販売状態は変更できません。
        </p>
      )}

      {!isNew && <ImageManager product={product!} />}

      <form method="post" action={isNew ? "/admin/products" : `/admin/products/${product!.id}`} class={`${card} mt-5 grid gap-5 sm:grid-cols-2`}>
        <h2 class="font-serif text-lg text-leather sm:col-span-2">商品情報</h2>
        <Field name="code" title="商品管理番号 *" hint="例：KH-0001（ほかの商品と重複しない番号）">
          <input id="code" name="code" required value={v.code ?? ""} class={input} />
        </Field>
        <Field name="name" title="商品名 *">
          <input id="name" name="name" required value={v.name ?? ""} class={input} />
        </Field>
        <Field name="category_id" title="カテゴリー">
          <select id="category_id" name="category_id" class={input}>
            <option value="">（未設定）</option>
            {categories.map((x) => (
              <option value={x.id} selected={x.id === v.category_id}>{x.name}</option>
            ))}
          </select>
        </Field>
        <Field name="price" title="税込販売価格（円） *">
          <input id="price" name="price" type="number" inputmode="numeric" min="0" step="1" required value={v.price != null ? String(v.price) : ""} class={input} />
        </Field>
        <div class="sm:col-span-2">
          <Field name="description" title="説明文" hint="改行はそのまま表示されます">
            <textarea id="description" name="description" rows={7} class={input}>{v.description ?? ""}</textarea>
          </Field>
        </div>
        <Field name="material" title="使用素材"><input id="material" name="material" value={v.material ?? ""} class={input} placeholder="例：ヌメ革（栃木レザー）" /></Field>
        <Field name="size" title="サイズ"><input id="size" name="size" value={v.size ?? ""} class={input} placeholder="例：約 縦7cm × 横9cm" /></Field>
        <Field name="color" title="革の色"><input id="color" name="color" value={v.color ?? ""} class={input} placeholder="例：ナチュラル" /></Field>
        <Field name="shipping_profile_id" title="送料設定" hint="送料は「送料設定」で登録します">
          <select id="shipping_profile_id" name="shipping_profile_id" class={input}>
            <option value="">（未設定：購入手続き画面でご案内）</option>
            {shipping.map((s) => (
              <option value={s.id} selected={s.id === v.shipping_profile_id}>
                {s.name}{s.fee != null ? `（${formatPrice(s.fee)}）` : "（金額未定）"}{s.is_active ? "" : "【無効】"}
              </option>
            ))}
          </select>
        </Field>
        <Field name="slug" title="URL用の名前" hint="空欄なら商品管理番号から自動作成。公開後は変更しないでください">
          <input id="slug" name="slug" value={v.slug ?? ""} class={input} placeholder="例：kh-0001" />
        </Field>
        <Field name="status" title="販売状態">
          <select id="status" name="status" class={input} disabled={product?.status === "reserved"}>
            <option value="draft" selected={v.status === "draft"}>非公開（下書き）</option>
            <option value="on_sale" selected={v.status === "on_sale"}>販売中（公開）</option>
            <option value="sold_out" selected={v.status === "sold_out"}>SOLD OUT（作品集に掲載）</option>
            <option value="suspended" selected={v.status === "suspended"}>販売停止（非公開）</option>
          </select>
          {!isNew && !photoCountOk(product!.images.length) && (
            <p class="mt-1 text-xs text-red-700">{PHOTO_RULE_TEXT}（現在{product!.images.length}枚）</p>
          )}
        </Field>
        <div class="sm:col-span-2">
          <Field
            name="sold_note"
            title="販売終了後のコメント"
            hint="SOLD OUT のときだけ、商品ページの「おっさんからひとこと」欄と作品集に表示されます。例：「大事に使うてもらえたら、うれしいです。」お客様のお名前や地域を載せる場合は、ご本人の了承をとってください（300文字まで）"
          >
            <textarea id="sold_note" name="sold_note" rows={3} maxlength={300} class={input}>{v.sold_note ?? ""}</textarea>
          </Field>
        </div>
        {error && <p role="alert" class="rounded border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-800 sm:col-span-2">{error}</p>}
        <div class="flex flex-wrap gap-3 sm:col-span-2">
          <button class={btnPrimary}>{isNew ? "登録して写真の追加へ" : "保存する"}</button>
          <a href="/admin/products" class={btnGhost}>一覧へ戻る</a>
        </div>
      </form>

      {!isNew && ["draft", "suspended"].includes(product!.status) && !product!.sold_at && (
        <form method="post" action={`/admin/products/${product!.id}/delete`} class="mt-6 text-right" data-confirm="この商品と写真を完全に削除します。よろしいですか？">
          <button class={btnDanger}>この商品を削除</button>
        </form>
      )}
    </>,
    { error: undefined },
  );
}

function ImageManager({ product }: { product: Product }) {
  return (
    <section class={card} data-image-manager data-product-id={product.id} aria-labelledby="img-heading">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 id="img-heading" class="font-serif text-lg text-leather">写真（{product.images.length}枚）</h2>
        <p class="text-xs text-muted">1枚目（★）が一覧に表示されます。スマホで撮った写真をそのまま選べます</p>
      </div>
      <p class={`mt-2 rounded px-3 py-2 text-xs ${photoCountOk(product.images.length) ? "bg-green-50 text-green-900" : "bg-amber-50 text-amber-900"}`}>
        {photoCountOk(product.images.length)
          ? `写真の枚数OK（${product.images.length}枚）：販売中にできます`
          : `${PHOTO_RULE_TEXT}。いま${product.images.length}枚です`}
      </p>
      <label
        data-dropzone
        class="mt-4 flex min-h-28 cursor-pointer flex-col items-center justify-center gap-1 rounded border-2 border-dashed border-line bg-paper px-4 py-6 text-center text-sm text-muted hover:border-camel"
      >
        <span class="text-base text-leather">＋ 写真を追加</span>
        <span>タップして選ぶ／ここにドラッグ（複数可）</span>
        <input type="file" accept="image/*" multiple class="sr-only" data-image-input />
      </label>
      <p data-upload-status class="mt-2 text-sm text-muted" aria-live="polite"></p>
      {product.images.length > 0 && (
        <ul class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4" data-image-list>
          {product.images.map((img, i) => (
            <li class="rounded border border-line bg-white p-2" data-image-id={img.id}>
              <div class="relative aspect-square overflow-hidden rounded bg-sand">
                <img src={mediaUrl(img.key_small)} alt={img.alt} class="h-full w-full object-cover" loading="lazy" />
                {img.is_main ? <span class="absolute left-1 top-1 rounded bg-gold px-1.5 text-xs text-ink">★ メイン</span> : null}
              </div>
              <input
                value={img.alt}
                placeholder="写真の説明（代替テキスト）"
                aria-label="写真の説明（代替テキスト）"
                data-image-alt
                class="mt-2 w-full rounded border border-line px-2 py-1.5 text-[16px] sm:text-sm"
              />
              <div class="mt-2 grid grid-cols-4 gap-1 text-sm">
                <button type="button" data-image-move="-1" disabled={i === 0} class="min-h-10 rounded border border-line disabled:opacity-30" aria-label="前へ">←</button>
                <button type="button" data-image-move="1" disabled={i === product.images.length - 1} class="min-h-10 rounded border border-line disabled:opacity-30" aria-label="後ろへ">→</button>
                <button type="button" data-image-main disabled={!!img.is_main} class="min-h-10 rounded border border-line disabled:opacity-30" aria-label="メインにする">★</button>
                <button type="button" data-image-delete class="min-h-10 rounded border border-red-200 text-red-700" aria-label="削除">✕</button>
              </div>
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}

async function readProductForm(c: Context<AppEnv>) {
  const body = await c.req.parseBody();
  const raw: Record<string, string> = {};
  for (const k of Object.keys(productSchema.shape)) raw[k] = typeof body[k] === "string" ? (body[k] as string) : "";
  if (!raw.status) raw.status = "draft";
  return { raw, parsed: productSchema.safeParse(raw) };
}

function friendlyDbError(e: unknown): string {
  const msg = e instanceof Error ? e.message : String(e);
  if (msg.includes("products.code")) return "この商品管理番号はすでに使われています。";
  if (msg.includes("products.slug")) return "このURL用の名前はすでに使われています。";
  return "保存できませんでした。入力内容を確認してください。";
}

adminRoutes.get("/products/new", (c) => productFormPage(c, null));

adminRoutes.post("/products", async (c) => {
  const { raw, parsed } = await readProductForm(c);
  if (!parsed.success) return productFormPage(c, null, raw as never, parsed.error.issues[0].message);
  const f = parsed.data;
  if (f.status === "on_sale") {
    return productFormPage(c, null, raw as never, `写真を追加してから販売中にしてください。${PHOTO_RULE_TEXT}。`);
  }
  const id = newId();
  const now = nowIso();
  try {
    await c.env.DB.prepare(
      `INSERT INTO products (id, code, slug, name, category_id, description, material, size, color, price,
         shipping_profile_id, status, stock, sold_at, published_at, sold_note)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
    )
      .bind(
        id, f.code, f.slug || toSlug(f.code), f.name, f.category_id || null, f.description, f.material || null,
        f.size || null, f.color || null, f.price, f.shipping_profile_id || null, f.status,
        f.status === "sold_out" ? 0 : 1, f.status === "sold_out" ? now : null, null,
        f.sold_note.trim() || null,
      )
      .run();
  } catch (e) {
    return productFormPage(c, null, raw as never, friendlyDbError(e));
  }
  await audit(c.env.DB, c.get("adminEmail"), "product.create", id, { code: f.code, status: f.status });
  return c.redirect(`/admin/products/${id}?notice=created`, 303);
});

adminRoutes.get("/products/:id", async (c) => {
  const p = await getProductById(c.env.DB, c.req.param("id"));
  if (!p) return c.notFound();
  return productFormPage(c, p);
});

adminRoutes.post("/products/:id", async (c) => {
  const p = await getProductById(c.env.DB, c.req.param("id"));
  if (!p) return c.notFound();
  const { raw, parsed } = await readProductForm(c);
  if (p.status === "reserved") raw.status = "on_sale"; // 手続き中は状態を変えない
  if (!parsed.success) return productFormPage(c, p, raw as never, parsed.error.issues[0].message);
  const f = parsed.data;
  const now = nowIso();
  const status = p.status === "reserved" ? "reserved" : f.status;
  if (status === "on_sale" && !photoCountOk(p.images.length)) {
    return productFormPage(c, p, raw as never, `${PHOTO_RULE_TEXT}（現在${p.images.length}枚）。写真を追加してから販売中にしてください。`);
  }
  const soldAt = status === "sold_out" ? p.sold_at ?? now : null;
  const publishedAt = p.published_at ?? (status === "on_sale" ? now : null);
  try {
    await c.env.DB.prepare(
      `UPDATE products SET code=?, slug=?, name=?, category_id=?, description=?, material=?, size=?, color=?, price=?,
         shipping_profile_id=?, status=?, stock=?, sold_at=?, published_at=?, sold_note=?, updated_at=?
       WHERE id = ?`,
    )
      .bind(
        f.code, f.slug || p.slug, f.name, f.category_id || null, f.description, f.material || null, f.size || null,
        f.color || null, f.price, f.shipping_profile_id || null, status, status === "sold_out" ? 0 : 1, soldAt,
        publishedAt, f.sold_note.trim() || null, now, p.id,
      )
      .run();
  } catch (e) {
    return productFormPage(c, p, raw as never, friendlyDbError(e));
  }
  await audit(c.env.DB, c.get("adminEmail"), "product.update", p.id, { from: p.status, to: status, price: f.price });
  return c.redirect(`/admin/products/${p.id}?notice=saved`, 303);
});

adminRoutes.post("/products/:id/delete", async (c) => {
  const p = await getProductById(c.env.DB, c.req.param("id"));
  if (!p) return c.notFound();
  const ordered = await c.env.DB.prepare("SELECT 1 FROM order_items WHERE product_id = ? LIMIT 1").bind(p.id).first();
  if (!["draft", "suspended"].includes(p.status) || p.sold_at || ordered) {
    return productFormPage(c, p, undefined, "販売実績のある商品は削除できません（SOLD OUT作品として残します）。");
  }
  await deleteMedia(c.env, p.images.flatMap((img) => [img.key_large, img.key_small]));
  await c.env.DB.prepare("DELETE FROM products WHERE id = ?").bind(p.id).run();
  await audit(c.env.DB, c.get("adminEmail"), "product.delete", p.id, { code: p.code });
  return c.redirect("/admin/products?notice=deleted", 303);
});

// ------------------------------------------------------------------ 商品写真（admin.js から呼ぶ JSON API）
const MAX_LARGE = MAX_MEDIA_BYTES;
const MAX_SMALL = 1024 * 1024;

async function readImage(v: unknown, max: number): Promise<{ bytes: ArrayBuffer; type: string } | null> {
  if (!(v instanceof File) || v.size === 0 || v.size > max) return null;
  const bytes = await v.arrayBuffer();
  const b = new Uint8Array(bytes.slice(0, 12));
  const isJpeg = b[0] === 0xff && b[1] === 0xd8 && b[2] === 0xff;
  const isWebp = String.fromCharCode(...b.slice(0, 4)) === "RIFF" && String.fromCharCode(...b.slice(8, 12)) === "WEBP";
  if (isJpeg) return { bytes, type: "image/jpeg" };
  if (isWebp) return { bytes, type: "image/webp" };
  return null;
}

adminRoutes.post("/products/:id/images", async (c) => {
  const p = await getProductById(c.env.DB, c.req.param("id"));
  if (!p) return c.json({ error: "商品が見つかりません" }, 404);
  if (p.images.length >= 20) return c.json({ error: "写真は1商品20枚までです" }, 400);
  const body = await c.req.parseBody();
  const large = await readImage(body.large, MAX_LARGE);
  const small = await readImage(body.small, MAX_SMALL);
  if (!large || !small) return c.json({ error: "画像を読み込めませんでした（JPEG/WebP、1.9MB以下）" }, 400);
  const id = newId();
  const ext = large.type === "image/webp" ? "webp" : "jpg";
  const keyLarge = `products/${p.id}/${id}.${ext}`;
  const keySmall = `products/${p.id}/${id}_sm.${ext}`;
  await putMedia(c.env, keyLarge, large.bytes, large.type);
  await putMedia(c.env, keySmall, small.bytes, small.type);
  const width = Number(body.width) || null;
  const height = Number(body.height) || null;
  const alt = typeof body.alt === "string" ? body.alt.slice(0, 200) : "";
  const isMain = p.images.length === 0 ? 1 : 0;
  await c.env.DB.prepare(
    `INSERT INTO product_images (id, product_id, key_large, key_small, alt, width, height, sort_order, is_main)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`,
  ).bind(id, p.id, keyLarge, keySmall, alt || p.name, width, height, p.images.length, isMain).run();
  await touch(c, p.id);
  await audit(c.env.DB, c.get("adminEmail"), "image.add", p.id, { image: id });
  return c.json({ ok: true, id });
});

adminRoutes.post("/products/:id/images/order", async (c) => {
  const pid = c.req.param("id");
  const { ids } = (await c.req.json().catch(() => ({}))) as { ids?: string[] };
  if (!Array.isArray(ids)) return c.json({ error: "不正なリクエスト" }, 400);
  await c.env.DB.batch(
    ids.map((id, i) => c.env.DB.prepare("UPDATE product_images SET sort_order = ? WHERE id = ? AND product_id = ?").bind(i, id, pid)),
  );
  await touch(c, pid);
  return c.json({ ok: true });
});

adminRoutes.post("/images/:imgId/main", async (c) => {
  const img = await c.env.DB.prepare("SELECT product_id FROM product_images WHERE id = ?").bind(c.req.param("imgId")).first<{ product_id: string }>();
  if (!img) return c.json({ error: "写真が見つかりません" }, 404);
  await c.env.DB.batch([
    c.env.DB.prepare("UPDATE product_images SET is_main = 0 WHERE product_id = ?").bind(img.product_id),
    c.env.DB.prepare("UPDATE product_images SET is_main = 1 WHERE id = ?").bind(c.req.param("imgId")),
  ]);
  await touch(c, img.product_id);
  return c.json({ ok: true });
});

adminRoutes.post("/images/:imgId/alt", async (c) => {
  const { alt } = (await c.req.json().catch(() => ({}))) as { alt?: string };
  await c.env.DB.prepare("UPDATE product_images SET alt = ? WHERE id = ?").bind(String(alt ?? "").slice(0, 200), c.req.param("imgId")).run();
  return c.json({ ok: true });
});

adminRoutes.post("/images/:imgId/delete", async (c) => {
  const img = await c.env.DB.prepare("SELECT * FROM product_images WHERE id = ?")
    .bind(c.req.param("imgId"))
    .first<{ id: string; product_id: string; key_large: string; key_small: string; is_main: number }>();
  if (!img) return c.json({ error: "写真が見つかりません" }, 404);
  const prod = await c.env.DB.prepare("SELECT status, (SELECT COUNT(*) FROM product_images WHERE product_id = ?) AS n FROM products WHERE id = ?")
    .bind(img.product_id, img.product_id)
    .first<{ status: string; n: number }>();
  if (prod && ["on_sale", "reserved"].includes(prod.status) && !photoCountOk(prod.n - 1)) {
    return c.json({ error: `販売中のため削除できません。${PHOTO_RULE_TEXT}（削除すると${prod.n - 1}枚）。先に写真を追加するか、販売状態を変えてください。` }, 400);
  }
  await deleteMedia(c.env, [img.key_large, img.key_small]);
  await c.env.DB.prepare("DELETE FROM product_images WHERE id = ?").bind(img.id).run();
  if (img.is_main) {
    await c.env.DB.prepare(
      `UPDATE product_images SET is_main = 1 WHERE id = (SELECT id FROM product_images WHERE product_id = ? ORDER BY sort_order LIMIT 1)`,
    ).bind(img.product_id).run();
  }
  await touch(c, img.product_id);
  await audit(c.env.DB, c.get("adminEmail"), "image.delete", img.product_id, { image: img.id });
  return c.json({ ok: true });
});

const touch = (c: Context<AppEnv>, productId: string) =>
  c.env.DB.prepare("UPDATE products SET updated_at = ? WHERE id = ?").bind(nowIso(), productId).run();

// ================================================================== カテゴリー
adminRoutes.get("/categories", async (c) => {
  const cats = await listCategories(c.env.DB);
  return page(
    c,
    "カテゴリー",
    <>
      <ul class="space-y-3">
        {cats.map((x) => (
          <li class={card}>
            <form method="post" action={`/admin/categories/${x.id}`} class="grid items-end gap-3 sm:grid-cols-[1fr_1fr_6rem_auto]">
              <Field title="表示名"><input name="name" value={x.name} required class={input} /></Field>
              <Field title="URL用の名前"><input name="slug" value={x.slug} required pattern="[a-z0-9-]+" class={input} /></Field>
              <Field title="並び順"><input name="sort_order" type="number" value={String(x.sort_order)} class={input} /></Field>
              <button class={btnGhost}>保存</button>
            </form>
          </li>
        ))}
      </ul>
      <form method="post" action="/admin/categories" class={`${card} mt-6 grid items-end gap-3 sm:grid-cols-[1fr_1fr_6rem_auto]`}>
        <Field title="新しいカテゴリー"><input name="name" required class={input} placeholder="例：ペンケース" /></Field>
        <Field title="URL用の名前"><input name="slug" required pattern="[a-z0-9-]+" class={input} placeholder="例：pen-case" /></Field>
        <Field title="並び順"><input name="sort_order" type="number" value="50" class={input} /></Field>
        <button class={btnPrimary}>追加</button>
      </form>
    </>,
  );
});

const categorySchema = z.object({
  name: z.string().trim().min(1).max(40),
  slug: z.string().trim().regex(/^[a-z0-9-]+$/),
  sort_order: z.coerce.number().int().min(0).max(9999),
});

adminRoutes.post("/categories/:id?", async (c) => {
  const parsed = categorySchema.safeParse(await c.req.parseBody());
  if (!parsed.success) return c.redirect("/admin/categories", 303);
  const f = parsed.data;
  const id = c.req.param("id");
  try {
    if (id) {
      await c.env.DB.prepare("UPDATE categories SET name=?, slug=?, sort_order=? WHERE id=?").bind(f.name, f.slug, f.sort_order, id).run();
    } else {
      await c.env.DB.prepare("INSERT INTO categories (id, name, slug, sort_order) VALUES (?, ?, ?, ?)").bind(newId(), f.name, f.slug, f.sort_order).run();
    }
  } catch {
    return page(c, "カテゴリー", <a href="/admin/categories" class={btnGhost}>戻る</a>, { error: "URL用の名前が重複しています。" });
  }
  await audit(c.env.DB, c.get("adminEmail"), id ? "category.update" : "category.create", id, f);
  return c.redirect("/admin/categories?notice=saved", 303);
});

// ================================================================== 送料設定
adminRoutes.get("/shipping", async (c) => {
  const list = await listShippingProfiles(c.env.DB);
  const row = (s?: (typeof list)[number]) => (
    <form method="post" action={s ? `/admin/shipping/${s.id}` : "/admin/shipping"} class="grid items-end gap-3 sm:grid-cols-[1fr_8rem_1fr_auto_auto]">
      <Field title={s ? "配送方法" : "新しい配送方法"}><input name="name" value={s?.name ?? ""} required class={input} placeholder="例：クリックポスト" /></Field>
      <Field title="送料（税込・円）"><input name="fee" type="number" min="0" inputmode="numeric" value={s?.fee != null ? String(s.fee) : ""} class={input} placeholder="未定なら空欄" /></Field>
      <Field title="備考"><input name="note" value={s?.note ?? ""} class={input} /></Field>
      <label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" checked={s ? !!s.is_active : true} /> 有効</label>
      <button class={s ? btnGhost : btnPrimary}>{s ? "保存" : "追加"}</button>
    </form>
  );
  return page(
    c,
    "送料設定",
    <>
      <p class="mb-4 text-sm text-muted">ここで登録した配送方法を、商品ごとに選びます。金額が未定の場合は空欄にしておくと、お客様には「購入手続き画面でご案内」と表示されます。</p>
      <ul class="space-y-3">
        {list.map((s) => <li class={card}>{row(s)}</li>)}
      </ul>
      <div class={`${card} mt-6`}>{row()}</div>
    </>,
  );
});

adminRoutes.post("/shipping/:id?", async (c) => {
  const b = await c.req.parseBody();
  const name = String(b.name ?? "").trim().slice(0, 60);
  const feeRaw = String(b.fee ?? "").trim();
  const fee = feeRaw === "" ? null : Math.max(0, Math.floor(Number(feeRaw)));
  if (!name || (fee !== null && Number.isNaN(fee))) return c.redirect("/admin/shipping", 303);
  const note = String(b.note ?? "").trim().slice(0, 200) || null;
  const active = b.is_active === "1" ? 1 : 0;
  const id = c.req.param("id");
  if (id) {
    await c.env.DB.prepare("UPDATE shipping_profiles SET name=?, fee=?, note=?, is_active=? WHERE id=?").bind(name, fee, note, active, id).run();
  } else {
    await c.env.DB.prepare("INSERT INTO shipping_profiles (id, name, fee, note, is_active) VALUES (?, ?, ?, ?, ?)").bind(newId(), name, fee, note, active).run();
  }
  await audit(c.env.DB, c.get("adminEmail"), id ? "shipping.update" : "shipping.create", id, { name, fee });
  return c.redirect("/admin/shipping?notice=saved", 303);
});

// ================================================================== ページ設定
adminRoutes.get("/settings", async (c) => {
  const hero = await getSetting<HeroSetting>(c.env.DB, "home.hero", { catch: SITE.catch, sub: SITE.sub, imageKey: null });
  const about = await getSetting<AboutSetting>(c.env.DB, "page.about", { body: null });
  return page(
    c,
    "ページ設定",
    <div class="space-y-6">
      <section class={card}>
        <h2 class="font-serif text-lg text-leather">トップページのメイン画像</h2>
        <div class="mt-3 flex flex-wrap items-start gap-4">
          <img src={hero.imageKey ? mediaUrl(hero.imageKey) : "/images/works/coincase-natural-2.jpg"} alt="現在のメイン画像" class="h-40 w-32 rounded object-cover" />
          <div class="flex-1 space-y-2 text-sm">
            <p class="text-muted">{hero.imageKey ? "アップロードした画像を表示中" : "初期画像（ナチュラルのコインケース）を表示中"}</p>
            <label class={`${btnGhost} cursor-pointer`}>
              画像を選んで変更
              <input type="file" accept="image/*" class="sr-only" data-hero-input />
            </label>
            <p data-hero-status class="text-muted" aria-live="polite"></p>
            {hero.imageKey && (
              <form method="post" action="/admin/settings/hero-image/reset">
                <button class="text-sm text-red-700 underline">初期画像に戻す</button>
              </form>
            )}
          </div>
        </div>
      </section>
      <form method="post" action="/admin/settings" class={`${card} space-y-4`}>
        <h2 class="font-serif text-lg text-leather">文言</h2>
        <Field name="hero_catch" title="キャッチコピー" hint="「、」の後ろで改行して表示します">
          <input id="hero_catch" name="hero_catch" value={hero.catch} maxlength={60} class={input} />
        </Field>
        <Field name="hero_sub" title="サブコピー">
          <input id="hero_sub" name="hero_sub" value={hero.sub} maxlength={80} class={input} />
        </Field>
        <Field name="about_body" title="制作者紹介の本文" hint="空行で段落が分かれます。空欄にすると初期の文章を表示します">
          <textarea id="about_body" name="about_body" rows={14} class={input}>{about.body ?? ""}</textarea>
        </Field>
        <button class={btnPrimary}>保存する</button>
      </form>
    </div>,
  );
});

adminRoutes.post("/settings", async (c) => {
  const b = await c.req.parseBody();
  const hero = await getSetting<HeroSetting>(c.env.DB, "home.hero", { catch: SITE.catch, sub: SITE.sub, imageKey: null });
  const actor = c.get("adminEmail");
  await setSetting(c.env.DB, "home.hero", {
    ...hero,
    catch: String(b.hero_catch ?? "").trim().slice(0, 60) || SITE.catch,
    sub: String(b.hero_sub ?? "").trim().slice(0, 80) || SITE.sub,
  }, actor);
  const body = String(b.about_body ?? "").replace(/\r\n/g, "\n").trim().slice(0, 5000);
  await setSetting(c.env.DB, "page.about", { body: body || null }, actor);
  await audit(c.env.DB, actor, "settings.update", "home.hero,page.about");
  return c.redirect("/admin/settings?notice=saved", 303);
});

adminRoutes.post("/settings/hero-image", async (c) => {
  const body = await c.req.parseBody();
  const large = await readImage(body.large, MAX_LARGE);
  if (!large) return c.json({ error: "画像を読み込めませんでした" }, 400);
  const key = `site/hero-${newId()}.${large.type === "image/webp" ? "webp" : "jpg"}`;
  await putMedia(c.env, key, large.bytes, large.type);
  const hero = await getSetting<HeroSetting>(c.env.DB, "home.hero", { catch: SITE.catch, sub: SITE.sub, imageKey: null });
  if (hero.imageKey) await deleteMedia(c.env, [hero.imageKey]);
  await setSetting(c.env.DB, "home.hero", { ...hero, imageKey: key }, c.get("adminEmail"));
  await audit(c.env.DB, c.get("adminEmail"), "settings.hero_image", key);
  return c.json({ ok: true });
});

adminRoutes.post("/settings/hero-image/reset", async (c) => {
  const hero = await getSetting<HeroSetting>(c.env.DB, "home.hero", { catch: SITE.catch, sub: SITE.sub, imageKey: null });
  if (hero.imageKey) await deleteMedia(c.env, [hero.imageKey]);
  await setSetting(c.env.DB, "home.hero", { ...hero, imageKey: null }, c.get("adminEmail"));
  return c.redirect("/admin/settings?notice=saved", 303);
});

// ================================================================== Instagram 連携
adminRoutes.get("/instagram", async (c) => {
  const token = await getInstagramToken(c.env);
  const sync = await getSetting(c.env.DB, "instagram.sync", { lastSyncAt: null as string | null, lastError: null as string | null });
  const { results: posts } = await c.env.DB.prepare("SELECT * FROM instagram_posts ORDER BY posted_at DESC LIMIT 12").all<{
    id: string; image_key: string | null; permalink: string; posted_at: string;
  }>();
  return page(
    c,
    "Instagram連携",
    <div class="space-y-6">
      <section class={card}>
        <h2 class="font-serif text-lg text-leather">状態</h2>
        <dl class="mt-3 grid grid-cols-[8rem_1fr] gap-y-2 text-sm">
          <dt class="text-muted">アクセストークン</dt>
          <dd>{token ? `登録済み（有効期限 ${formatDateTime(token.expires_at)}・自動更新）` : "未登録"}</dd>
          <dt class="text-muted">最終更新</dt>
          <dd>{sync.lastSyncAt ? formatDateTime(sync.lastSyncAt) : "まだ取得していません"}</dd>
          {sync.lastError && (
            <>
              <dt class="text-muted">エラー</dt>
              <dd class="text-red-700">{sync.lastError}</dd>
            </>
          )}
        </dl>
        <p class="mt-3 text-xs text-muted">6時間ごとに自動で最新の投稿を取得し、トップページに6件表示します。</p>
        {token && (
          <form method="post" action="/admin/instagram/sync" class="mt-4">
            <button class={btnPrimary}>今すぐ更新</button>
          </form>
        )}
      </section>
      <form method="post" action="/admin/instagram/token" class={`${card} space-y-3`}>
        <h2 class="font-serif text-lg text-leather">{token ? "トークンを登録し直す" : "アクセストークンの登録"}</h2>
        <p class="text-sm leading-6 text-muted">
          Meta for Developers のアプリ画面「Instagram → API setup with Instagram business login」で発行したアクセストークンを貼り付けてください。手順は docs/ADMIN_MANUAL.md にあります。トークンは画面には再表示しません。
        </p>
        <input name="token" type="password" autocomplete="off" required minlength={20} class={input} placeholder="IGAA…" aria-label="アクセストークン" />
        <button class={btnGhost}>登録して取得する</button>
      </form>
      {posts.length > 0 && (
        <section class={card}>
          <h2 class="font-serif text-lg text-leather">取得済みの投稿</h2>
          <ul class="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-6">
            {posts.map((p) => (
              <li>
                <a href={p.permalink} target="_blank" rel="noopener noreferrer" class="block aspect-square overflow-hidden rounded bg-sand">
                  {p.image_key && <img src={mediaUrl(p.image_key)} alt="" class="h-full w-full object-cover" loading="lazy" />}
                </a>
              </li>
            ))}
          </ul>
        </section>
      )}
    </div>,
  );
});

adminRoutes.post("/instagram/token", async (c) => {
  const b = await c.req.parseBody();
  const token = String(b.token ?? "").trim();
  if (token.length < 20 || /\s/.test(token)) return c.redirect("/admin/instagram", 303);
  await saveInstagramToken(c.env, token);
  await audit(c.env.DB, c.get("adminEmail"), "instagram.token_set");
  const r = await syncInstagram(c.env);
  return c.redirect(r.ok ? "/admin/instagram?notice=synced" : "/admin/instagram", 303);
});

adminRoutes.post("/instagram/sync", async (c) => {
  const r = await syncInstagram(c.env);
  await audit(c.env.DB, c.get("adminEmail"), "instagram.sync", undefined, r);
  return c.redirect(r.ok ? "/admin/instagram?notice=synced" : "/admin/instagram", 303);
});

// ================================================================== 操作履歴
adminRoutes.get("/logs", async (c) => {
  const { results } = await c.env.DB.prepare("SELECT * FROM audit_logs ORDER BY id DESC LIMIT 200").all<{
    id: number; actor: string; action: string; target: string | null; detail: string | null; created_at: string;
  }>();
  return page(
    c,
    "操作履歴",
    <div class={`${card} overflow-x-auto !p-0`}>
      <table class="w-full min-w-[560px] text-left text-sm">
        <thead class="bg-sand/60 text-xs text-muted">
          <tr><th class="px-3 py-2">日時</th><th class="px-3 py-2">操作者</th><th class="px-3 py-2">操作</th><th class="px-3 py-2">内容</th></tr>
        </thead>
        <tbody class="divide-y divide-line">
          {results.map((r) => (
            <tr>
              <td class="whitespace-nowrap px-3 py-2">{formatDateTime(r.created_at)}</td>
              <td class="px-3 py-2">{r.actor}</td>
              <td class="px-3 py-2">{r.action}</td>
              <td class="max-w-[24rem] truncate px-3 py-2 text-xs text-muted">{r.detail ?? r.target ?? ""}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>,
  );
});
