import type { Child } from "hono/jsx";
import { raw } from "hono/html";
import { formatDate, formatPrice, mediaUrl } from "../lib/format";
import { SITE, WORKS } from "../lib/site";
import type { InstagramPost, Product, ProductImage } from "../lib/types";

export function InstagramIcon({ class: cls = "h-5 w-5" }: { class?: string }) {
  return (
    <svg viewBox="0 0 24 24" fill="none" class={cls} aria-hidden="true">
      <rect x="3" y="3" width="18" height="18" rx="5" stroke="currentColor" stroke-width="1.5" />
      <circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="1.5" />
      <circle cx="17.3" cy="6.7" r="1" fill="currentColor" />
    </svg>
  );
}

type Variant = "primary" | "outline" | "gold" | "ghostLight";
const BTN: Record<Variant, string> = {
  primary: "bg-leather text-ivory hover:bg-charcoal",
  outline: "border border-leather text-leather hover:bg-leather hover:text-ivory",
  gold: "bg-gold text-ink hover:bg-[#d6b68d]",
  ghostLight: "border border-ivory/70 text-ivory hover:bg-ivory hover:text-ink",
};

export function ButtonLink({ href, variant = "primary", children }: { href: string; variant?: Variant; children: Child }) {
  return (
    <a
      href={href}
      class={`inline-flex min-h-12 items-center justify-center gap-2 rounded-[2px] px-7 py-3 text-[0.95rem] tracking-[0.12em] transition-colors duration-300 ${BTN[variant]}`}
    >
      {children}
    </a>
  );
}

export function SectionHeading({
  eyebrow,
  title,
  lead,
  align = "center",
}: {
  eyebrow?: string;
  title: string;
  lead?: string;
  align?: "center" | "left";
}) {
  const center = align === "center";
  return (
    <div class={`mb-10 ${center ? "text-center" : ""}`}>
      {eyebrow && <p class="eyebrow mb-3">{eyebrow}</p>}
      <h2 class="text-[1.45rem] sm:text-[1.8rem]">{title}</h2>
      <div class={`stitch mt-5 w-16 ${center ? "mx-auto" : ""}`} aria-hidden="true"></div>
      {lead && <p class={`mt-6 text-[0.95rem] text-muted ${center ? "mx-auto max-w-xl" : "max-w-xl"}`}>{lead}</p>}
    </div>
  );
}

export function Placeholder({ label = "写真準備中", tone = "light" }: { label?: string; tone?: "light" | "dark" }) {
  return (
    <div
      role="img"
      aria-label={label}
      class={`flex h-full w-full flex-col items-center justify-center gap-2 p-4 text-center ${
        tone === "dark" ? "bg-[#1b1a18] text-gold/70" : "bg-sand text-muted"
      }`}
    >
      <svg width="28" height="28" viewBox="0 0 24 24" fill="none" aria-hidden="true" class="opacity-60">
        <rect x="3" y="5" width="18" height="14" rx="1.5" stroke="currentColor" stroke-width="1.2" />
        <circle cx="9" cy="10" r="1.6" stroke="currentColor" stroke-width="1.2" />
        <path d="M4 17l5-5 4 4 3-3 4 4" stroke="currentColor" stroke-width="1.2" />
      </svg>
      <span class="text-xs tracking-[0.2em]">{label}</span>
    </div>
  );
}

/** 商品画像（R2）を srcset 付きで表示 */
export function ProductImg({
  img,
  sizes,
  eager = false,
  class: cls = "",
}: {
  img: ProductImage;
  sizes: string;
  eager?: boolean;
  class?: string;
}) {
  return (
    <img
      src={mediaUrl(img.key_large)}
      srcset={`${mediaUrl(img.key_small)} 640w, ${mediaUrl(img.key_large)} 1600w`}
      sizes={sizes}
      alt={img.alt}
      width={img.width ?? undefined}
      height={img.height ?? undefined}
      loading={eager ? "eager" : "lazy"}
      decoding="async"
      class={`absolute inset-0 h-full w-full object-cover ${cls}`}
    />
  );
}

export function SoldOutOverlay({ large = false }: { large?: boolean }) {
  return (
    <div class="pointer-events-none absolute inset-0 flex items-center justify-center bg-ink/35" aria-label="SOLD OUT">
      <span class={`border border-ivory/90 font-serif tracking-[0.3em] text-ivory ${large ? "px-6 py-2.5 text-xl" : "px-3 py-1.5 text-sm"}`}>
        SOLD OUT
      </span>
    </div>
  );
}

export function ProductCard({ product, eager = false }: { product: Product; eager?: boolean }) {
  const img = product.images[0];
  const soldOut = product.status === "sold_out";
  return (
    <a href={`/shop/${product.slug}`} class="group block">
      <div class="relative aspect-[4/5] overflow-hidden rounded-card bg-sand">
        {img ? (
          <ProductImg
            img={img}
            sizes="(min-width: 1024px) 30vw, 50vw"
            eager={eager}
            class={`transition-transform duration-700 ease-soft group-hover:scale-[1.03] ${soldOut ? "grayscale-[35%]" : ""}`}
          />
        ) : (
          <Placeholder />
        )}
        {soldOut && <SoldOutOverlay />}
        {product.status === "reserved" && (
          <span class="absolute left-2 top-2 rounded-[2px] bg-charcoal/85 px-2 py-1 text-[0.68rem] tracking-[0.1em] text-ivory">購入手続き中</span>
        )}
      </div>
      <div class="mt-3 px-0.5">
        {product.category_name && <p class="text-[0.68rem] tracking-[0.15em] text-camel">{product.category_name}</p>}
        <h3 class="mt-0.5 font-sans text-[0.92rem] font-medium leading-6 tracking-[0.04em] text-charcoal">{product.name}</h3>
        {soldOut && product.sold_note && (
          <p class="mt-1 line-clamp-2 text-[0.75rem] leading-5 text-muted">{product.sold_note}</p>
        )}
        {!soldOut && (
          <p class="mt-1 text-[0.95rem] text-leather">
            {formatPrice(product.price)}
            <span class="ml-1 text-[0.7rem] text-muted">（税込）</span>
          </p>
        )}
      </div>
    </a>
  );
}

export function ProductGrid({ products, empty }: { products: Product[]; empty: Child }) {
  if (products.length === 0) return <>{empty}</>;
  return (
    <ul class="grid grid-cols-2 gap-x-4 gap-y-9 sm:gap-x-6 lg:grid-cols-3">
      {products.map((p, i) => (
        <li class="reveal">
          <ProductCard product={p} eager={i < 2} />
        </li>
      ))}
    </ul>
  );
}

export function EmptyState({ title, body }: { title: string; body?: string }) {
  return (
    <div class="rounded-card border border-dashed border-line bg-paper px-6 py-14 text-center">
      <p class="font-serif text-leather">{title}</p>
      {body && <p class="mt-3 text-sm text-muted">{body}</p>}
    </div>
  );
}

export function Breadcrumbs({ items, siteUrl }: { items: { href: string; label: string }[]; siteUrl: string }) {
  const all = [{ href: "/", label: "トップ" }, ...items];
  const jsonLd = {
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    itemListElement: all.map((c, i) => ({ "@type": "ListItem", position: i + 1, name: c.label, item: `${siteUrl}${c.href}` })),
  };
  return (
    <nav aria-label="パンくずリスト" class="mx-auto max-w-6xl px-5 pt-5 text-xs text-muted">
      <ol class="flex flex-wrap items-center gap-x-2 gap-y-1">
        {all.map((c, i) => (
          <li class="flex items-center gap-2">
            {i > 0 && <span aria-hidden="true" class="text-line">/</span>}
            {i === all.length - 1 ? (
              <span aria-current="page" class="text-charcoal">{c.label}</span>
            ) : (
              <a href={c.href} class="underline-offset-4 hover:underline">{c.label}</a>
            )}
          </li>
        ))}
      </ol>
      <script type="application/ld+json">{raw(JSON.stringify(jsonLd).replace(/</g, "\\u003c"))}</script>
    </nav>
  );
}

export function WorksStrip() {
  return (
    <div class="-mx-5 overflow-x-auto px-5 pb-2 no-scrollbar">
      <ul class="flex w-max snap-x snap-mandatory gap-3 sm:gap-4">
        {WORKS.map((w) => (
          <li class="w-[58vw] max-w-[260px] shrink-0 snap-start">
            <div class="relative aspect-[4/5] overflow-hidden rounded-card bg-sand">
              <img src={w.src} alt={w.alt} loading="lazy" decoding="async" class="absolute inset-0 h-full w-full object-cover" />
            </div>
            <p class="mt-2 text-center text-xs text-muted">{w.caption}</p>
          </li>
        ))}
      </ul>
    </div>
  );
}

/** 商品写真ギャラリー（スワイプ・サムネイル・拡大。動きは /assets/site.js） */
export function ProductGallery({ images, name, soldOut }: { images: ProductImage[]; name: string; soldOut: boolean }) {
  return (
    <div data-gallery>
      <div class="relative">
        <div
          data-gallery-track
          class="no-scrollbar flex aspect-square snap-x snap-mandatory overflow-x-auto overscroll-x-contain rounded-card bg-sand"
          aria-roledescription="カルーセル"
          aria-label={`${name}の写真`}
        >
          {images.map((img, i) => (
            <button
              type="button"
              data-gallery-zoom={String(i)}
              class="relative h-full w-full shrink-0 snap-center cursor-zoom-in"
              aria-label={`写真${i + 1}を拡大表示`}
            >
              <ProductImg img={img} sizes="(min-width: 1024px) 50vw, 100vw" eager={i === 0} />
            </button>
          ))}
        </div>
        {soldOut && <SoldOutOverlay large />}
        {images.length > 1 && (
          <p class="pointer-events-none absolute bottom-3 right-3 rounded-full bg-ink/60 px-2.5 py-0.5 text-xs text-ivory">
            <span data-gallery-index>1</span> / {images.length}
          </p>
        )}
      </div>
      {images.length > 1 && (
        <ul class="mt-3 grid grid-cols-4 gap-2" aria-label="サムネイル一覧">
          {images.map((img, i) => (
            <li>
              <button
                type="button"
                data-gallery-thumb={String(i)}
                aria-label={`写真${i + 1}を表示`}
                aria-current={i === 0 ? "true" : "false"}
                class="relative block aspect-square w-full overflow-hidden rounded-[2px] border-2 border-transparent opacity-70 transition aria-[current=true]:border-camel aria-[current=true]:opacity-100"
              >
                <img src={mediaUrl(img.key_small)} alt="" loading="lazy" class="absolute inset-0 h-full w-full object-cover" />
              </button>
            </li>
          ))}
        </ul>
      )}
      <dialog data-gallery-dialog class="m-auto h-dvh max-h-none w-screen max-w-none bg-ink/95 p-0 backdrop:bg-ink/80" aria-label="写真の拡大表示">
        <div class="relative flex h-full w-full items-center justify-center">
          <img data-gallery-full src="" alt="" class="max-h-[85dvh] max-w-[94vw] object-contain" />
          <button type="button" data-gallery-close class="absolute right-3 top-3 flex h-12 w-12 items-center justify-center text-2xl text-ivory" aria-label="閉じる">×</button>
          {images.length > 1 && (
            <>
              <button type="button" data-gallery-prev class="absolute left-1 top-1/2 flex h-14 w-12 -translate-y-1/2 items-center justify-center text-3xl text-ivory/80" aria-label="前の写真">‹</button>
              <button type="button" data-gallery-next class="absolute right-1 top-1/2 flex h-14 w-12 -translate-y-1/2 items-center justify-center text-3xl text-ivory/80" aria-label="次の写真">›</button>
            </>
          )}
        </div>
        <script type="application/json" data-gallery-data>
          {raw(JSON.stringify(images.map((i) => ({ src: mediaUrl(i.key_large), alt: i.alt }))).replace(/</g, "\\u003c"))}
        </script>
      </dialog>
    </div>
  );
}

/** Instagram 最新投稿（同期済みのものだけ表示） */
export function InstagramFeed({ posts }: { posts: InstagramPost[] }) {
  return (
    <section class="bg-leather py-16 text-center text-ivory" aria-labelledby="ig-heading">
      <div class="mx-auto max-w-5xl px-5">
        <p class="eyebrow !text-gold">Instagram</p>
        <h2 id="ig-heading" class="mt-4 font-serif text-xl tracking-[0.1em] !text-ivory">新作や作りよる様子は<br class="sm:hidden" /> Instagram で</h2>
        <p class="mt-3 text-sm text-ivory/75">新しい作品ができたら、まずInstagramでお知らせしよるけぇね。</p>
        {posts.length > 0 && (
          <ul class="mt-10 grid grid-cols-3 gap-1.5 sm:gap-3">
            {posts.map((p) => (
              <li>
                <a
                  href={p.permalink}
                  target="_blank"
                  rel="noopener noreferrer"
                  class="group relative block aspect-square overflow-hidden rounded-[2px] bg-charcoal"
                  aria-label={`Instagramの投稿（${formatDate(p.posted_at)}）を開く`}
                >
                  <img
                    src={mediaUrl(p.image_key!)}
                    alt={(p.caption ?? "Instagramの投稿").slice(0, 80)}
                    loading="lazy"
                    decoding="async"
                    class="absolute inset-0 h-full w-full object-cover transition-transform duration-700 ease-soft group-hover:scale-105"
                  />
                  {p.media_type === "VIDEO" && (
                    <span class="absolute right-1.5 top-1.5 rounded-full bg-ink/60 px-1.5 text-[10px] text-ivory" aria-hidden="true">▶</span>
                  )}
                  {p.media_type === "CAROUSEL_ALBUM" && (
                    <span class="absolute right-1.5 top-1.5 rounded-full bg-ink/60 px-1.5 text-[10px] text-ivory" aria-hidden="true">❐</span>
                  )}
                </a>
              </li>
            ))}
          </ul>
        )}
        <a
          href={SITE.instagram.url}
          target="_blank"
          rel="noopener noreferrer"
          class="mt-10 inline-flex min-h-12 items-center gap-3 rounded-[2px] border border-gold px-7 py-3 tracking-[0.12em] text-gold transition-colors hover:bg-gold hover:text-ink"
        >
          <InstagramIcon /> {SITE.instagram.handle} をフォローしてみんさい
        </a>
      </div>
    </section>
  );
}
