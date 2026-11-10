<?php
declare(strict_types=1);

require APP_DIR . '/views/components.php';
require APP_DIR . '/views/layout.php';

const IG_EMPTY_NOTE = '新作のお知らせは Instagram（@nori2023_）でしよります。';

const COMING_SOON = [
    '/gallery' => ['SOLD OUT作品集', null],
    '/custom-cardcase' => ['オリジナル名刺入れ・カードケース', '名入れの相談は、お問い合わせからでも受けとりますけぇ。'],
    '/leather-aging' => ['革の経年変化', null],
    '/leather-care' => ['革のお手入れ', null],
    '/workshop' => ['ワークショップ', '開催のお知らせは Instagram（@nori2023_）でもしとります。'],
    '/order' => ['オーダーメイド相談', null],
    '/contact' => ['お問い合わせ', '急ぎの方は Instagram（@nori2023_）のDMから連絡してつかぁさい。'],
    '/legal/tokushoho' => ['特定商取引法に基づく表記', null],
    '/legal/privacy' => ['プライバシーポリシー', null],
    '/legal/terms' => ['利用規約', null],
    '/legal/shipping' => ['配送・送料', null],
    '/legal/returns' => ['返品・交換・キャンセル', null],
];

function route_public(string $path): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'HEAD') {
        http_response_code(405);
        exit;
    }
    match (true) {
        $path === '/' => page_home(),
        $path === '/shop' => page_shop(),
        (bool) preg_match('#^/shop/([a-z0-9-]{1,80})$#', $path, $m) => page_product($m[1]),
        $path === '/about' => page_about(),
        isset(COMING_SOON[$path]) => page_coming_soon($path),
        $path === '/sitemap.xml' => page_sitemap(),
        default => render_not_found(),
    };
}

// ------------------------------------------------------------------ トップ
function page_home(): void
{
    // ヒアドキュメント内で関数の戻り値を埋め込むための小道具
    $h = static fn (string $v): string => $v;
    $hero = hero_setting();
    $onSale = list_public_products(['on_sale', 'reserved'], null, 6);
    $soldOut = list_public_products(['sold_out'], null, 4);
    $igPosts = list_instagram_posts(6);
    $heroImg = $hero['imageKey'] ? upload_url($hero['imageKey']) : url('/images/works/coincase-natural-2.jpg');
    $catch = $hero['catch'];
    $pos = mb_strpos($catch, '、');
    $catchHtml = ($pos !== false && $pos > 0 && $pos < mb_strlen($catch) - 1)
        ? e(mb_substr($catch, 0, $pos + 1)) . '<br>' . e(mb_substr($catch, $pos + 1))
        : e($catch);

    $steps = '';
    foreach ([['裁断', "革の表情を見て\n取る場所を決めるんよ"], ['縫製', "ひと針ずつ\n手で縫うとります"], ['仕上げ', "コバを磨いて\n手触りを整えるんよ"]] as [$t, $d]) {
        $steps .= '<li class="reveal rounded-card border border-line bg-paper px-2 py-6"><p class="font-serif text-lg text-leather">' . e($t) . '</p>'
            . '<p class="mt-2 whitespace-pre-line text-[0.72rem] leading-5 text-muted sm:text-xs">' . e($d) . '</p></li>';
    }

    $body = <<<HTML
<section class="relative overflow-hidden bg-leather text-ivory">
  <div class="hero-dots absolute inset-0 opacity-[0.15]" aria-hidden="true"></div>
  <div class="relative mx-auto grid max-w-6xl md:min-h-[78vh] md:grid-cols-2 md:items-center">
    <div class="relative aspect-[5/4] md:order-2 md:aspect-auto md:h-full">
      <img src="{$h(e($heroImg))}" alt="ヌメ革の作品" fetchpriority="high" class="absolute inset-0 h-full w-full object-cover">
      <div class="absolute inset-0 bg-gradient-to-t from-leather via-transparent to-transparent md:bg-gradient-to-r" aria-hidden="true"></div>
    </div>
    <div class="relative px-6 pb-16 pt-6 md:px-10 md:py-20">
      <p class="eyebrow !text-gold">Nori2023 · Handmade leather goods</p>
      <h1 class="mt-4 text-[1.7rem] leading-snug !text-ivory sm:text-[2.4rem]">{$h(e(SITE_NAME))}</h1>
      <p class="mt-6 font-serif text-[1.15rem] leading-relaxed tracking-[0.1em] text-ivory sm:text-[1.35rem]">「{$catchHtml}」</p>
      <p class="mt-3 text-[0.95rem] text-ivory/80">{$h(e($hero['sub']))}</p>
      <div class="mt-10 flex flex-col gap-3 sm:flex-row">
        {$h(button_link('/shop', '作品、見てみんさい', 'gold'))}
        {$h(button_link('/custom-cardcase', '自分だけの名刺入れ、作らん？', 'ghostLight'))}
      </div>
    </div>
  </div>
</section>

<section class="mx-auto max-w-6xl px-5 py-20 sm:py-24">
  {$h(section_heading('販売中の作品', 'Works on sale', 'ぜんぶ1点ものじゃけぇ、売れてしもうたら同じもんはもう作れんのよ。'))}
  {$h(product_grid($onSale, empty_state('いま新しい作品を作りよるところです', IG_EMPTY_NOTE)))}
  <div class="mt-12 text-center">{$h(button_link('/shop', '作品をぜんぶ見る', 'outline'))}</div>
</section>

<section class="bg-grain border-y border-line/60 py-20 sm:py-24">
  <div class="mx-auto max-w-3xl px-5">
    {$h(section_heading('1点ものへのこだわり', 'One of a kind'))}
    <div class="reveal space-y-6 text-center text-[0.98rem] leading-[2.1]">
      <p>同じ革でも、色も、模様も、手触りもちごうとる。<br>その違いが、ぶち面白いんよ。</p>
      <p>革を切って、穴あけて、縫うて、磨いて。<br>ひとつずつ手で仕上げとるけぇ、<br class="sm:hidden">まったく同じもんはできんのんよ。</p>
      <p>大量生産はしよらん。<br>その革、その時にしかできん1点ものじゃけぇね。</p>
    </div>
    <div class="mt-14">
      <p class="mb-4 text-center text-xs tracking-[0.2em] text-camel">これまでに作ったもんより</p>
      {$h(works_strip())}
    </div>
    <ul class="mt-12 grid grid-cols-3 gap-3 text-center sm:gap-6">{$steps}</ul>
  </div>
</section>

<section class="mx-auto max-w-6xl px-5 py-20 sm:py-24">
  {$h(section_heading('SOLD OUT作品集', 'Sold out gallery', 'もう旅立っていった作品たちです。買うてくれんさって、ほんまにありがとうございました。'))}
  {$h(product_grid($soldOut, empty_state('作品集は、まだ準備しよります')))}
  <div class="mt-12 text-center">{$h(button_link('/gallery', '作品集を見てみる', 'outline'))}</div>
</section>

<section class="bg-ink text-ivory">
  <div class="mx-auto grid max-w-6xl items-center gap-10 px-5 py-20 md:grid-cols-2 md:py-24">
    <div class="relative aspect-[4/3] overflow-hidden rounded-card md:order-2">
      <img src="{$h(e(url('/images/brand/cardcase-open.jpeg')))}" alt="ヌメ革の名刺ケースを開いた状態" loading="lazy" class="absolute inset-0 h-full w-full object-cover">
    </div>
    <div>
      <p class="eyebrow !text-gold">Custom card case</p>
      <h2 class="mt-3 text-[1.4rem] leading-relaxed !text-ivory sm:text-[1.75rem]">大切な人に贈りとうなる、<br>世界にひとつの<br class="sm:hidden">名刺・カードケース。</h2>
      <div class="mt-5 h-px w-16 bg-gold/70" aria-hidden="true"></div>
      <p class="mt-6 text-[0.95rem] leading-8 text-ivory/80">名前やお店のロゴを刻んで作る、ヌメ革のオリジナル名刺ケースよ。自分用はもちろん、誕生日や開店祝い、記念品、退職祝いの贈りもんにもええよ。</p>
      <div class="mt-8">{$h(button_link('/custom-cardcase', 'オリジナル名刺入れ、作ってみる', 'gold'))}</div>
    </div>
  </div>
</section>

<section class="mx-auto grid max-w-6xl items-center gap-10 px-5 py-20 sm:py-24 md:grid-cols-[2fr_3fr]">
  <div class="relative mx-auto aspect-[3/4] w-full max-w-sm overflow-hidden rounded-card">
    <img src="{$h(e(url('/images/site/maker-illustration.jpg')))}" alt="工房で革小物を縫う制作者のイラスト" loading="lazy" class="absolute inset-0 h-full w-full object-cover">
  </div>
  <div>
    {$h(section_heading('作っとるんは、こがぁなおっさんです。', 'About the maker', null, 'left'))}
    <p class="text-[0.96rem] leading-8">革を切って、穴あけて、縫うて、磨いて。そがぁなことをコツコツやりながら、いろんな革小物を作っとります。特に好きなんが革の経年変化。使やぁ使うほど色が深まって、艶が出てくるんよ。新品の革もええけど、使い込んだ革もええよ。</p>
    <div class="mt-8">{$h(button_link('/about', 'おっさんの話を読んでみる', 'outline'))}</div>
  </div>
</section>

<section class="bg-grain border-t border-line/60">
  <div class="mx-auto grid max-w-6xl gap-5 px-5 py-20 sm:py-24 md:grid-cols-2">
    {$h(feature_card('/leather-aging', 'Leather aging', '革は、使うてからが面白いんよ。', '光や手の脂、毎日のこすれで、ヌメ革はちいとずつ色や艶が変わっていくんよ。その変わり方の楽しみ方とお手入れを紹介しとります。', '革の経年変化を見てみる'))}
    {$h(feature_card('/workshop', 'Workshop', 'ワークショップ', 'レザーキーホルダーやカードケースを、自分の手で作ってみんさい。開催の予定はワークショップのページでお知らせしますけぇ。', '開催予定を見る'))}
  </div>
</section>

{$h(instagram_feed($igPosts))}
HTML;

    render_page(['path' => '/'], $body);
    ig_sync_if_due_after_response();
}

function feature_card(string $path, string $eyebrow, string $title, string $body, string $cta): string
{
    return '<a href="' . e(url($path)) . '" class="reveal group flex flex-col rounded-card border border-line bg-paper p-7 transition-colors hover:border-camel sm:p-9">'
        . '<p class="eyebrow">' . e($eyebrow) . '</p><h2 class="mt-3 text-xl">' . e($title) . '</h2>'
        . '<p class="mt-4 flex-1 text-[0.92rem] leading-7 text-muted">' . e($body) . '</p>'
        . '<span class="mt-6 inline-flex min-h-11 items-center text-sm tracking-[0.1em] text-leather">' . e($cta)
        . ' <span class="ml-2 transition-transform group-hover:translate-x-1" aria-hidden="true">→</span></span></a>';
}

// ------------------------------------------------------------------ 商品一覧
function page_shop(): void
{
    $categories = list_categories();
    $raw = query_str('category', 80);
    $current = null;
    foreach ($categories as $c) {
        if ($c['slug'] === $raw) {
            $current = $c['slug'];
        }
    }
    $products = list_public_products(['on_sale', 'reserved'], $current);

    $nav = '';
    if ($categories) {
        $nav = '<nav aria-label="カテゴリー" class="no-scrollbar -mx-5 mb-10 overflow-x-auto px-5"><ul class="flex w-max gap-2 sm:mx-auto">';
        foreach (array_merge([['slug' => null, 'name' => 'すべて']], $categories) as $c) {
            $active = $c['slug'] === $current;
            $href = $c['slug'] ? url('/shop') . '?category=' . rawurlencode($c['slug']) : url('/shop');
            $nav .= '<li><a href="' . e($href) . '"' . ($active ? ' aria-current="page"' : '')
                . ' class="inline-flex min-h-10 items-center rounded-full border px-4 text-sm transition-colors '
                . ($active ? 'border-leather bg-leather text-ivory' : 'border-line bg-paper text-charcoal hover:border-camel') . '">'
                . e($c['name']) . '</a></li>';
        }
        $nav .= '</ul></nav>';
    }
    $empty = empty_state($current ? 'このカテゴリーは、いま売りよる作品がないんよ' : 'いま新しい作品を作りよるところです', IG_EMPTY_NOTE);
    $body = breadcrumbs([['/shop', '販売中の作品']])
        . '<div class="mx-auto max-w-6xl px-5 pb-24 pt-10">'
        . section_heading('販売中の作品', 'Shop', '完成品はぜんぶ1点もの。写真の作品そのものをお届けしますけぇ。')
        . $nav . product_grid($products, $empty)
        . '<p class="mt-16 text-center text-sm text-muted">売れてしもうた作品は <a href="' . e(url('/gallery')) . '" class="text-leather underline underline-offset-4">SOLD OUT作品集</a> で見られるよ。</p></div>';

    render_page([
        'title' => '販売中の作品',
        'description' => 'おっさんがひとつひとつ手で作った、1点ものの革小物じゃけぇ。レザーキーホルダー、コインケース、カードケースなんか。',
        'path' => $current ? '/shop?category=' . $current : '/shop',
    ], $body);
}

// ------------------------------------------------------------------ 商品詳細
function page_product(string $slug): void
{
    $p = get_public_product($slug);
    if (!$p) {
        render_not_found();
        return;
    }
    $soldOut = $p['status'] === 'sold_out';
    $desc = mb_substr(preg_replace('/\s+/u', ' ', $p['description']) ?? '', 0, 110);

    $gallery = $p['images']
        ? product_gallery($p['images'], $p['name'], $soldOut)
        : '<div class="relative aspect-square overflow-hidden rounded-card">' . placeholder() . ($soldOut ? sold_out_overlay(true) : '') . '</div>';

    $info = '';
    if ($p['category_name']) {
        $info .= '<p class="text-xs tracking-[0.18em] text-camel">' . e($p['category_name']) . '</p>';
    }
    $info .= '<h1 class="mt-2 text-[1.4rem] sm:text-[1.7rem]">' . e($p['name']) . '</h1>'
        . '<p class="mt-1 text-xs text-muted">商品番号：' . e($p['code']) . '</p>';
    if (!$soldOut) {
        $info .= '<p class="mt-6 font-serif text-[1.6rem] text-leather">' . e(format_price($p['price'])) . '<span class="ml-1 font-sans text-xs text-muted">（税込）</span></p>';
    }
    $info .= purchase_area($p);
    if ($p['description'] !== '') {
        $info .= '<div class="mt-10 whitespace-pre-line text-[0.95rem] leading-8">' . e($p['description']) . '</div>';
    }
    $specs = [['素材', $p['material']], ['サイズ', $p['size']], ['革の色', $p['color']]];
    if (!$soldOut) {
        $specs[] = ['送料', $p['shipping_name']
            ? $p['shipping_name'] . ($p['shipping_fee'] !== null ? '　' . format_price($p['shipping_fee']) . '（税込）' : '')
            : '購入手続きのときにお知らせします'];
    }
    $specs[] = ['在庫', $soldOut ? 'なし' : '1点だけ'];
    $info .= '<dl class="mt-10 divide-y divide-line border-y border-line text-sm">';
    foreach ($specs as [$label, $value]) {
        if ($value !== null && $value !== '') {
            $info .= '<div class="grid grid-cols-[6rem_1fr] gap-3 py-3"><dt class="text-muted">' . e($label) . '</dt><dd>' . e($value) . '</dd></div>';
        }
    }
    $info .= '</dl><p class="mt-6 text-xs leading-6 text-muted">※ 手作業で仕立てとるけぇ、革の色味・シボ・小さなキズなんかは1点ずつ違います。写真の作品そのものをお届けします。<br>※ 見よる画面によって、実物と色の見え方がちごうことがあります。</p>'
        . '<div class="mt-8 flex flex-wrap gap-x-6 gap-y-2 text-sm">'
        . '<a href="' . e(url('/leather-care')) . '" class="text-leather underline underline-offset-4">革のお手入れ</a>'
        . '<a href="' . e(url('/legal/shipping')) . '" class="text-leather underline underline-offset-4">配送・送料</a>'
        . '<a href="' . e(url('/contact')) . '" class="text-leather underline underline-offset-4">この作品のこと、聞いてみる</a></div>';

    $body = breadcrumbs([
        $soldOut ? ['/gallery', 'SOLD OUT作品集'] : ['/shop', '販売中の作品'],
        ['/shop/' . $p['slug'], $p['name']],
    ]) . '<article class="mx-auto grid max-w-6xl gap-10 px-5 pb-24 pt-6 lg:grid-cols-2 lg:gap-14"><div>' . $gallery . '</div><div>' . $info . '</div></article>';

    $ld = [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $p['name'],
        'sku' => $p['code'],
        'description' => $p['description'],
        'image' => array_map(fn ($i) => absolute_url('/' . UPLOAD_DIR_NAME . '/' . $i['key_large']), $p['images']),
        'brand' => ['@type' => 'Brand', 'name' => SITE_BRAND],
        'offers' => [
            '@type' => 'Offer',
            'url' => absolute_url('/shop/' . $p['slug']),
            'priceCurrency' => 'JPY',
            'price' => $p['price'],
            'availability' => $p['status'] === 'on_sale' ? 'https://schema.org/InStock' : 'https://schema.org/SoldOut',
            'itemCondition' => 'https://schema.org/NewCondition',
        ],
    ];
    if ($p['category_name']) {
        $ld['category'] = $p['category_name'];
    }

    render_page([
        'title' => $soldOut ? $p['name'] . '（SOLD OUT）' : $p['name'],
        'description' => $desc ?: SITE_DESCRIPTION,
        'path' => '/shop/' . $p['slug'],
        'image' => $p['images'] ? upload_url($p['images'][0]['key_large']) : null,
        'jsonLd' => [$ld],
    ], $body);
}

function purchase_area(array $p): string
{
    if ($p['status'] === 'sold_out') {
        $h = '<div class="mt-8 rounded-card border border-line bg-paper px-5 py-6 text-center">'
            . '<p class="font-serif tracking-[0.2em] text-leather">SOLD OUT</p>'
            . '<p class="mt-3 text-sm leading-7">この作品は売れてしまいました。<br>買うてくれんさって、ありがとうございました。</p>';
        if (!empty($p['sold_note'])) {
            $h .= '<div class="mt-5 border-t border-line pt-5 text-left"><p class="text-xs tracking-[0.2em] text-camel">おっさんからひとこと</p>'
                . '<p class="mt-2 whitespace-pre-line text-sm leading-7">' . e($p['sold_note']) . '</p></div>';
        }
        return $h . '</div>';
    }
    if ($p['status'] === 'reserved') {
        return '<div class="mt-8 rounded-card border border-line bg-paper px-5 py-5 text-center text-sm leading-7">'
            . '<p class="font-medium text-leather">いま、ほかのお客さんが購入手続きしよります</p>'
            . '<p class="mt-1 text-muted">手続きが終わらんかったら、また販売中に戻りますけぇね。</p></div>';
    }
    // STEP 3（在庫確保・決済）が完成し sales.enabled=true になるまで購入不可
    $enabled = sales_enabled();
    $h = '<div class="mt-8"><button type="button" disabled class="flex min-h-14 w-full items-center justify-center rounded-[2px] bg-leather text-[1rem] tracking-[0.15em] text-ivory disabled:cursor-not-allowed disabled:opacity-50">'
        . ($enabled ? '購入手続きへ進む' : 'ネットでの購入は、いま準備しよります') . '</button>';
    if (!$enabled) {
        $h .= '<p class="mt-3 text-center text-xs text-muted">欲しい方は <a href="' . e(url('/contact')) . '" class="text-leather underline underline-offset-4">お問い合わせ</a> から声かけてつかぁさい。</p>';
    }
    $h .= '<p class="mt-3 text-center text-xs text-muted">' . e(MAKER_SELLER_TEXT) . '</p>';
    return $h . '</div>';
}

// ------------------------------------------------------------------ 制作者紹介
const DEFAULT_ABOUT = "はじめまして。\n「おっさんのレザークラフト」です。\n\n革を切って、穴あけて、縫うて、磨いて。\nそがぁなことをコツコツやりながら、いろんな革小物を作っとります。\n\n同じ革でも、色も模様も手触りもちごうとる。\nその違いが面白いんよ。\n\n完成品は基本的に1点もの。\nそれと、ヌメ革の名刺入れやカードケースなんかに、名前やロゴを入れた作品も作っとります。\n\n特に好きなんが革の経年変化。\n使やぁ使うほど色が深まって、艶が出てくる。\n新品の革もええけど、使い込んだ革もええよ。\n\nそがぁな革のええところを楽しんでもろうたら、うれしいです。";

function page_about(): void
{
    $about = get_setting('page.about', ['body' => null]);
    $text = is_string($about['body'] ?? null) && $about['body'] !== '' ? $about['body'] : DEFAULT_ABOUT;
    $paras = '';
    foreach (preg_split("/\n\s*\n/u", $text) ?: [] as $para) {
        $paras .= '<p class="whitespace-pre-line">' . e(trim($para)) . '</p>';
    }
    $slots = [
        ['工房', '/images/site/workshop.jpg', '革や糸、道具が並ぶ工房の作業机'],
        ['革の裁断', null, null],
        ['刻印', '/images/works/keyholder-engraved.jpg', '名前を刻印したレザーキーホルダー'],
        ['縫製・組み立て', null, null],
        ['完成作品', '/images/works/backpack-collage.jpg', 'ヌメ革のリュックサック'],
    ];
    $photos = '';
    foreach ($slots as [$label, $src, $alt]) {
        $photos .= '<li><div class="relative aspect-square overflow-hidden rounded-card">'
            . ($src ? '<img src="' . e(url($src)) . '" alt="' . e($alt) . '" loading="lazy" class="absolute inset-0 h-full w-full object-cover">' : placeholder($label . '（準備中）'))
            . '</div><p class="mt-2 text-center text-xs text-muted">' . e($label) . '</p></li>';
    }
    $body = breadcrumbs([['/about', '制作者紹介']]) . '<article class="pb-24">'
        . '<header class="mx-auto max-w-3xl px-5 pb-12 pt-10 text-center"><p class="eyebrow">About the maker</p>'
        . '<h1 class="mt-3 text-[1.55rem] sm:text-[2rem]">作っとるんは、<br class="sm:hidden">こがぁなおっさんです。</h1>'
        . '<div class="stitch mx-auto mt-6 w-16" aria-hidden="true"></div></header>'
        . '<div class="mx-auto grid max-w-5xl gap-10 px-5 md:grid-cols-[2fr_3fr] md:items-start">'
        . '<div class="space-y-4 md:sticky md:top-28"><div class="relative aspect-[4/5] overflow-hidden rounded-card">'
        . '<img src="' . e(url('/images/site/maker-illustration.jpg')) . '" alt="工房で革小物を縫う制作者のイラスト" class="absolute inset-0 h-full w-full object-cover"></div>'
        . '<img src="' . e(url('/images/brand/nori2023-logo.jpeg')) . '" alt="革に刻印されたNori2023のロゴ" loading="lazy" class="mx-auto h-32 w-32 rounded-full border border-line object-cover"></div>'
        . '<div class="space-y-6 text-[1rem] leading-[2.15]">' . $paras
        . '<a href="' . e(INSTAGRAM_URL) . '" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-12 items-center gap-2 text-leather underline-offset-4 hover:underline">'
        . instagram_icon() . ' Instagram：' . e(INSTAGRAM_HANDLE) . '</a></div></div>'
        . '<section class="mx-auto mt-20 max-w-6xl px-5" aria-labelledby="workshop-photos"><h2 id="workshop-photos" class="mb-8 text-center text-xl">作りよる様子</h2>'
        . '<ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">' . $photos . '</ul></section>'
        . '<div class="mt-16 flex flex-col items-center gap-3 px-5 sm:flex-row sm:justify-center">'
        . button_link('/shop', '作品、見てみんさい') . button_link('/custom-cardcase', '自分だけの名刺入れ、作らん？', 'outline') . '</div></article>';

    render_page([
        'title' => '制作者紹介',
        'description' => '作っとるんは、こがぁなおっさんです。革を切って、穴あけて、縫うて、磨いて。おっさんのレザークラフト（Nori2023）の制作者紹介。',
        'path' => '/about',
    ], $body);
}

// ------------------------------------------------------------------ 準備中ページ
function page_coming_soon(string $path): void
{
    [$title, $note] = COMING_SOON[$path];
    $body = breadcrumbs([[$path, $title]]) . '<div class="mx-auto max-w-xl px-5 py-24 text-center"><p class="eyebrow">Coming soon</p>'
        . '<h1 class="mt-3 text-[1.5rem]">' . e($title) . '</h1><div class="stitch mx-auto mt-6 w-16" aria-hidden="true"></div>'
        . '<p class="mt-8 text-[0.95rem] leading-8 text-muted">このページは、いま準備しよります。<br>もうちいと待っとってつかぁさい。</p>'
        . ($note ? '<p class="mt-4 text-sm text-muted">' . e($note) . '</p>' : '')
        . '<div class="mt-10">' . button_link('/', 'トップに戻る', 'outline') . '</div></div>';
    render_page(['title' => $title, 'path' => $path, 'noindex' => true], $body);
}

// ------------------------------------------------------------------ サイトマップ
function page_sitemap(): void
{
    $paths = ['/', '/shop', '/gallery', '/custom-cardcase', '/about', '/leather-aging', '/leather-care', '/workshop', '/order', '/contact'];
    foreach (list_public_products() as $p) {
        $paths[] = '/shop/' . $p['slug'];
    }
    header('Content-Type: application/xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($paths as $p) {
        echo '  <url><loc>' . e(absolute_url($p)) . "</loc></url>\n";
    }
    echo "</urlset>\n";
}
