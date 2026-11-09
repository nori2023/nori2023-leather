import type { Child } from "hono/jsx";
import { raw } from "hono/html";
import { FOOTER_NAV, MAIN_NAV, SITE } from "../lib/site";
import { InstagramIcon } from "./components";

export type Meta = {
  title?: string;
  description?: string;
  path: string;
  siteUrl: string;
  image?: string;
  noindex?: boolean;
  jsonLd?: unknown[];
};

const ASSET_VERSION = "20261108";

function Head({ meta }: { meta: Meta }) {
  const title = meta.title ? `${meta.title}｜${SITE.name}` : `${SITE.name}｜${SITE.brand} 手仕事の1点もの革小物`;
  const desc = meta.description ?? SITE.description;
  const url = `${meta.siteUrl}${meta.path}`;
  const image = `${meta.siteUrl}${meta.image ?? "/images/works/coincase-natural-2.jpg"}`;
  return (
    <head>
      <meta charset="utf-8" />
      <meta name="viewport" content="width=device-width, initial-scale=1" />
      <title>{title}</title>
      <meta name="description" content={desc} />
      <meta name="theme-color" content="#493326" />
      <link rel="canonical" href={url} />
      {meta.noindex && <meta name="robots" content="noindex" />}
      <meta property="og:type" content="website" />
      <meta property="og:locale" content="ja_JP" />
      <meta property="og:site_name" content={SITE.name} />
      <meta property="og:title" content={meta.title ?? SITE.name} />
      <meta property="og:description" content={desc} />
      <meta property="og:url" content={url} />
      <meta property="og:image" content={image} />
      <meta name="twitter:card" content="summary_large_image" />
      <link rel="icon" href="/favicon.svg" type="image/svg+xml" />
      <link rel="preconnect" href="https://fonts.googleapis.com" />
      <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="" />
      <link
        rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500&family=Noto+Serif+JP:wght@500;600&display=swap"
      />
      <link rel="stylesheet" href={`/assets/app.css?v=${ASSET_VERSION}`} />
      {(meta.jsonLd ?? []).map((d) => (
        <script type="application/ld+json">{raw(JSON.stringify(d).replace(/</g, "\\u003c"))}</script>
      ))}
    </head>
  );
}

export function Document({ meta, children }: { meta: Meta; children: Child }) {
  return (
    <>
      {raw("<!DOCTYPE html>")}
      <html lang="ja" class="h-full">
        <Head meta={meta} />
        <body class="flex min-h-full flex-col">
          <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-50 focus:bg-ivory focus:px-4 focus:py-2">
            本文へスキップ
          </a>
          <SiteHeader />
          <main id="main" class="flex-1">
            {children}
          </main>
          <SiteFooter />
          <script src={`/assets/site.js?v=${ASSET_VERSION}`} defer></script>
        </body>
      </html>
    </>
  );
}

function SiteHeader() {
  return (
    <header class="sticky top-0 z-30 border-b border-line/70 bg-ivory/95">
      <div class="mx-auto flex h-16 max-w-6xl items-center justify-between pl-5 pr-2 lg:h-20 lg:px-5">
        <a href="/" class="flex flex-col leading-none" aria-label={`${SITE.name} トップへ`}>
          <span class="font-serif text-[1.02rem] font-semibold tracking-[0.12em] text-leather sm:text-lg">{SITE.name}</span>
          <span class="mt-1 text-[0.62rem] tracking-[0.4em] text-camel">{SITE.brand.toUpperCase()}</span>
        </a>
        <nav aria-label="メインメニュー" class="hidden lg:block">
          <ul class="flex items-center gap-7 text-[0.85rem] tracking-[0.08em]">
            {MAIN_NAV.map((i) => (
              <li>
                <a href={i.href} class="text-charcoal transition-colors hover:text-camel">{i.label}</a>
              </li>
            ))}
            <li>
              <a href={SITE.instagram.url} target="_blank" rel="noopener noreferrer" aria-label="Instagram @nori2023_" class="text-leather hover:text-camel">
                <InstagramIcon />
              </a>
            </li>
          </ul>
        </nav>
        <div class="lg:hidden">
          <button
            type="button"
            data-menu-toggle
            aria-expanded="false"
            aria-controls="mobile-nav"
            aria-label="メニューを開く"
            class="relative z-50 flex h-12 w-12 items-center justify-center text-leather"
          >
            <span class="relative block h-3.5 w-6" aria-hidden="true">
              <span class="menu-bar absolute left-0 top-0 h-px w-6 bg-current transition-transform duration-300"></span>
              <span class="menu-bar absolute left-0 top-1.5 h-px w-6 bg-current transition-opacity duration-300"></span>
              <span class="menu-bar absolute left-0 top-3 h-px w-6 bg-current transition-transform duration-300"></span>
            </span>
          </button>
        </div>
      </div>
      <div id="mobile-nav" hidden class="fixed inset-0 z-40 overflow-y-auto bg-ivory px-6 pb-10 pt-24 lg:hidden">
        <nav aria-label="メインメニュー">
          <ul class="divide-y divide-line border-y border-line">
            {[{ href: "/", label: "トップ" }, ...MAIN_NAV].map((i) => (
              <li>
                <a href={i.href} class="flex min-h-14 items-center justify-between py-3 font-serif text-[1.05rem] text-leather">
                  {i.label}
                  <span aria-hidden="true" class="text-camel">›</span>
                </a>
              </li>
            ))}
          </ul>
        </nav>
        <a href={SITE.instagram.url} target="_blank" rel="noopener noreferrer" class="mt-8 inline-flex min-h-12 items-center gap-2 text-sm text-leather">
          <InstagramIcon /> Instagram {SITE.instagram.handle}
        </a>
      </div>
    </header>
  );
}

function SiteFooter() {
  return (
    <footer class="mt-auto bg-charcoal text-ivory/85">
      <div class="mx-auto max-w-6xl px-5 py-14">
        <div class="flex flex-col gap-10 md:flex-row md:justify-between">
          <div class="max-w-xs">
            <img
              src="/images/brand/nori2023-logo.jpeg"
              alt="Nori2023 ロゴ"
              width="80"
              height="80"
              loading="lazy"
              class="mb-5 h-20 w-20 rounded-full border border-gold/40 object-cover"
            />
            <p class="font-serif text-lg tracking-[0.12em] text-ivory">{SITE.name}</p>
            <p class="mt-1 text-xs tracking-[0.35em] text-gold">{SITE.brand.toUpperCase()}</p>
            <p class="mt-4 text-sm leading-7 text-ivory/70">{SITE.catch}</p>
            <a href={SITE.instagram.url} target="_blank" rel="noopener noreferrer" class="mt-5 inline-flex min-h-11 items-center gap-2 text-sm text-gold hover:text-ivory">
              <InstagramIcon /> {SITE.instagram.handle}
            </a>
          </div>
          <div class="grid grid-cols-1 gap-8 text-sm sm:grid-cols-2">
            <ul class="space-y-1">
              {MAIN_NAV.map((i) => (
                <li><a href={i.href} class="inline-flex min-h-10 items-center hover:text-gold">{i.label}</a></li>
              ))}
            </ul>
            <ul class="space-y-1">
              {FOOTER_NAV.map((i) => (
                <li><a href={i.href} class="inline-flex min-h-10 items-center hover:text-gold">{i.label}</a></li>
              ))}
            </ul>
          </div>
        </div>
        <div class="stitch mt-12 opacity-40" aria-hidden="true"></div>
        <p class="mt-6 text-center text-xs tracking-[0.15em] text-ivory/50">© {SITE.brand} {SITE.name}</p>
      </div>
    </footer>
  );
}
