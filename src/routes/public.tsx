import { Hono } from "hono";
import type { AppEnv } from "../env";
import {
  type AboutSetting,
  type HeroSetting,
  getPublicProduct,
  getSetting,
  listCategories,
  listInstagramPosts,
  listPublicProducts,
} from "../lib/db";
import { formatPrice, mediaUrl } from "../lib/format";
import { getMedia } from "../lib/media";
import { SITE } from "../lib/site";
import type { Product } from "../lib/types";
import { Document } from "../views/layout";
import {
  Breadcrumbs,
  ButtonLink,
  EmptyState,
  InstagramFeed,
  InstagramIcon,
  Placeholder,
  ProductGallery,
  ProductGrid,
  SectionHeading,
  SoldOutOverlay,
  WorksStrip,
} from "../views/components";

export const publicRoutes = new Hono<AppEnv>();

const DEFAULT_HERO: HeroSetting = { catch: SITE.catch, sub: SITE.sub, imageKey: null };
const IG_EMPTY_NOTE = "新作のお知らせは Instagram（@nori2023_）でしよります。";

// ------------------------------------------------------------------ トップ
publicRoutes.get("/", async (c) => {
  const db = c.env.DB;
  const [hero, onSale, soldOut, igPosts] = await Promise.all([
    getSetting<HeroSetting>(db, "home.hero", DEFAULT_HERO),
    listPublicProducts(db, { statuses: ["on_sale", "reserved"], limit: 6 }),
    listPublicProducts(db, { statuses: ["sold_out"], limit: 4 }),
    listInstagramPosts(db, 6),
  ]);
  const heroImg = hero.imageKey ? mediaUrl(hero.imageKey) : "/images/works/coincase-natural-2.jpg";
  const [catchA, catchB] = splitCatch(hero.catch || SITE.catch);

  return c.html(
    <Document meta={{ path: "/", siteUrl: c.env.SITE_URL, image: hero.imageKey ? undefined : "/images/works/coincase-natural-2.jpg" }}>
      {/* 1. メインビジュアル */}
      <section class="relative overflow-hidden bg-leather text-ivory">
        <div class="hero-dots absolute inset-0 opacity-[0.15]" aria-hidden="true"></div>
        <div class="relative mx-auto grid max-w-6xl md:min-h-[78vh] md:grid-cols-2 md:items-center">
          <div class="relative aspect-[5/4] md:order-2 md:aspect-auto md:h-full">
            <img src={heroImg} alt="ヌメ革の作品" fetchpriority="high" class="absolute inset-0 h-full w-full object-cover" />
            <div class="absolute inset-0 bg-gradient-to-t from-leather via-transparent to-transparent md:bg-gradient-to-r" aria-hidden="true"></div>
          </div>
          <div class="relative px-6 pb-16 pt-6 md:px-10 md:py-20">
            <p class="eyebrow !text-gold">{SITE.brand} · Handmade leather goods</p>
            <h1 class="mt-4 text-[1.7rem] leading-snug !text-ivory sm:text-[2.4rem]">{SITE.name}</h1>
            <p class="mt-6 font-serif text-[1.15rem] leading-relaxed tracking-[0.1em] text-ivory sm:text-[1.35rem]">
              「{catchA}
              {catchB && (
                <>
                  <br />
                  {catchB}
                </>
              )}
              」
            </p>
            <p class="mt-3 text-[0.95rem] text-ivory/80">{hero.sub || SITE.sub}</p>
            <div class="mt-10 flex flex-col gap-3 sm:flex-row">
              <ButtonLink href="/shop" variant="gold">作品、見てみんさい</ButtonLink>
              <ButtonLink href="/custom-cardcase" variant="ghostLight">自分だけの名刺入れ、作らん？</ButtonLink>
            </div>
          </div>
        </div>
      </section>

      {/* 2. 販売中の作品 */}
      <section class="mx-auto max-w-6xl px-5 py-20 sm:py-24">
        <SectionHeading eyebrow="Works on sale" title="販売中の作品" lead="ぜんぶ1点ものじゃけぇ、売れてしもうたら同じもんはもう作れんのよ。" />
        <ProductGrid products={onSale} empty={<EmptyState title="いま新しい作品を作りよるところです" body={IG_EMPTY_NOTE} />} />
        <div class="mt-12 text-center">
          <ButtonLink href="/shop" variant="outline">作品をぜんぶ見る</ButtonLink>
        </div>
      </section>

      {/* 3. 1点ものへのこだわり */}
      <section class="bg-grain border-y border-line/60 py-20 sm:py-24">
        <div class="mx-auto max-w-3xl px-5">
          <SectionHeading eyebrow="One of a kind" title="1点ものへのこだわり" />
          <div class="reveal space-y-6 text-center text-[0.98rem] leading-[2.1]">
            <p>同じ革でも、色も、模様も、手触りもちごうとる。<br />その違いが、ぶち面白いんよ。</p>
            <p>革を切って、穴あけて、縫うて、磨いて。<br />ひとつずつ手で仕上げとるけぇ、<br class="sm:hidden" />まったく同じもんはできんのんよ。</p>
            <p>大量生産はしよらん。<br />その革、その時にしかできん1点ものじゃけぇね。</p>
          </div>
          <div class="mt-14">
            <p class="mb-4 text-center text-xs tracking-[0.2em] text-camel">これまでに作ったもんより</p>
            <WorksStrip />
          </div>
          <ul class="mt-12 grid grid-cols-3 gap-3 text-center sm:gap-6">
            {[
              ["裁断", "革の表情を見て\n取る場所を決めるんよ"],
              ["縫製", "ひと針ずつ\n手で縫うとります"],
              ["仕上げ", "コバを磨いて\n手触りを整えるんよ"],
            ].map(([t, d]) => (
              <li class="reveal rounded-card border border-line bg-paper px-2 py-6">
                <p class="font-serif text-lg text-leather">{t}</p>
                <p class="mt-2 whitespace-pre-line text-[0.72rem] leading-5 text-muted sm:text-xs">{d}</p>
              </li>
            ))}
          </ul>
        </div>
      </section>

      {/* 4. SOLD OUT作品集 */}
      <section class="mx-auto max-w-6xl px-5 py-20 sm:py-24">
        <SectionHeading eyebrow="Sold out gallery" title="SOLD OUT作品集" lead="もう旅立っていった作品たちです。買うてくれんさって、ほんまにありがとうございました。" />
        <ProductGrid products={soldOut} empty={<EmptyState title="作品集は、まだ準備しよります" />} />
        <div class="mt-12 text-center">
          <ButtonLink href="/gallery" variant="outline">作品集を見てみる</ButtonLink>
        </div>
      </section>

      {/* 5. 名刺入れ・カードケース受注制作 */}
      <section class="bg-ink text-ivory">
        <div class="mx-auto grid max-w-6xl items-center gap-10 px-5 py-20 md:grid-cols-2 md:py-24">
          <div class="relative aspect-[4/3] overflow-hidden rounded-card md:order-2">
            <img src="/images/brand/cardcase-open.jpeg" alt="ヌメ革の名刺ケースを開いた状態" loading="lazy" class="absolute inset-0 h-full w-full object-cover" />
          </div>
          <div>
            <p class="eyebrow !text-gold">Custom card case</p>
            <h2 class="mt-3 text-[1.4rem] leading-relaxed !text-ivory sm:text-[1.75rem]">
              大切な人に贈りとうなる、<br />世界にひとつの<br class="sm:hidden" />名刺・カードケース。
            </h2>
            <div class="mt-5 h-px w-16 bg-gold/70" aria-hidden="true"></div>
            <p class="mt-6 text-[0.95rem] leading-8 text-ivory/80">
              名前やお店のロゴを刻んで作る、ヌメ革のオリジナル名刺ケースよ。自分用はもちろん、誕生日や開店祝い、記念品、退職祝いの贈りもんにもええよ。
            </p>
            <div class="mt-8">
              <ButtonLink href="/custom-cardcase" variant="gold">オリジナル名刺入れ、作ってみる</ButtonLink>
            </div>
          </div>
        </div>
      </section>

      {/* 6. 制作者紹介 */}
      <section class="mx-auto grid max-w-6xl items-center gap-10 px-5 py-20 sm:py-24 md:grid-cols-[2fr_3fr]">
        <div class="relative mx-auto aspect-[3/4] w-full max-w-sm overflow-hidden rounded-card">
          <img src="/images/site/maker-illustration.jpg" alt="工房で革小物を縫う制作者のイラスト" loading="lazy" class="absolute inset-0 h-full w-full object-cover" />
        </div>
        <div>
          <SectionHeading align="left" eyebrow="About the maker" title="作っとるんは、こがぁなおっさんです。" />
          <p class="text-[0.96rem] leading-8">
            革を切って、穴あけて、縫うて、磨いて。そがぁなことをコツコツやりながら、いろんな革小物を作っとります。特に好きなんが革の経年変化。使やぁ使うほど色が深まって、艶が出てくるんよ。新品の革もええけど、使い込んだ革もええよ。
          </p>
          <div class="mt-8">
            <ButtonLink href="/about" variant="outline">おっさんの話を読んでみる</ButtonLink>
          </div>
        </div>
      </section>

      {/* 7. 革の経年変化 / 8. ワークショップ */}
      <section class="bg-grain border-t border-line/60">
        <div class="mx-auto grid max-w-6xl gap-5 px-5 py-20 sm:py-24 md:grid-cols-2">
          <FeatureCard
            href="/leather-aging"
            eyebrow="Leather aging"
            title="革は、使うてからが面白いんよ。"
            body="光や手の脂、毎日のこすれで、ヌメ革はちいとずつ色や艶が変わっていくんよ。その変わり方の楽しみ方とお手入れを紹介しとります。"
            cta="革の経年変化を見てみる"
          />
          <FeatureCard
            href="/workshop"
            eyebrow="Workshop"
            title="ワークショップ"
            body="レザーキーホルダーやカードケースを、自分の手で作ってみんさい。開催の予定はワークショップのページでお知らせしますけぇ。"
            cta="開催予定を見る"
          />
        </div>
      </section>

      {/* 9. Instagram */}
      <InstagramFeed posts={igPosts} />
    </Document>,
  );
});

function splitCatch(s: string): [string, string] {
  const i = s.indexOf("、");
  return i > 0 && i < s.length - 1 ? [s.slice(0, i + 1), s.slice(i + 1)] : [s, ""];
}

function FeatureCard({ href, eyebrow, title, body, cta }: { href: string; eyebrow: string; title: string; body: string; cta: string }) {
  return (
    <a href={href} class="reveal group flex flex-col rounded-card border border-line bg-paper p-7 transition-colors hover:border-camel sm:p-9">
      <p class="eyebrow">{eyebrow}</p>
      <h2 class="mt-3 text-xl">{title}</h2>
      <p class="mt-4 flex-1 text-[0.92rem] leading-7 text-muted">{body}</p>
      <span class="mt-6 inline-flex min-h-11 items-center text-sm tracking-[0.1em] text-leather">
        {cta} <span class="ml-2 transition-transform group-hover:translate-x-1" aria-hidden="true">→</span>
      </span>
    </a>
  );
}

// ------------------------------------------------------------------ 商品一覧
publicRoutes.get("/shop", async (c) => {
  const db = c.env.DB;
  const categories = await listCategories(db);
  const raw = c.req.query("category");
  const current = categories.find((x) => x.slug === raw)?.slug;
  const products = await listPublicProducts(db, { statuses: ["on_sale", "reserved"], categorySlug: current });

  return c.html(
    <Document
      meta={{
        title: "販売中の作品",
        description: "おっさんがひとつひとつ手で作った、1点ものの革小物じゃけぇ。レザーキーホルダー、コインケース、カードケースなんか。",
        path: current ? `/shop?category=${current}` : "/shop",
        siteUrl: c.env.SITE_URL,
      }}
    >
      <Breadcrumbs items={[{ href: "/shop", label: "販売中の作品" }]} siteUrl={c.env.SITE_URL} />
      <div class="mx-auto max-w-6xl px-5 pb-24 pt-10">
        <SectionHeading eyebrow="Shop" title="販売中の作品" lead="完成品はぜんぶ1点もの。写真の作品そのものをお届けしますけぇ。" />
        {categories.length > 0 && (
          <nav aria-label="カテゴリー" class="no-scrollbar -mx-5 mb-10 overflow-x-auto px-5">
            <ul class="flex w-max gap-2 sm:mx-auto">
              {[{ slug: undefined as string | undefined, name: "すべて" }, ...categories].map((x) => {
                const active = x.slug === current;
                return (
                  <li>
                    <a
                      href={x.slug ? `/shop?category=${x.slug}` : "/shop"}
                      aria-current={active ? "page" : undefined}
                      class={`inline-flex min-h-10 items-center rounded-full border px-4 text-sm transition-colors ${
                        active ? "border-leather bg-leather text-ivory" : "border-line bg-paper text-charcoal hover:border-camel"
                      }`}
                    >
                      {x.name}
                    </a>
                  </li>
                );
              })}
            </ul>
          </nav>
        )}
        <ProductGrid
          products={products}
          empty={<EmptyState title={current ? "このカテゴリーは、いま売りよる作品がないんよ" : "いま新しい作品を作りよるところです"} body={IG_EMPTY_NOTE} />}
        />
        <p class="mt-16 text-center text-sm text-muted">
          売れてしもうた作品は <a href="/gallery" class="text-leather underline underline-offset-4">SOLD OUT作品集</a> で見られるよ。
        </p>
      </div>
    </Document>,
  );
});

// ------------------------------------------------------------------ 商品詳細
publicRoutes.get("/shop/:slug", async (c) => {
  const db = c.env.DB;
  const product = await getPublicProduct(db, c.req.param("slug"));
  if (!product) return c.notFound();
  const salesEnabled = (await getSetting<boolean>(db, "sales.enabled", false)) === true;
  const soldOut = product.status === "sold_out";
  const desc = product.description.replace(/\s+/g, " ").slice(0, 110);
  const url = `${c.env.SITE_URL}/shop/${product.slug}`;

  return c.html(
    <Document
      meta={{
        title: soldOut ? `${product.name}（SOLD OUT）` : product.name,
        description: desc || SITE.description,
        path: `/shop/${product.slug}`,
        siteUrl: c.env.SITE_URL,
        image: product.images[0] ? mediaUrl(product.images[0].key_large) : undefined,
        jsonLd: [productJsonLd(product, url, c.env.SITE_URL)],
      }}
    >
      <Breadcrumbs
        siteUrl={c.env.SITE_URL}
        items={[
          soldOut ? { href: "/gallery", label: "SOLD OUT作品集" } : { href: "/shop", label: "販売中の作品" },
          { href: `/shop/${product.slug}`, label: product.name },
        ]}
      />
      <article class="mx-auto grid max-w-6xl gap-10 px-5 pb-24 pt-6 lg:grid-cols-2 lg:gap-14">
        <div>
          {product.images.length > 0 ? (
            <ProductGallery images={product.images} name={product.name} soldOut={soldOut} />
          ) : (
            <div class="relative aspect-square overflow-hidden rounded-card">
              <Placeholder />
              {soldOut && <SoldOutOverlay large />}
            </div>
          )}
        </div>
        <div>
          {product.category_name && <p class="text-xs tracking-[0.18em] text-camel">{product.category_name}</p>}
          <h1 class="mt-2 text-[1.4rem] sm:text-[1.7rem]">{product.name}</h1>
          <p class="mt-1 text-xs text-muted">商品番号：{product.code}</p>
          {!soldOut && (
            <p class="mt-6 font-serif text-[1.6rem] text-leather">
              {formatPrice(product.price)}
              <span class="ml-1 font-sans text-xs text-muted">（税込）</span>
            </p>
          )}
          <PurchaseArea product={product} salesEnabled={salesEnabled} />
          {product.description && <div class="mt-10 whitespace-pre-line text-[0.95rem] leading-8">{product.description}</div>}
          <dl class="mt-10 divide-y divide-line border-y border-line text-sm">
            <Spec label="素材" value={product.material} />
            <Spec label="サイズ" value={product.size} />
            <Spec label="革の色" value={product.color} />
            {!soldOut && (
              <Spec
                label="送料"
                value={
                  product.shipping_name
                    ? `${product.shipping_name}${product.shipping_fee != null ? `　${formatPrice(product.shipping_fee)}（税込）` : ""}`
                    : "購入手続きのときにお知らせします"
                }
              />
            )}
            <Spec label="在庫" value={soldOut ? "なし" : "1点だけ"} />
          </dl>
          <p class="mt-6 text-xs leading-6 text-muted">
            ※ 手作業で仕立てとるけぇ、革の色味・シボ・小さなキズなんかは1点ずつ違います。写真の作品そのものをお届けします。
            <br />※ 見よる画面によって、実物と色の見え方がちごうことがあります。
          </p>
          <div class="mt-8 flex flex-wrap gap-x-6 gap-y-2 text-sm">
            <a href="/leather-care" class="text-leather underline underline-offset-4">革のお手入れ</a>
            <a href="/legal/shipping" class="text-leather underline underline-offset-4">配送・送料</a>
            <a href="/contact" class="text-leather underline underline-offset-4">この作品のこと、聞いてみる</a>
          </div>
        </div>
      </article>
    </Document>,
  );
});

function PurchaseArea({ product, salesEnabled }: { product: Product; salesEnabled: boolean }) {
  if (product.status === "sold_out") {
    return (
      <div class="mt-8 rounded-card border border-line bg-paper px-5 py-6 text-center">
        <p class="font-serif tracking-[0.2em] text-leather">SOLD OUT</p>
        <p class="mt-3 text-sm leading-7">この作品は売れてしまいました。<br />買うてくれんさって、ありがとうございました。</p>
        {product.sold_note && (
          <div class="mt-5 border-t border-line pt-5 text-left">
            <p class="text-xs tracking-[0.2em] text-camel">おっさんからひとこと</p>
            <p class="mt-2 whitespace-pre-line text-sm leading-7">{product.sold_note}</p>
          </div>
        )}
      </div>
    );
  }
  if (product.status === "reserved") {
    return (
      <div class="mt-8 rounded-card border border-line bg-paper px-5 py-5 text-center text-sm leading-7">
        <p class="font-medium text-leather">いま、ほかのお客さんが購入手続きしよります</p>
        <p class="mt-1 text-muted">手続きが終わらんかったら、また販売中に戻りますけぇね。</p>
      </div>
    );
  }
  // STEP 3（在庫確保・決済）が完成し sales.enabled=true になるまで購入不可
  return (
    <div class="mt-8">
      <button
        type="button"
        disabled
        class="flex min-h-14 w-full items-center justify-center rounded-[2px] bg-leather text-[1rem] tracking-[0.15em] text-ivory disabled:cursor-not-allowed disabled:opacity-50"
      >
        {salesEnabled ? "購入手続きへ進む" : "ネットでの購入は、いま準備しよります"}
      </button>
      {!salesEnabled && (
        <p class="mt-3 text-center text-xs text-muted">
          欲しい方は <a href="/contact" class="text-leather underline underline-offset-4">お問い合わせ</a> から声かけてつかぁさい。
        </p>
      )}
    </div>
  );
}

function Spec({ label, value }: { label: string; value: string | null }) {
  if (!value) return null;
  return (
    <div class="grid grid-cols-[6rem_1fr] gap-3 py-3">
      <dt class="text-muted">{label}</dt>
      <dd>{value}</dd>
    </div>
  );
}

function productJsonLd(p: Product, url: string, siteUrl: string) {
  return {
    "@context": "https://schema.org",
    "@type": "Product",
    name: p.name,
    sku: p.code,
    description: p.description,
    image: p.images.map((i) => `${siteUrl}${mediaUrl(i.key_large)}`),
    brand: { "@type": "Brand", name: SITE.brand },
    category: p.category_name ?? undefined,
    offers: {
      "@type": "Offer",
      url,
      priceCurrency: "JPY",
      price: p.price,
      availability: p.status === "on_sale" ? "https://schema.org/InStock" : "https://schema.org/SoldOut",
      itemCondition: "https://schema.org/NewCondition",
    },
  };
}

// ------------------------------------------------------------------ 制作者紹介
const PHOTO_SLOTS: { label: string; src?: string; alt?: string }[] = [
  { label: "工房", src: "/images/site/workshop.jpg", alt: "革や糸、道具が並ぶ工房の作業机" },
  { label: "革の裁断" },
  { label: "刻印", src: "/images/works/keyholder-engraved.jpg", alt: "名前を刻印したレザーキーホルダー" },
  { label: "縫製・組み立て" },
  { label: "完成作品", src: "/images/works/backpack-collage.jpg", alt: "ヌメ革のリュックサック" },
];

const DEFAULT_ABOUT = `はじめまして。
「おっさんのレザークラフト」です。

革を切って、穴あけて、縫うて、磨いて。
そがぁなことをコツコツやりながら、いろんな革小物を作っとります。

同じ革でも、色も模様も手触りもちごうとる。
その違いが面白いんよ。

完成品は基本的に1点もの。
それと、ヌメ革の名刺入れやカードケースなんかに、名前やロゴを入れた作品も作っとります。

特に好きなんが革の経年変化。
使やぁ使うほど色が深まって、艶が出てくる。
新品の革もええけど、使い込んだ革もええよ。

そがぁな革のええところを楽しんでもろうたら、うれしいです。`;

publicRoutes.get("/about", async (c) => {
  const about = await getSetting<AboutSetting>(c.env.DB, "page.about", { body: null });
  const paragraphs = (about.body || DEFAULT_ABOUT).split(/\n\s*\n/);
  return c.html(
    <Document
      meta={{
        title: "制作者紹介",
        description: "作っとるんは、こがぁなおっさんです。革を切って、穴あけて、縫うて、磨いて。おっさんのレザークラフト（Nori2023）の制作者紹介。",
        path: "/about",
        siteUrl: c.env.SITE_URL,
      }}
    >
      <Breadcrumbs items={[{ href: "/about", label: "制作者紹介" }]} siteUrl={c.env.SITE_URL} />
      <article class="pb-24">
        <header class="mx-auto max-w-3xl px-5 pb-12 pt-10 text-center">
          <p class="eyebrow">About the maker</p>
          <h1 class="mt-3 text-[1.55rem] sm:text-[2rem]">作っとるんは、<br class="sm:hidden" />こがぁなおっさんです。</h1>
          <div class="stitch mx-auto mt-6 w-16" aria-hidden="true"></div>
        </header>
        <div class="mx-auto grid max-w-5xl gap-10 px-5 md:grid-cols-[2fr_3fr] md:items-start">
          <div class="space-y-4 md:sticky md:top-28">
            <div class="relative aspect-[4/5] overflow-hidden rounded-card">
              <img src="/images/site/maker-illustration.jpg" alt="工房で革小物を縫う制作者のイラスト" class="absolute inset-0 h-full w-full object-cover" />
            </div>
            <img src="/images/brand/nori2023-logo.jpeg" alt="革に刻印されたNori2023のロゴ" loading="lazy" class="mx-auto h-32 w-32 rounded-full border border-line object-cover" />
          </div>
          <div class="space-y-6 text-[1rem] leading-[2.15]">
            {paragraphs.map((p) => (
              <p class="whitespace-pre-line">{p.trim()}</p>
            ))}
            <a href={SITE.instagram.url} target="_blank" rel="noopener noreferrer" class="inline-flex min-h-12 items-center gap-2 text-leather underline-offset-4 hover:underline">
              <InstagramIcon /> Instagram：{SITE.instagram.handle}
            </a>
          </div>
        </div>
        <section class="mx-auto mt-20 max-w-6xl px-5" aria-labelledby="workshop-photos">
          <h2 id="workshop-photos" class="mb-8 text-center text-xl">作りよる様子</h2>
          <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            {PHOTO_SLOTS.map(({ label, src, alt }) => (
              <li>
                <div class="relative aspect-square overflow-hidden rounded-card">
                  {src ? <img src={src} alt={alt ?? label} loading="lazy" class="absolute inset-0 h-full w-full object-cover" /> : <Placeholder label={`${label}（準備中）`} />}
                </div>
                <p class="mt-2 text-center text-xs text-muted">{label}</p>
              </li>
            ))}
          </ul>
        </section>
        <div class="mt-16 flex flex-col items-center gap-3 px-5 sm:flex-row sm:justify-center">
          <ButtonLink href="/shop">作品、見てみんさい</ButtonLink>
          <ButtonLink href="/custom-cardcase" variant="outline">自分だけの名刺入れ、作らん？</ButtonLink>
        </div>
      </article>
    </Document>,
  );
});

// ------------------------------------------------------------------ 準備中ページ（後続STEPで実装）
const COMING: Record<string, { title: string; note?: string }> = {
  "/gallery": { title: "SOLD OUT作品集" },
  "/custom-cardcase": { title: "オリジナル名刺入れ・カードケース", note: "名入れの相談は、お問い合わせからでも受けとりますけぇ。" },
  "/leather-aging": { title: "革の経年変化" },
  "/leather-care": { title: "革のお手入れ" },
  "/workshop": { title: "ワークショップ", note: "開催のお知らせは Instagram（@nori2023_）でもしとります。" },
  "/order": { title: "オーダーメイド相談" },
  "/contact": { title: "お問い合わせ", note: "急ぎの方は Instagram（@nori2023_）のDMから連絡してつかぁさい。" },
  "/legal/tokushoho": { title: "特定商取引法に基づく表記" },
  "/legal/privacy": { title: "プライバシーポリシー" },
  "/legal/terms": { title: "利用規約" },
  "/legal/shipping": { title: "配送・送料" },
  "/legal/returns": { title: "返品・交換・キャンセル" },
};

for (const [path, page] of Object.entries(COMING)) {
  publicRoutes.get(path, (c) =>
    c.html(
      <Document meta={{ title: page.title, path, siteUrl: c.env.SITE_URL, noindex: true }}>
        <Breadcrumbs items={[{ href: path, label: page.title }]} siteUrl={c.env.SITE_URL} />
        <div class="mx-auto max-w-xl px-5 py-24 text-center">
          <p class="eyebrow">Coming soon</p>
          <h1 class="mt-3 text-[1.5rem]">{page.title}</h1>
          <div class="stitch mx-auto mt-6 w-16" aria-hidden="true"></div>
          <p class="mt-8 text-[0.95rem] leading-8 text-muted">このページは、いま準備しよります。<br />もうちいと待っとってつかぁさい。</p>
          {page.note && <p class="mt-4 text-sm text-muted">{page.note}</p>}
          <div class="mt-10">
            <ButtonLink href="/" variant="outline">トップに戻る</ButtonLink>
          </div>
        </div>
      </Document>,
    ),
  );
}

// ------------------------------------------------------------------ SEO
publicRoutes.get("/robots.txt", (c) =>
  c.text(`User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /api\n\nSitemap: ${c.env.SITE_URL}/sitemap.xml\n`),
);

publicRoutes.get("/sitemap.xml", async (c) => {
  const fixed = ["/", "/shop", "/gallery", "/custom-cardcase", "/about", "/leather-aging", "/leather-care", "/workshop", "/order", "/contact"];
  const products = await listPublicProducts(c.env.DB);
  const urls = [...fixed.map((p) => `${c.env.SITE_URL}${p}`), ...products.map((p) => `${c.env.SITE_URL}/shop/${p.slug}`)];
  const xml = `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${urls
    .map((u) => `  <url><loc>${u}</loc></url>`)
    .join("\n")}\n</urlset>\n`;
  return c.body(xml, 200, { "Content-Type": "application/xml; charset=utf-8" });
});

// ------------------------------------------------------------------ 公開画像（R2）
const PUBLIC_MEDIA_PREFIXES = ["products/", "site/", "instagram/"];

publicRoutes.get("/media/*", async (c) => {
  const key = decodeURIComponent(new URL(c.req.url).pathname.slice("/media/".length));
  if (!PUBLIC_MEDIA_PREFIXES.some((p) => key.startsWith(p)) || key.includes("..")) return c.notFound();
  // エッジのキャッシュにあればそれを返す（DBの読み込みを減らす）
  const cache = (caches as unknown as { default: Cache }).default;
  const hit = await cache.match(c.req.raw);
  if (hit) return new Response(hit.body, hit); // 変更可能なヘッダーにコピー
  const obj = await getMedia(c.env, key);
  if (!obj) return c.notFound();
  const headers = new Headers();
  headers.set("Content-Type", obj.contentType);
  headers.set("etag", obj.etag);
  headers.set("Cache-Control", "public, max-age=31536000, immutable");
  headers.set("X-Content-Type-Options", "nosniff");
  const res = new Response(obj.body, { headers });
  c.executionCtx.waitUntil(cache.put(c.req.raw, res.clone()));
  return res;
});

export function notFoundPage(siteUrl: string) {
  return (
    <Document meta={{ title: "ページが見つからんのよ", path: "/404", siteUrl, noindex: true }}>
      <div class="mx-auto flex max-w-md flex-col items-center px-5 py-24 text-center">
        <p class="eyebrow">404</p>
        <h1 class="mt-3 text-xl">探しよるページが見つからんかったんよ</h1>
        <p class="mt-4 text-sm text-muted">作品が売れてしもうたか、URLが変わったんかもしれん。</p>
        <div class="mt-8">
          <ButtonLink href="/" variant="outline">トップに戻る</ButtonLink>
        </div>
      </div>
    </Document>
  );
}
