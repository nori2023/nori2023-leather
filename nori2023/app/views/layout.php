<?php
declare(strict_types=1);

/**
 * 公開ページの共通レイアウト
 * $meta: title, description, path, image(サイト内パス or 絶対URL), noindex, jsonLd(array)
 */
function render_page(array $meta, string $body, int $status = 200): void
{
    http_response_code($status);
    send_security_headers();
    header('Content-Type: text/html; charset=utf-8');

    $title = isset($meta['title']) ? $meta['title'] . '｜' . SITE_NAME : SITE_NAME . '｜' . SITE_BRAND . ' 手仕事の1点もの革小物';
    $desc = $meta['description'] ?? SITE_DESCRIPTION;
    $path = $meta['path'] ?? '/';
    $image = $meta['image'] ?? '/images/works/coincase-natural-2.jpg';
    $imageUrl = str_starts_with($image, 'http') ? $image : absolute_url($image);
    ?>
<!DOCTYPE html>
<html lang="ja" class="h-full">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<meta name="theme-color" content="#493326">
<link rel="canonical" href="<?= e(absolute_url($path)) ?>">
<?php if (!empty($meta['noindex'])): ?><meta name="robots" content="noindex">
<?php endif ?>
<meta property="og:type" content="website">
<meta property="og:locale" content="ja_JP">
<meta property="og:site_name" content="<?= e(SITE_NAME) ?>">
<meta property="og:title" content="<?= e($meta['title'] ?? SITE_NAME) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:url" content="<?= e(absolute_url($path)) ?>">
<meta property="og:image" content="<?= e($imageUrl) ?>">
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" href="<?= e(url('/favicon.svg')) ?>" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500&family=Noto+Serif+JP:wght@500;600&display=swap">
<link rel="stylesheet" href="<?= e(asset('assets/app.css')) ?>">
<?php foreach ($meta['jsonLd'] ?? [] as $ld): ?><?= json_ld($ld) ?>
<?php endforeach ?>
</head>
<body class="flex min-h-full flex-col">
<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-50 focus:bg-ivory focus:px-4 focus:py-2">本文へスキップ</a>
<?= site_header() ?>
<main id="main" class="flex-1">
<?= $body ?>
</main>
<?= site_footer() ?>
<script src="<?= e(asset('assets/site.js')) ?>" defer></script>
</body>
</html>
<?php
}

function site_header(): string
{
    $h = '<header class="sticky top-0 z-30 border-b border-line/70 bg-ivory/95"><div class="mx-auto flex h-16 max-w-6xl items-center justify-between pl-5 pr-2 lg:h-20 lg:px-5">'
        . '<a href="' . e(url('/')) . '" class="flex flex-col leading-none" aria-label="' . e(SITE_NAME) . ' トップへ">'
        . '<span class="font-serif text-[1.02rem] font-semibold tracking-[0.12em] text-leather sm:text-lg">' . e(SITE_NAME) . '</span>'
        . '<span class="mt-1 text-[0.62rem] tracking-[0.4em] text-camel">' . e(strtoupper(SITE_BRAND)) . '</span></a>'
        . '<nav aria-label="メインメニュー" class="hidden lg:block"><ul class="flex items-center gap-7 text-[0.85rem] tracking-[0.08em]">';
    foreach (MAIN_NAV as [$path, $label]) {
        $h .= '<li><a href="' . e(url($path)) . '" class="text-charcoal transition-colors hover:text-camel">' . e($label) . '</a></li>';
    }
    $h .= '<li><a href="' . e(INSTAGRAM_URL) . '" target="_blank" rel="noopener noreferrer" aria-label="Instagram ' . e(INSTAGRAM_HANDLE) . '" class="text-leather hover:text-camel">' . instagram_icon() . '</a></li></ul></nav>'
        . '<div class="lg:hidden"><button type="button" data-menu-toggle aria-expanded="false" aria-controls="mobile-nav" aria-label="メニューを開く" class="relative z-50 flex h-12 w-12 items-center justify-center text-leather">'
        . '<span class="relative block h-3.5 w-6" aria-hidden="true">'
        . '<span class="menu-bar absolute left-0 top-0 h-px w-6 bg-current transition-transform duration-300"></span>'
        . '<span class="menu-bar absolute left-0 top-1.5 h-px w-6 bg-current transition-opacity duration-300"></span>'
        . '<span class="menu-bar absolute left-0 top-3 h-px w-6 bg-current transition-transform duration-300"></span>'
        . '</span></button></div></div>'
        . '<div id="mobile-nav" hidden class="fixed inset-0 z-40 overflow-y-auto bg-ivory px-6 pb-10 pt-24 lg:hidden"><nav aria-label="メインメニュー"><ul class="divide-y divide-line border-y border-line">';
    foreach (array_merge([['/', 'トップ']], MAIN_NAV) as [$path, $label]) {
        $h .= '<li><a href="' . e(url($path)) . '" class="flex min-h-14 items-center justify-between py-3 font-serif text-[1.05rem] text-leather">' . e($label) . '<span aria-hidden="true" class="text-camel">›</span></a></li>';
    }
    $h .= '</ul></nav><a href="' . e(INSTAGRAM_URL) . '" target="_blank" rel="noopener noreferrer" class="mt-8 inline-flex min-h-12 items-center gap-2 text-sm text-leather">'
        . instagram_icon() . ' Instagram ' . e(INSTAGRAM_HANDLE) . '</a></div></header>';
    return $h;
}

function site_footer(): string
{
    $h = '<footer class="mt-auto bg-charcoal text-ivory/85"><div class="mx-auto max-w-6xl px-5 py-14"><div class="flex flex-col gap-10 md:flex-row md:justify-between"><div class="max-w-xs">'
        . '<img src="' . e(url('/images/brand/nori2023-logo.jpeg')) . '" alt="Nori2023 ロゴ" width="80" height="80" loading="lazy" class="mb-5 h-20 w-20 rounded-full border border-gold/40 object-cover">'
        . '<p class="font-serif text-lg tracking-[0.12em] text-ivory">' . e(SITE_NAME) . '</p>'
        . '<p class="mt-1 text-xs tracking-[0.35em] text-gold">' . e(strtoupper(SITE_BRAND)) . '</p>'
        . '<p class="mt-4 text-sm leading-7 text-ivory/70">' . e(SITE_CATCH) . '</p>'
        . '<a href="' . e(INSTAGRAM_URL) . '" target="_blank" rel="noopener noreferrer" class="mt-5 inline-flex min-h-11 items-center gap-2 text-sm text-gold hover:text-ivory">' . instagram_icon() . ' ' . e(INSTAGRAM_HANDLE) . '</a>'
        . '</div><div class="grid grid-cols-1 gap-8 text-sm sm:grid-cols-2">';
    foreach ([MAIN_NAV, FOOTER_NAV] as $nav) {
        $h .= '<ul class="space-y-1">';
        foreach ($nav as [$path, $label]) {
            $h .= '<li><a href="' . e(url($path)) . '" class="inline-flex min-h-10 items-center hover:text-gold">' . e($label) . '</a></li>';
        }
        $h .= '</ul>';
    }
    $h .= '</div></div><div class="stitch mt-12 opacity-40" aria-hidden="true"></div>'
        . '<p class="mt-6 text-center text-xs tracking-[0.15em] text-ivory/50">© ' . e(SITE_BRAND) . ' ' . e(SITE_NAME) . '</p></div></footer>';
    return $h;
}

function render_not_found(): void
{
    $body = '<div class="mx-auto flex max-w-md flex-col items-center px-5 py-24 text-center"><p class="eyebrow">404</p>'
        . '<h1 class="mt-3 text-xl">探しよるページが見つからんかったんよ</h1>'
        . '<p class="mt-4 text-sm text-muted">作品が売れてしもうたか、URLが変わったんかもしれん。</p>'
        . '<div class="mt-8">' . button_link('/', 'トップに戻る', 'outline') . '</div></div>';
    render_page(['title' => 'ページが見つからんのよ', 'path' => '/404', 'noindex' => true], $body, 404);
}
