<?php
declare(strict_types=1);

require APP_DIR . '/views/components.php';
require APP_DIR . '/views/layout.php';
require APP_DIR . '/routes/public_content.php';

const IG_EMPTY_NOTE = '新作のお知らせは Instagram（@nori2023_）でしよります。';

const COMING_SOON = [
    '/leather-care' => ['革のお手入れ', null],
    '/order' => ['オーダーメイド相談', null],
    '/legal/terms' => ['利用規約', null],
];

function route_public(string $path): void
{
    if ($path === '/visit' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        counter_visit();
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'HEAD') {
        http_response_code(405);
        exit;
    }
    match (true) {
        $path === '/' => page_home(),
        $path === '/shop' => page_shop(),
        (bool) preg_match('#^/shop/([a-z0-9-]{1,80})$#', $path, $m) => page_product($m[1]),
        $path === '/about' => page_about(),
        $path === '/gallery' => page_gallery(),
        $path === '/workshop' => page_workshop(),
        $path === '/leather-aging' => page_leather_aging(),
        $path === '/legal/shipping' => page_shipping(),
        $path === '/legal/privacy' => page_privacy(),
        $path === '/legal/tokushoho' => page_tokushoho(),
        $path === '/legal/returns' => page_returns(),
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
    $heroImg = $hero['imageKey'] ? upload_url($hero['imageKey']) : url(HERO_DEFAULT_IMAGE);
    $catch = $hero['catch'];
    $pos = mb_strpos($catch, '、');
    // 1行ずつ下からせり上がるように、行ごとに包む
    $catchLines = ($pos !== false && $pos > 0 && $pos < mb_strlen($catch) - 1) ? [mb_substr($catch, 0, $pos + 1), mb_substr($catch, $pos + 1)] : [$catch];
    $catchHtml = '';
    foreach ($catchLines as $i => $line) {
        $catchHtml .= '<span class="hero-line"><span class="hero-d' . ($i + 2) . '">' . ($i === 0 ? '「' : '') . e($line) . ($i === count($catchLines) - 1 ? '」' : '') . '</span></span>';
    }

    $steps = '';
    foreach ([['裁断', "革の表情を見て\n取る場所を決めるんよ"], ['縫製', "ひと針ずつ\n手で縫うとります"], ['仕上げ', "コバを磨いて\n手触りを整えるんよ"]] as [$t, $d]) {
        $steps .= '<li class="reveal rounded-card border border-line bg-paper px-2 py-6"><p class="font-serif text-lg text-leather">' . e($t) . '</p>'
            . '<p class="mt-2 whitespace-pre-line text-[0.72rem] leading-5 text-muted sm:text-xs">' . e($d) . '</p></li>';
    }

    $body = <<<HTML
<section class="hero relative overflow-hidden bg-leather text-ivory">
  <div class="hero-dots absolute inset-0 opacity-[0.15]" aria-hidden="true"></div>
  <div class="relative mx-auto grid max-w-6xl md:min-h-[82vh] md:grid-cols-2 md:items-center">
    <div class="relative aspect-[5/4] overflow-hidden md:order-2 md:aspect-auto md:h-full">
      <img src="{$h(e($heroImg))}" alt="おっさんが手で縫ったヌメ革のランドセル" fetchpriority="high" class="hero-kb absolute inset-0 h-full w-full object-cover object-[50%_62%]">
      <div class="absolute inset-0 bg-gradient-to-t from-leather via-transparent to-transparent md:bg-gradient-to-r" aria-hidden="true"></div>
    </div>
    <div class="relative px-6 pb-16 pt-6 md:px-10 md:py-20">
      <p class="eyebrow hero-ey !text-gold">Nori2023 · Handmade leather goods</p>
      <h1 class="mt-4 text-[1.7rem] leading-snug !text-ivory sm:text-[2.4rem]"><span class="hero-line"><span class="hero-d1">{$h(e(SITE_NAME))}</span></span></h1>
      <p class="mt-6 font-serif text-[1.15rem] leading-relaxed tracking-[0.1em] text-ivory sm:text-[1.35rem]">{$catchHtml}</p>
      <div class="hero-gl mt-6 h-px w-16 bg-gold/80" aria-hidden="true"></div>
      <p class="hero-fade hero-f1 mt-5 text-[0.95rem] text-ivory/80">{$h(e($hero['sub']))}</p>
      <div class="hero-fade hero-f2 mt-10 flex flex-col gap-3 sm:flex-row">
        <span class="hero-shine">{$h(button_link('/shop', '作品、見てみんさい', 'gold'))}</span>
        {$h(button_link('/custom-cardcase', '自分だけの名刺入れ、作らん？', 'ghostLight'))}
      </div>
    </div>
  </div>
  <div class="hero-scroll" aria-hidden="true">SCROLL<i></i></div>
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
    <div class="relative mx-auto aspect-square w-full max-w-md overflow-hidden rounded-card md:order-2 md:max-w-lg">
      <img src="{$h(e(url('/images/brand/cardcase-gift2.jpg')))}" alt="名入れ・ロゴ刻印ができる、封筒型の名刺・カードケース" loading="lazy" data-zoom class="absolute inset-0 h-full w-full object-cover">
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
    <img src="{$h(e(url('/images/site/maker-illustration.jpg')))}" alt="工房で革小物を縫う制作者のイラスト" loading="lazy" data-zoom class="absolute inset-0 h-full w-full object-cover">
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

{$h(counter_section())}
HTML;

    render_page(['path' => '/', 'headScripts' => [['src' => asset('assets/counter.js')]]], $body);
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
function page_gallery(): void
{
    $products = list_public_products(['sold_out'], null, 300);
    $body = breadcrumbs([['/gallery', 'SOLD OUT作品集']])
        . '<div class="mx-auto max-w-6xl px-5 pb-24 pt-10">'
        . section_heading('SOLD OUT作品集', 'Sold out gallery', 'もう旅立っていった作品たちです。買うてくれんさって、ほんまにありがとうございました。')
        . product_grid($products, empty_state('作品集は、まだ準備しよります', IG_EMPTY_NOTE))
        . '<div class="mt-16 flex flex-wrap justify-center gap-3">' . button_link('/shop', '販売中の作品を見る') . button_link('/custom-cardcase', '自分だけの名刺入れ、作らん？', 'outline') . '</div></div>';
    render_page([
        'title' => 'SOLD OUT作品集',
        'path' => '/gallery',
        'description' => 'これまでに売れた、おっさんの1点ものの革小物の作品集です。',
    ], $body);
}

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
    release_expired_reservations();
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
        . '<p class="mt-1 text-xs text-muted">品番：' . e($p['code']) . '</p>';
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
        . '<a href="' . e(url('/legal/returns')) . '" class="text-leather underline underline-offset-4">返品・交換</a>'
        . '<a href="' . e(url('/legal/tokushoho')) . '" class="text-leather underline underline-offset-4">特定商取引法に基づく表記</a>'
        . '<a href="' . e(url('/contact') . '?item=' . rawurlencode($p['slug'])) . '" class="text-leather underline underline-offset-4">この作品のこと、聞いてみる</a></div>';

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
    // 決済設定が「公開」、またはテスト担当の端末のときだけ購入できる
    $enabled = checkout_open() && product_shippable($p);
    if ($enabled) {
        $h = '<div class="mt-8"><a href="' . e(url('/checkout/' . $p['slug'])) . '" class="flex min-h-14 w-full items-center justify-center rounded-[2px] bg-leather text-[1rem] tracking-[0.15em] text-ivory hover:bg-charcoal">購入手続きへ進む</a>'
            . '<p class="mt-3 text-center text-xs text-muted">お支払いはクレジットカード（一括）です。</p>';
    } else {
        $h = '<div class="mt-8"><button type="button" disabled class="flex min-h-14 w-full items-center justify-center rounded-[2px] bg-leather text-[1rem] tracking-[0.15em] text-ivory disabled:cursor-not-allowed disabled:opacity-50">ネットでの購入は、いま準備しよります</button>'
            . '<p class="mt-3 text-center text-xs text-muted">欲しい方は <a href="' . e(url('/contact') . '?item=' . rawurlencode($p['slug'])) . '" class="text-leather underline underline-offset-4">お問い合わせ</a> から声かけてつかぁさい。</p>';
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
    // 文章の中の「Instagram：@nori2023_」だけの行は、下にリンク付きで出すので重ねて表示しない
    $text = preg_replace('/^[ \t　]*Instagram[ \t　]*[:：][ \t　]*@?' . preg_quote(ltrim(INSTAGRAM_HANDLE, '@'), '/') . '[ \t　]*$/mu', '', $text) ?? $text;
    $paras = '';
    foreach (preg_split("/\n\s*\n/u", $text) ?: [] as $para) {
        if (trim($para) === '') {
            continue;
        }
        $paras .= '<p class="whitespace-pre-line">' . e(trim($para)) . '</p>';
    }
    $slots = [
        ['工房', '/images/site/workshop.jpg', '革や糸、道具が並ぶ工房の作業机'],
        ['革の型抜き', '/images/site/cutting.jpg', '革の上に抜き型をのせて、プレス機で型抜きするところ'],
        ['刻印', '/images/works/keyholder-engraved.jpg', '名前を刻印したレザーキーホルダー'],
        ['縫製・組み立て', '/images/site/sewing.jpg', '2本の針で、ヌメ革のカードポケットを手縫いしているところ'],
        ['完成作品', '/images/works/backpack-collage.jpg', 'ヌメ革のリュックサック'],
    ];
    $photos = '';
    foreach ($slots as [$label, $src, $alt]) {
        $photos .= '<li><div class="relative aspect-square overflow-hidden rounded-card">'
            . ($src ? '<img src="' . e(url($src)) . '" alt="' . e($alt) . '" loading="lazy" data-zoom class="absolute inset-0 h-full w-full object-cover">' : placeholder($label . '（準備中）'))
            . '</div><p class="mt-2 text-center text-xs text-muted">' . e($label) . '</p></li>';
    }
    $body = breadcrumbs([['/about', '制作者紹介']]) . '<article class="pb-24">'
        . '<header class="mx-auto max-w-3xl px-5 pb-12 pt-10 text-center"><p class="eyebrow">About the maker</p>'
        . '<h1 class="mt-3 text-[1.55rem] sm:text-[2rem]">作っとるんは、<br class="sm:hidden">こがぁなおっさんです。</h1>'
        . '<div class="stitch mx-auto mt-6 w-16" aria-hidden="true"></div></header>'
        . '<div class="mx-auto grid max-w-5xl gap-10 px-5 md:grid-cols-[2fr_3fr] md:items-start">'
        . '<div class="space-y-4 md:sticky md:top-28"><div class="relative aspect-[4/5] overflow-hidden rounded-card">'
        . '<img src="' . e(url('/images/site/maker-illustration.jpg')) . '" alt="工房で革小物を縫う制作者のイラスト" data-zoom class="absolute inset-0 h-full w-full object-cover"></div>'
        . '<img src="' . e(url('/images/brand/nori2023-logo.jpeg')) . '" alt="革に刻印されたNori2023のロゴ" loading="lazy" data-zoom class="mx-auto h-32 w-32 rounded-full border border-line object-cover"></div>'
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

// ------------------------------------------------------------------ 法定表記（標準語）
function legal_value(?string $v): string
{
    return $v === null || $v === '' ? '<span class="text-muted">' . e(LEGAL_TBD) . '</span>' : e($v);
}

function returns_text(): array
{
    $days = SELLER_INFO['report_days'];
    return [
        '返品・交換について' => '商品はすべて手作りの1点ものです。そのため、お客様のご都合による返品・交換はお受けしておりません。',
        '不良品・破損・誤配送の場合' => '商品に不良・破損があった場合や、ご注文と異なる商品が届いた場合は、返品・交換または返金にて対応いたします。'
            . ($days ? "商品到着後{$days}日以内に" : '商品到着後、お早めに') . 'メールにてご連絡ください。返送にかかる送料は当社が負担いたします。',
        '対応できない場合' => '革の色味・シボ・小さなキズ、使用に伴う色の変化（経年変化）は天然皮革の特性であり、不良品には該当しません。お客様のもとで使用・加工された商品も対応いたしかねます。',
    ];
}

function legal_page(string $path, string $title, string $eyebrow, string $inner): void
{
    $body = breadcrumbs([[$path, $title]]) . '<article class="mx-auto max-w-3xl px-5 pb-24 pt-10">'
        . '<header class="text-center"><p class="eyebrow">' . e($eyebrow) . '</p><h1 class="mt-3 text-[1.4rem] sm:text-[1.7rem]">' . e($title) . '</h1>'
        . '<div class="stitch mx-auto mt-6 w-16" aria-hidden="true"></div></header>' . $inner . '</article>';
    render_page(['title' => $title, 'path' => $path], $body);
}

function legal_table(array $rows): string
{
    $h = '<dl class="mt-10 divide-y divide-line border-y border-line text-sm leading-7">';
    foreach ($rows as $k => $v) {
        $h .= '<div class="grid gap-1 py-4 sm:grid-cols-[12rem_1fr] sm:gap-6"><dt class="font-medium text-leather">' . e($k) . '</dt><dd>' . $v . '</dd></div>';
    }
    return $h . '</dl>';
}

function page_tokushoho(): void
{
    $s = SELLER_INFO;
    $returns = '';
    foreach (returns_text() as $k => $v) {
        $returns .= '<p class="mt-1"><span class="font-medium">' . e($k) . '：</span>' . e($v) . '</p>';
    }
    $rows = [
        '販売業者' => e($s['company']),
        '販売責任者' => e($s['manager']),
        '所在地' => e($s['postal']) . '<br>' . e($s['address']),
        '電話番号' => e($s['tel']) . '<br><span class="text-xs text-muted">受付時間：</span>' . legal_value($s['tel_hours'])
            . '<br><span class="text-xs text-muted">お問い合わせは、できるだけメールでお願いいたします。</span>',
        'メールアドレス' => e($s['email']),
        '制作' => e(MAKER_NAME) . '（商品の制作を担当しています）',
        '販売価格' => '各商品ページに税込価格で表示しています。',
        '商品代金以外の必要料金' => '送料' . (($ft = shipping_fee_text()) ? '<br>' . $ft : '') . '<br><span class="text-xs text-muted">商品ごとの配送方法は各商品ページに、くわしくは<a href="' . e(url('/legal/shipping')) . '" class="text-leather underline underline-offset-4">配送・送料</a>のページに表示しています。</span>',
        'お支払い方法' => 'クレジットカード決済（3Dセキュア本人認証に対応）',
        'お支払い時期' => 'ご注文確定時にお支払いが確定します。',
        '商品のお届け時期' => legal_value($s['delivery']),
        'ご注文後のキャンセル' => legal_value($s['cancel']),
        '返品・交換' => $returns,
    ];
    legal_page('/legal/tokushoho', '特定商取引法に基づく表記', 'Legal notice', legal_table($rows));
}

/** 有効な配送方法の一覧（送料の説明に使う） */
function active_shipping(): array
{
    return db_all('SELECT name, fee, note FROM shipping_profiles WHERE is_active = 1 ORDER BY fee IS NULL, fee, name');
}

function shipping_fee_text(): string
{
    $list = array_map(static fn ($r) => e($r['name']) . '：' . ($r['fee'] !== null ? e(format_price((int) $r['fee'])) . '（税込）' : 'お問い合わせください'), active_shipping());
    return $list ? implode('<br>', $list) : '';
}

function page_shipping(): void
{
    $rows = '';
    foreach (active_shipping() as $r) {
        $rows .= '<tr class="border-b border-line"><td class="px-3 py-3">' . e($r['name']) . '</td>'
            . '<td class="whitespace-nowrap px-3 py-3 text-right">' . ($r['fee'] !== null ? e(format_price((int) $r['fee'])) : '—') . '</td>'
            . '<td class="px-3 py-3 text-muted">' . e((string) $r['note']) . '</td></tr>';
    }
    $table = $rows
        ? '<div class="mt-10 overflow-x-auto"><table class="w-full min-w-[26rem] text-sm"><thead class="border-b border-line text-left text-xs text-muted"><tr>'
            . '<th class="px-3 py-2 font-medium">配送方法</th><th class="px-3 py-2 text-right font-medium">送料（税込）</th><th class="px-3 py-2 font-medium">お届けのしかた</th></tr></thead><tbody>'
            . $rows . '</tbody></table></div>'
        : '<p class="mt-10 text-center text-sm text-muted">' . e(LEGAL_TBD) . '</p>';
    $info = [
        '送料について' => '商品ごとに、上記のいずれかの配送方法でお届けします。どの配送方法になるかは、各商品ページに表示しています。',
        '発送の時期' => legal_value(SELLER_INFO['delivery']),
        'お届け先' => '日本国内のみとなります。',
        '配送状況の確認' => '発送後、お問い合わせ番号をお知らせします。配送業者のホームページで配送状況を確認できます。',
    ];
    $dl = '';
    foreach ($info as $k => $v) {
        $dl .= '<div class="grid gap-1 py-4 sm:grid-cols-[10rem_1fr] sm:gap-6"><dt class="font-medium text-leather">' . e($k) . '</dt><dd>' . ($k === '発送の時期' ? $v : e($v)) . '</dd></div>';
    }
    legal_page('/legal/shipping', '配送・送料', 'Shipping', $table . '<dl class="mt-10 divide-y divide-line border-y border-line text-sm leading-7">' . $dl . '</dl>');
}

function page_privacy(): void
{
    $si = SELLER_INFO;
    $link = static fn (string $href, string $label): string => '<a href="' . e($href) . '" target="_blank" rel="noopener noreferrer" class="text-leather underline underline-offset-4">' . e($label) . '</a>';
    $sections = [
        '1. 事業者' => e($si['company']) . '（' . e($si['manager']) . '）<br>' . e($si['postal'] . ' ' . $si['address']),
        '2. 取得する情報' => '当サイトでは、次の情報を取得することがあります。<br>'
            . '・ご注文時のお名前、ご住所、電話番号、メールアドレス<br>'
            . '・お問い合わせやご相談の内容<br>'
            . '・名入れ制作のためにお預かりする文字、ロゴなどのデータ<br>'
            . '・当サイトの閲覧に関する情報（Cookie、閲覧したページ、ご利用の端末やブラウザの種類など）',
        '3. 利用目的' => '取得した情報は、次の目的に利用します。<br>'
            . '・ご注文の確認、商品の制作・発送、代金の決済<br>'
            . '・ご注文やお問い合わせに関するご連絡<br>'
            . '・名入れ商品の校正・制作<br>'
            . '・当サイトの利用状況の分析と、サービスの改善<br>'
            . '・法令にもとづく対応',
        '4. 第三者への提供' => '法令にもとづく場合を除き、ご本人の同意なく個人情報を第三者に提供することはありません。<br>'
            . 'ただし、次の場合には、必要な範囲で業務を委託します。<br>'
            . '・商品の配送のため、配送業者にお名前・ご住所・電話番号を提供します。<br>'
            . '・クレジットカード決済のため、決済代行会社（株式会社ゼウス）に決済に必要な情報を提供します。',
        '5. クレジットカード情報' => 'クレジットカード番号などのカード情報は、決済代行会社が直接取り扱います。当サイトのサーバーにカード情報を保存することはありません。',
        '6. アクセス解析（Googleアナリティクス）' => '当サイトでは、利用状況を把握するために、Google LLC の「Googleアナリティクス」を使用することがあります。'
            . 'Googleアナリティクスは Cookie を使って情報を集めますが、個人を特定する情報は含まれません。<br>'
            . '集計を望まない場合は、ブラウザの設定で Cookie を無効にするか、' . $link('https://tools.google.com/dlpage/gaoptout?hl=ja', 'Googleアナリティクス オプトアウト アドオン') . 'をご利用ください。<br>'
            . '詳しくは ' . $link('https://policies.google.com/technologies/partner-sites?hl=ja', 'Googleのサービスを使用するサイトやアプリから収集した情報のGoogleによる使用') . ' をご覧ください。',
        '7. 写真の掲載' => 'ワークショップなどで撮影した写真は、ご本人（未成年の方は保護者の方）の了承をいただいた場合に限り、当サイトやInstagramに掲載します。掲載の取りやめをご希望の場合は、下記の窓口までご連絡ください。',
        '8. 安全管理' => '個人情報への不正なアクセス、紛失、漏えいなどを防ぐため、必要かつ適切な安全管理を行います。',
        '9. 開示・訂正・削除のご請求' => 'ご本人から、個人情報の開示・訂正・利用停止・削除のご請求があった場合は、ご本人であることを確認したうえで、すみやかに対応します。',
        '10. 改定' => '本ポリシーの内容は、必要に応じて変更することがあります。変更後の内容は、当ページに掲載した時点から効力を生じます。',
        '11. お問い合わせ窓口' => e($si['company']) . '<br>メール：' . e($si['email']) . '<br>電話：' . e($si['tel']) . '（' . e((string) $si['tel_hours']) . '）',
    ];
    $h = '<p class="mt-10 text-sm leading-7">' . e($si['company']) . '（以下「当社」）は、「' . e(SITE_NAME) . '」（以下「当サイト」）におけるお客様の個人情報を、次のとおり取り扱います。</p>';
    $h .= '<div class="mt-8 space-y-8 text-sm leading-7">';
    foreach ($sections as $k => $v) {
        $h .= '<section><h2 class="font-serif text-base text-leather">' . e($k) . '</h2><p class="mt-2">' . $v . '</p></section>';
    }
    $h .= '</div><p class="mt-10 text-right text-xs text-muted">2026年11月17日 制定</p>';
    legal_page('/legal/privacy', 'プライバシーポリシー', 'Privacy policy', $h);
}

function page_returns(): void
{
    $rows = [];
    foreach (returns_text() as $k => $v) {
        $rows[$k] = e($v);
    }
    $rows['ご注文後のキャンセル'] = legal_value(SELLER_INFO['cancel']);
    $rows['ご連絡先'] = e(SELLER_INFO['company']) . '<br>メール：' . e(SELLER_INFO['email']) . '<br>電話：' . e(SELLER_INFO['tel']);
    legal_page('/legal/returns', '返品・交換・キャンセル', 'Returns', legal_table($rows));
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
    $paths = ['/', '/shop', '/gallery', '/custom-cardcase', '/about', '/leather-aging', '/leather-care', '/workshop', '/order', '/contact', '/legal/tokushoho', '/legal/returns', '/legal/shipping', '/legal/privacy'];
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
