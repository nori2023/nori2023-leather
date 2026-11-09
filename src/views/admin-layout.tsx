import type { Child } from "hono/jsx";
import { raw } from "hono/html";

const NAV = [
  { href: "/admin", label: "ダッシュボード" },
  { href: "/admin/products", label: "完成品（1点もの）" },
  { href: "/admin/categories", label: "カテゴリー" },
  { href: "/admin/shipping", label: "送料設定" },
  { href: "/admin/settings", label: "ページ設定" },
  { href: "/admin/instagram", label: "Instagram連携" },
  { href: "/admin/logs", label: "操作履歴" },
];

const NOTICES: Record<string, string> = {
  saved: "保存しました。",
  created: "登録しました。",
  deleted: "削除しました。",
  synced: "Instagramを更新しました。",
};

export function AdminLayout({
  title,
  path,
  email,
  notice,
  error,
  children,
}: {
  title: string;
  path: string;
  email: string;
  notice?: string;
  error?: string;
  children: Child;
}) {
  return (
    <>
      {raw("<!DOCTYPE html>")}
      <html lang="ja">
        <head>
          <meta charset="utf-8" />
          <meta name="viewport" content="width=device-width, initial-scale=1" />
          <meta name="robots" content="noindex, nofollow" />
          <title>{title}｜管理画面｜おっさんのレザークラフト</title>
          <link rel="icon" href="/favicon.svg" type="image/svg+xml" />
          <link rel="stylesheet" href="/assets/app.css?v=20261108" />
        </head>
        <body class="admin min-h-screen bg-paper text-[15px] text-charcoal">
          <header class="sticky top-0 z-30 border-b border-line bg-charcoal text-ivory">
            <div class="mx-auto flex h-14 max-w-6xl items-center justify-between px-4">
              <a href="/admin" class="font-serif tracking-[0.1em]">管理画面</a>
              <div class="flex items-center gap-3 text-xs text-ivory/70">
                <span class="hidden sm:inline">{email}</span>
                <a href="/" target="_blank" class="rounded border border-ivory/30 px-2 py-1 text-ivory">サイトを見る ↗</a>
              </div>
            </div>
            <nav class="no-scrollbar overflow-x-auto border-t border-ivory/10" aria-label="管理メニュー">
              <ul class="mx-auto flex w-max max-w-6xl gap-1 px-2">
                {NAV.map((n) => {
                  const active = n.href === "/admin" ? path === "/admin" : path.startsWith(n.href);
                  return (
                    <li>
                      <a
                        href={n.href}
                        aria-current={active ? "page" : undefined}
                        class={`inline-flex min-h-11 items-center whitespace-nowrap px-3 text-sm ${active ? "border-b-2 border-gold text-gold" : "text-ivory/80"}`}
                      >
                        {n.label}
                      </a>
                    </li>
                  );
                })}
              </ul>
            </nav>
          </header>
          <main class="mx-auto max-w-6xl px-4 pb-24 pt-6">
            <h1 class="mb-5 font-serif text-xl text-leather">{title}</h1>
            {notice && NOTICES[notice] && (
              <p role="status" class="mb-5 rounded border border-green-700/30 bg-green-50 px-4 py-3 text-sm text-green-900">{NOTICES[notice]}</p>
            )}
            {error && <p role="alert" class="mb-5 rounded border border-red-700/30 bg-red-50 px-4 py-3 text-sm text-red-900">{error}</p>}
            {children}
          </main>
          <script src="/assets/admin.js?v=20261108" defer></script>
        </body>
      </html>
    </>
  );
}

export const card = "rounded-md border border-line bg-white p-4 sm:p-6";
export const input =
  "mt-1 block w-full rounded border border-line bg-white px-3 py-2.5 text-[16px] focus:border-camel focus:outline-none";
export const label = "block text-sm font-medium text-charcoal";
export const btn = "inline-flex min-h-11 items-center justify-center rounded px-5 text-sm font-medium";
export const btnPrimary = `${btn} bg-leather text-ivory hover:bg-charcoal`;
export const btnGhost = `${btn} border border-line bg-white text-charcoal hover:border-camel`;
export const btnDanger = `${btn} border border-red-300 bg-white text-red-700 hover:bg-red-50`;

export function Field({ name, title, hint, children }: { name?: string; title: string; hint?: string; children: Child }) {
  return (
    <div>
      <label class={label} for={name}>{title}</label>
      {children}
      {hint && <p class="mt-1 text-xs text-muted">{hint}</p>}
    </div>
  );
}
