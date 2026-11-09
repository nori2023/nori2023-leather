<?php
declare(strict_types=1);

function instagram_icon(string $cls = 'h-5 w-5'): string
{
    return '<svg viewBox="0 0 24 24" fill="none" class="' . e($cls) . '" aria-hidden="true">'
        . '<rect x="3" y="3" width="18" height="18" rx="5" stroke="currentColor" stroke-width="1.5"/>'
        . '<circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="1.5"/>'
        . '<circle cx="17.3" cy="6.7" r="1" fill="currentColor"/></svg>';
}

function button_link(string $path, string $label, string $variant = 'primary'): string
{
    $styles = [
        'primary' => 'bg-leather text-ivory hover:bg-charcoal',
        'outline' => 'border border-leather text-leather hover:bg-leather hover:text-ivory',
        'gold' => 'bg-gold text-ink hover:bg-[#d6b68d]',
        'ghostLight' => 'border border-ivory/70 text-ivory hover:bg-ivory hover:text-ink',
    ];
    return '<a href="' . e(url($path)) . '" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-[2px] px-7 py-3 text-[0.95rem] tracking-[0.12em] transition-colors duration-300 '
        . $styles[$variant] . '">' . e($label) . '</a>';
}

function section_heading(string $title, ?string $eyebrow = null, ?string $lead = null, string $align = 'center'): string
{
    $center = $align === 'center';
    $h = '<div class="mb-10 ' . ($center ? 'text-center' : '') . '">';
    if ($eyebrow) {
        $h .= '<p class="eyebrow mb-3">' . e($eyebrow) . '</p>';
    }
    $h .= '<h2 class="text-[1.45rem] sm:text-[1.8rem]">' . e($title) . '</h2>';
    $h .= '<div class="stitch mt-5 w-16 ' . ($center ? 'mx-auto' : '') . '" aria-hidden="true"></div>';
    if ($lead) {
        $h .= '<p class="mt-6 text-[0.95rem] text-muted ' . ($center ? 'mx-auto max-w-xl' : 'max-w-xl') . '">' . e($lead) . '</p>';
    }
    return $h . '</div>';
}

function placeholder(string $label = '写真準備中'): string
{
    return '<div role="img" aria-label="' . e($label) . '" class="flex h-full w-full flex-col items-center justify-center gap-2 bg-sand p-4 text-center text-muted">'
        . '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" aria-hidden="true" class="opacity-60">'
        . '<rect x="3" y="5" width="18" height="14" rx="1.5" stroke="currentColor" stroke-width="1.2"/>'
        . '<circle cx="9" cy="10" r="1.6" stroke="currentColor" stroke-width="1.2"/>'
        . '<path d="M4 17l5-5 4 4 3-3 4 4" stroke="currentColor" stroke-width="1.2"/></svg>'
        . '<span class="text-xs tracking-[0.2em]">' . e($label) . '</span></div>';
}

function product_img(array $img, string $sizes, bool $eager = false, string $cls = ''): string
{
    return '<img src="' . e(upload_url($img['key_large'])) . '"'
        . ' srcset="' . e(upload_url($img['key_small'])) . ' 640w, ' . e(upload_url($img['key_large'])) . ' 1600w"'
        . ' sizes="' . e($sizes) . '" alt="' . e($img['alt']) . '"'
        . ($img['width'] ? ' width="' . (int) $img['width'] . '" height="' . (int) $img['height'] . '"' : '')
        . ' loading="' . ($eager ? 'eager' : 'lazy') . '" decoding="async"'
        . ' class="absolute inset-0 h-full w-full object-cover ' . e($cls) . '">';
}

function sold_out_overlay(bool $large = false): string
{
    return '<div class="pointer-events-none absolute inset-0 flex items-center justify-center bg-ink/35" aria-label="SOLD OUT">'
        . '<span class="border border-ivory/90 font-serif tracking-[0.3em] text-ivory ' . ($large ? 'px-6 py-2.5 text-xl' : 'px-3 py-1.5 text-sm') . '">SOLD OUT</span></div>';
}

function product_card(array $p, bool $eager = false): string
{
    $img = $p['images'][0] ?? null;
    $soldOut = $p['status'] === 'sold_out';
    $h = '<a href="' . e(url('/shop/' . $p['slug'])) . '" class="group block">';
    $h .= '<div class="relative aspect-[4/5] overflow-hidden rounded-card bg-sand">';
    $h .= $img
        ? product_img($img, '(min-width: 1024px) 30vw, 50vw', $eager, 'transition-transform duration-700 ease-soft group-hover:scale-[1.03]' . ($soldOut ? ' grayscale-[35%]' : ''))
        : placeholder();
    if ($soldOut) {
        $h .= sold_out_overlay();
    }
    if ($p['status'] === 'reserved') {
        $h .= '<span class="absolute left-2 top-2 rounded-[2px] bg-charcoal/85 px-2 py-1 text-[0.68rem] tracking-[0.1em] text-ivory">購入手続き中</span>';
    }
    $h .= '</div><div class="mt-3 px-0.5">';
    if ($p['category_name']) {
        $h .= '<p class="text-[0.68rem] tracking-[0.15em] text-camel">' . e($p['category_name']) . '</p>';
    }
    $h .= '<h3 class="mt-0.5 font-sans text-[0.92rem] font-medium leading-6 tracking-[0.04em] text-charcoal">' . e($p['name']) . '</h3>';
    if ($soldOut && !empty($p['sold_note'])) {
        $h .= '<p class="mt-1 line-clamp-2 text-[0.75rem] leading-5 text-muted">' . e($p['sold_note']) . '</p>';
    }
    if (!$soldOut) {
        $h .= '<p class="mt-1 text-[0.95rem] text-leather">' . e(format_price($p['price'])) . '<span class="ml-1 text-[0.7rem] text-muted">（税込）</span></p>';
    }
    return $h . '</div></a>';
}

function product_grid(array $products, string $emptyHtml): string
{
    if (!$products) {
        return $emptyHtml;
    }
    $h = '<ul class="grid grid-cols-2 gap-x-4 gap-y-9 sm:gap-x-6 lg:grid-cols-3">';
    foreach ($products as $i => $p) {
        $h .= '<li class="reveal">' . product_card($p, $i < 2) . '</li>';
    }
    return $h . '</ul>';
}

function empty_state(string $title, ?string $body = null): string
{
    return '<div class="rounded-card border border-dashed border-line bg-paper px-6 py-14 text-center">'
        . '<p class="font-serif text-leather">' . e($title) . '</p>'
        . ($body ? '<p class="mt-3 text-sm text-muted">' . e($body) . '</p>' : '')
        . '</div>';
}

/** パンくずリスト（$items は [path, label] の配列） */
function breadcrumbs(array $items): string
{
    $all = array_merge([['/', 'トップ']], $items);
    $h = '<nav aria-label="パンくずリスト" class="mx-auto max-w-6xl px-5 pt-5 text-xs text-muted"><ol class="flex flex-wrap items-center gap-x-2 gap-y-1">';
    $last = count($all) - 1;
    $ld = [];
    foreach ($all as $i => [$path, $label]) {
        $h .= '<li class="flex items-center gap-2">';
        if ($i > 0) {
            $h .= '<span aria-hidden="true" class="text-line">/</span>';
        }
        $h .= $i === $last
            ? '<span aria-current="page" class="text-charcoal">' . e($label) . '</span>'
            : '<a href="' . e(url($path)) . '" class="underline-offset-4 hover:underline">' . e($label) . '</a>';
        $h .= '</li>';
        $ld[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $label, 'item' => absolute_url($path)];
    }
    $h .= '</ol>' . json_ld(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $ld]) . '</nav>';
    return $h;
}

function works_strip(): string
{
    $h = '<div class="-mx-5 overflow-x-auto px-5 pb-2 no-scrollbar"><ul class="flex w-max snap-x snap-mandatory gap-3 sm:gap-4">';
    foreach (WORKS as [$src, $alt, $caption]) {
        $h .= '<li class="w-[58vw] max-w-[260px] shrink-0 snap-start"><div class="relative aspect-[4/5] overflow-hidden rounded-card bg-sand">'
            . '<img src="' . e(url($src)) . '" alt="' . e($alt) . '" loading="lazy" decoding="async" class="absolute inset-0 h-full w-full object-cover">'
            . '</div><p class="mt-2 text-center text-xs text-muted">' . e($caption) . '</p></li>';
    }
    return $h . '</ul></div>';
}

/** 商品写真ギャラリー（動きは assets/site.js） */
function product_gallery(array $images, string $name, bool $soldOut): string
{
    $n = count($images);
    $h = '<div data-gallery><div class="relative">';
    $h .= '<div data-gallery-track class="no-scrollbar flex aspect-square snap-x snap-mandatory overflow-x-auto overscroll-x-contain rounded-card bg-sand" aria-roledescription="カルーセル" aria-label="' . e($name) . 'の写真">';
    foreach ($images as $i => $img) {
        $h .= '<button type="button" data-gallery-zoom="' . $i . '" class="relative h-full w-full shrink-0 snap-center cursor-zoom-in" aria-label="写真' . ($i + 1) . 'を拡大表示">'
            . product_img($img, '(min-width: 1024px) 50vw, 100vw', $i === 0) . '</button>';
    }
    $h .= '</div>';
    if ($soldOut) {
        $h .= sold_out_overlay(true);
    }
    if ($n > 1) {
        $h .= '<p class="pointer-events-none absolute bottom-3 right-3 rounded-full bg-ink/60 px-2.5 py-0.5 text-xs text-ivory"><span data-gallery-index>1</span> / ' . $n . '</p>';
    }
    $h .= '</div>';
    if ($n > 1) {
        $h .= '<ul class="mt-3 grid grid-cols-4 gap-2" aria-label="サムネイル一覧">';
        foreach ($images as $i => $img) {
            $h .= '<li><button type="button" data-gallery-thumb="' . $i . '" aria-label="写真' . ($i + 1) . 'を表示" aria-current="' . ($i === 0 ? 'true' : 'false') . '" class="relative block aspect-square w-full overflow-hidden rounded-[2px] border-2 border-transparent opacity-70 transition aria-[current=true]:border-camel aria-[current=true]:opacity-100">'
                . '<img src="' . e(upload_url($img['key_small'])) . '" alt="" loading="lazy" class="absolute inset-0 h-full w-full object-cover"></button></li>';
        }
        $h .= '</ul>';
    }
    $data = array_map(fn ($i) => ['src' => upload_url($i['key_large']), 'alt' => $i['alt']], $images);
    $h .= '<dialog data-gallery-dialog class="m-auto h-dvh max-h-none w-screen max-w-none bg-ink/95 p-0 backdrop:bg-ink/80" aria-label="写真の拡大表示">'
        . '<div class="relative flex h-full w-full items-center justify-center">'
        . '<img data-gallery-full src="" alt="" class="max-h-[85dvh] max-w-[94vw] object-contain">'
        . '<button type="button" data-gallery-close class="absolute right-3 top-3 flex h-12 w-12 items-center justify-center text-2xl text-ivory" aria-label="閉じる">×</button>';
    if ($n > 1) {
        $h .= '<button type="button" data-gallery-prev class="absolute left-1 top-1/2 flex h-14 w-12 -translate-y-1/2 items-center justify-center text-3xl text-ivory/80" aria-label="前の写真">‹</button>'
            . '<button type="button" data-gallery-next class="absolute right-1 top-1/2 flex h-14 w-12 -translate-y-1/2 items-center justify-center text-3xl text-ivory/80" aria-label="次の写真">›</button>';
    }
    $h .= '</div><script type="application/json" data-gallery-data>'
        . str_replace('<', '<', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
        . '</script></dialog></div>';
    return $h;
}

function instagram_feed(array $posts): string
{
    $h = '<section class="bg-leather py-16 text-center text-ivory" aria-labelledby="ig-heading"><div class="mx-auto max-w-5xl px-5">'
        . '<p class="eyebrow !text-gold">Instagram</p>'
        . '<h2 id="ig-heading" class="mt-4 font-serif text-xl tracking-[0.1em] !text-ivory">新作や作りよる様子は<br class="sm:hidden"> Instagram で</h2>'
        . '<p class="mt-3 text-sm text-ivory/75">新しい作品ができたら、まずInstagramでお知らせしよるけぇね。</p>';
    if ($posts) {
        $h .= '<ul class="mt-10 grid grid-cols-3 gap-1.5 sm:gap-3">';
        foreach ($posts as $p) {
            $badge = match ($p['media_type']) {
                'VIDEO' => '<span class="absolute right-1.5 top-1.5 rounded-full bg-ink/60 px-1.5 text-[10px] text-ivory" aria-hidden="true">▶</span>',
                'CAROUSEL_ALBUM' => '<span class="absolute right-1.5 top-1.5 rounded-full bg-ink/60 px-1.5 text-[10px] text-ivory" aria-hidden="true">❐</span>',
                default => '',
            };
            $h .= '<li><a href="' . e($p['permalink']) . '" target="_blank" rel="noopener noreferrer" class="group relative block aspect-square overflow-hidden rounded-[2px] bg-charcoal" aria-label="Instagramの投稿（' . e(format_date($p['posted_at'])) . '）を開く">'
                . '<img src="' . e(upload_url($p['image_key'])) . '" alt="' . e(mb_substr((string) ($p['caption'] ?? 'Instagramの投稿'), 0, 80)) . '" loading="lazy" decoding="async" class="absolute inset-0 h-full w-full object-cover transition-transform duration-700 ease-soft group-hover:scale-105">'
                . $badge . '</a></li>';
        }
        $h .= '</ul>';
    }
    $h .= '<a href="' . e(INSTAGRAM_URL) . '" target="_blank" rel="noopener noreferrer" class="mt-10 inline-flex min-h-12 items-center gap-3 rounded-[2px] border border-gold px-7 py-3 tracking-[0.12em] text-gold transition-colors hover:bg-gold hover:text-ink">'
        . instagram_icon() . ' ' . e(INSTAGRAM_HANDLE) . ' をフォローしてみんさい</a></div></section>';
    return $h;
}
