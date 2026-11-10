<?php
declare(strict_types=1);

/** 公開ページ：ワークショップ・革の経年変化（広島弁） */

const WEEKDAYS_JA = ['日', '月', '火', '水', '木', '金', '土'];

function ws_date(string $date): string
{
    $t = strtotime($date);
    return $t ? date('Y年n月j日', $t) . '（' . WEEKDAYS_JA[(int) date('w', $t)] . '）' : '';
}

function ws_time(array $w): string
{
    $s = $w['start_time'] ? substr($w['start_time'], 0, 5) : '';
    $t = $w['end_time'] ? substr($w['end_time'], 0, 5) : '';
    return $s && $t ? "{$s}〜{$t}" : ($s ?: '');
}

function ws_img(?string $key, string $alt, string $cls): string
{
    return $key
        ? '<img src="' . e(upload_url($key)) . '" alt="' . e($alt) . '" loading="lazy" class="' . $cls . ' object-cover">'
        : '<div class="' . $cls . '">' . placeholder('写真準備中') . '</div>';
}

// ------------------------------------------------------------------ ワークショップ
function page_workshop(): void
{
    $today = date('Y-m-d');
    $upcoming = db_all('SELECT * FROM workshop_events WHERE is_published = 1 AND event_date >= ? ORDER BY event_date, start_time', [$today]);
    $past = db_all('SELECT * FROM workshop_events WHERE is_published = 1 AND event_date < ? ORDER BY event_date DESC LIMIT 12', [$today]);

    $cards = '';
    $ld = [];
    foreach ($upcoming as $w) {
        $rows = [
            '日にち' => e(ws_date($w['event_date'])),
            '時間' => e(ws_time($w)) ?: '<span class="text-muted">決まったらお知らせします</span>',
            '場所' => e($w['place']),
            '作るもの' => e($w['item_name']),
            '参加費' => ($w['fee'] !== null ? e(format_price((int) $w['fee'])) . '（税込）' : '<span class="text-muted">決まったらお知らせします</span>')
                . (!empty($w['fee_note']) ? '<br><span class="text-camel">' . e($w['fee_note']) . '</span>' : ''),
        ];
        if (!empty($w['capacity'])) {
            $rows['定員'] = e((string) $w['capacity']) . '名';
        }
        $dl = '';
        foreach ($rows as $k => $v) {
            $dl .= '<div class="grid grid-cols-[5.5rem_1fr] gap-3 py-2.5"><dt class="text-muted">' . e($k) . '</dt><dd>' . $v . '</dd></div>';
        }
        if (!$w['accepting']) {
            $cta = '<p class="mt-6 rounded border border-line bg-paper px-4 py-3 text-center text-sm">予約の受付は、締め切りました。</p>';
        } elseif (!empty($w['reserve_url'])) {
            $cta = '<a href="' . e($w['reserve_url']) . '" target="_blank" rel="noopener" class="mt-6 flex min-h-14 w-full items-center justify-center rounded-[2px] bg-leather text-[1rem] tracking-[0.12em] text-ivory hover:bg-charcoal">予約はこちらから ↗</a>'
                . '<p class="mt-2 text-center text-xs text-muted">予約ページ（別のサイト）が開きます</p>';
        } else {
            $cta = '<p class="mt-6 text-center text-sm text-muted">予約なしでも、当日ふらっと来てもろうてええですよ。</p>';
        }
        $cards .= '<article class="overflow-hidden rounded-card border border-line bg-white md:grid md:grid-cols-[2fr_3fr]">'
            . '<div class="relative aspect-[4/3] md:aspect-auto">' . ws_img($w['image_key'], $w['item_name'], 'absolute inset-0 h-full w-full') . '</div>'
            . '<div class="p-6 sm:p-8"><p class="eyebrow">' . e(ws_date($w['event_date'])) . '</p>'
            . '<h2 class="mt-2 text-[1.2rem] leading-relaxed sm:text-[1.35rem]">' . e($w['title']) . '</h2>'
            . '<dl class="mt-5 divide-y divide-line border-y border-line text-sm">' . $dl . '</dl>'
            . '<div class="mt-6 whitespace-pre-line text-[0.95rem] leading-8">' . e($w['description']) . '</div>' . $cta . '</div></article>';
        $start = $w['event_date'] . ($w['start_time'] ? 'T' . substr($w['start_time'], 0, 5) . ':00+09:00' : '');
        $ld[] = array_filter([
            '@context' => 'https://schema.org', '@type' => 'Event',
            'name' => $w['title'], 'startDate' => $start,
            'endDate' => $w['end_time'] ? $w['event_date'] . 'T' . substr($w['end_time'], 0, 5) . ':00+09:00' : null,
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'location' => ['@type' => 'Place', 'name' => $w['place']],
            'description' => mb_substr(preg_replace('/\s+/u', ' ', $w['description']) ?? '', 0, 200),
            'image' => $w['image_key'] ? [absolute_url('/' . UPLOAD_DIR_NAME . '/' . $w['image_key'])] : null,
            'organizer' => ['@type' => 'Organization', 'name' => SITE_NAME],
            'offers' => $w['fee'] !== null ? ['@type' => 'Offer', 'price' => (int) $w['fee'], 'priceCurrency' => 'JPY', 'url' => $w['reserve_url'] ?: absolute_url('/workshop')] : null,
        ], static fn ($v) => $v !== null);
    }
    if (!$upcoming) {
        $cards = empty_state('いまは、決まっとるワークショップはありません', '次の開催が決まったら、ここと Instagram（@nori2023_）でお知らせしますけぇね。');
    }

    $pastHtml = '';
    if ($past) {
        $pastHtml = '<section class="mx-auto mt-20 max-w-4xl px-5">' . section_heading('これまでのワークショップ', 'Past events')
            . '<ul class="divide-y divide-line border-y border-line text-sm">';
        foreach ($past as $w) {
            $pastHtml .= '<li class="flex flex-wrap gap-x-4 gap-y-1 py-3"><span class="text-muted">' . e(ws_date($w['event_date'])) . '</span>'
                . '<span>' . e($w['item_name']) . '</span><span class="text-muted">' . e($w['place']) . '</span></li>';
        }
        $pastHtml .= '</ul></section>';
    }

    $body = breadcrumbs([['/workshop', 'ワークショップ']])
        . '<header class="mx-auto max-w-3xl px-5 pb-10 pt-10 text-center"><p class="eyebrow">Workshop</p>'
        . '<h1 class="mt-3 text-[1.55rem] sm:text-[2rem]">革にさわって、<br class="sm:hidden">作ってみんさい。</h1>'
        . '<div class="stitch mx-auto mt-6 w-16" aria-hidden="true"></div>'
        . '<p class="mt-8 text-[0.95rem] leading-8 text-muted">はじめての人でも、子どもさんでも大丈夫。<br>おっさんが横について、いっしょに作りますけぇ。</p></header>'
        . '<div class="mx-auto max-w-5xl space-y-8 px-5">' . $cards . '</div>' . $pastHtml
        . '<p class="mx-auto mt-16 max-w-xl px-5 pb-24 text-center text-sm text-muted">開催のお知らせは Instagram（<a href="https://www.instagram.com/nori2023_/" target="_blank" rel="noopener" class="text-leather underline underline-offset-4">@nori2023_</a>）でもしとります。</p>'
        . implode('', array_map('json_ld', $ld));
    $first = $upcoming[0] ?? null;
    render_page([
        'title' => 'ワークショップ',
        'path' => '/workshop',
        'description' => $first
            ? ws_date($first['event_date']) . '、' . $first['place'] . 'でレザークラフトのワークショップをします。' . $first['item_name'] . 'を作ってみんさい。'
            : 'レザークラフトのワークショップのお知らせです。はじめての人でも、子どもさんでも大丈夫じゃけぇ。',
        'image' => $first && $first['image_key'] ? '/' . UPLOAD_DIR_NAME . '/' . $first['image_key'] : null,
    ], $body);
}

// ------------------------------------------------------------------ 革の経年変化
function page_leather_aging(): void
{
    $photos = db_all('SELECT * FROM leather_aging_photos WHERE is_published = 1 AND is_real_record = 1 ORDER BY sort_order, created_at');

    $points = [
        ['色', '生成りのうすいベージュから、だんだん飴色・こげ茶へ。日の光や手の脂で、ゆっくり色が深うなっていきます。'],
        ['ツヤ', '毎日さわっとるうちに表面がなめらかになって、しっとりしたツヤが出てきます。'],
        ['手なじみ', '使い始めはちいと硬めでも、だんだんやわらこうなって、持つ人の手や使い方になじんでいきます。'],
    ];
    $pointHtml = '';
    foreach ($points as $i => [$t, $d]) {
        $pointHtml .= '<li class="rounded-card border border-line bg-white p-6"><p class="font-serif text-2xl text-camel">0' . ($i + 1) . '</p>'
            . '<h3 class="mt-2 font-serif text-lg text-leather">' . e($t) . '</h3><p class="mt-3 text-sm leading-7">' . e($d) . '</p></li>';
    }

    if ($photos) {
        $gallery = '<ul class="grid gap-8 md:grid-cols-2">';
        foreach ($photos as $p) {
            $gallery .= '<li class="rounded-card border border-line bg-white p-4 sm:p-5"><div class="grid grid-cols-2 gap-2">'
                . '<figure><div class="relative aspect-square overflow-hidden rounded">' . ws_img($p['before_key'], $p['title'] . '（使い始め）', 'absolute inset-0 h-full w-full') . '</div>'
                . '<figcaption class="mt-2 text-center text-xs text-muted">使い始め</figcaption></figure>'
                . '<figure><div class="relative aspect-square overflow-hidden rounded">' . ws_img($p['after_key'], $p['title'] . '（' . ($p['period_label'] ?: '使い込んだあと') . '）', 'absolute inset-0 h-full w-full') . '</div>'
                . '<figcaption class="mt-2 text-center text-xs text-muted">' . e($p['period_label'] ?: '使い込んだあと') . '</figcaption></figure></div>'
                . '<h3 class="mt-4 font-serif text-leather">' . e($p['title']) . '</h3>'
                . ($p['description'] ? '<p class="mt-2 whitespace-pre-line text-sm leading-7">' . e($p['description']) . '</p>' : '') . '</li>';
        }
        $gallery .= '</ul>';
    } else {
        $gallery = empty_state('比較写真は、いま撮りためよります', 'おっさんが実際に使い込んだ作品の写真がそろうたら、ここで見てもらえるようにしますけぇね。');
    }

    $body = breadcrumbs([['/leather-aging', '革の経年変化']]) . '<article class="pb-24">'
        . '<header class="mx-auto max-w-3xl px-5 pb-12 pt-10 text-center"><p class="eyebrow">Leather aging</p>'
        . '<h1 class="mt-3 text-[1.55rem] sm:text-[2rem]">使やぁ使うほど、<br class="sm:hidden">ええ色になるんよ。</h1>'
        . '<div class="stitch mx-auto mt-6 w-16" aria-hidden="true"></div>'
        . '<p class="mt-8 text-[0.95rem] leading-8">おっさんがよう使うヌメ革は、植物の渋（タンニン）でなめした革です。<br class="hidden sm:inline">'
        . '日の光や手の脂、空気にふれるうちに、色が深うなってツヤが出てきます。<br class="hidden sm:inline">これを「経年変化（エイジング）」いいます。</p></header>'
        . '<section class="mx-auto max-w-5xl px-5">' . section_heading('どがぁに変わるん？', 'How it changes')
        . '<ul class="grid gap-4 md:grid-cols-3">' . $pointHtml . '</ul></section>'
        . '<section class="mx-auto mt-20 max-w-5xl px-5">' . section_heading('使い始めと、使い込んだあと', 'Before / After', '写真は、実際に使うて撮ったものだけを載せとります。')
        . $gallery . '</section>'
        . '<section class="mx-auto mt-20 max-w-3xl px-5">' . section_heading('ええ色に育てるコツ', 'Tips')
        . '<ul class="space-y-4 text-[0.95rem] leading-8">'
        . '<li class="rounded-card border border-line bg-paper px-5 py-4"><span class="font-medium text-leather">毎日さわってやる</span>　手でさわるんが、いちばんのお手入れじゃけぇ。しまいこむより、どんどん使うてやってつかぁさい。</li>'
        . '<li class="rounded-card border border-line bg-paper px-5 py-4"><span class="font-medium text-leather">使い始めは水に気をつける</span>　ヌメ革は水がつくとシミになりやすいです。ぬれたら、かわいた布でやさしく押さえて、日かげで乾かしてください。</li>'
        . '<li class="rounded-card border border-line bg-paper px-5 py-4"><span class="font-medium text-leather">変わり方は1つずつ違う</span>　使い方や置き場所で、色の出方はみんな違います。それが自分だけの味になるんよね。</li>'
        . '</ul><p class="mt-8 text-center text-sm"><a href="' . e(url('/leather-care')) . '" class="text-leather underline underline-offset-4">革のお手入れのしかた</a></p></section>'
        . '<div class="mt-16 text-center">' . button_link('/shop', '販売中の作品を見る') . '</div></article>';
    render_page([
        'title' => '革の経年変化',
        'path' => '/leather-aging',
        'description' => 'ヌメ革は使やぁ使うほど色が深うなって、ツヤが出てきます。おっさんの革小物の経年変化（エイジング）を紹介しとります。',
    ], $body);
}
