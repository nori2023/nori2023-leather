<?php
declare(strict_types=1);

/**
 * 購入手続き（1点もの）
 *  /checkout/{slug}          お届け先の入力 → 仮押さえして注文を作る
 *  /checkout/pay/{token}     カード入力（ゼウスのトークン）→ 3-Dセキュア
 *  /checkout/enroll/{token}  EnrolReq（JSから）
 *  /checkout/term/{token}    PaRes の受け取り（ゼウスのJSから）→ AuthReq → PayReq
 *  /checkout/done/{token}    完了
 *  /checkout/cancel/{token}  やめる（仮押さえを戻す）
 */

function start_checkout_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('nori_co');
    session_set_cookie_params(['lifetime' => 0, 'path' => (base_path() ?: '') . '/checkout', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

function route_checkout(string $path): void
{
    start_checkout_session();
    release_expired_reservations();
    $post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    match (true) {
        (bool) preg_match('#^/checkout/pay/([a-f0-9]{40})$#', $path, $m) => checkout_pay($m[1]),
        (bool) preg_match('#^/checkout/enroll/([a-f0-9]{40})$#', $path, $m) && $post => checkout_enroll($m[1]),
        (bool) preg_match('#^/checkout/term/([a-f0-9]{40})$#', $path, $m) && $post => checkout_term($m[1]),
        (bool) preg_match('#^/checkout/done/([a-f0-9]{40})$#', $path, $m) => checkout_done($m[1]),
        (bool) preg_match('#^/checkout/cancel/([a-f0-9]{40})$#', $path, $m) && $post => checkout_cancel($m[1]),
        (bool) preg_match('#^/checkout/([a-z0-9-]{1,80})$#', $path, $m) => $post ? checkout_order_save($m[1]) : checkout_form($m[1]),
        default => render_not_found(),
    };
}

function checkout_meta(string $title, string $path, array $extra = []): array
{
    return ['title' => $title, 'path' => $path, 'noindex' => true, 'nostore' => true, 'noAnalytics' => true] + $extra;
}

function checkout_product(string $slug): ?array
{
    $p = get_public_product($slug);
    return $p && $p['status'] === 'on_sale' ? $p : null;
}

function steps_bar(int $now): string
{
    $steps = ['お届け先', 'カード情報', '完了'];
    $h = '<ol class="mx-auto mt-6 flex max-w-md justify-between text-xs">';
    foreach ($steps as $i => $s) {
        $on = $i + 1 <= $now;
        $h .= '<li class="flex flex-1 flex-col items-center gap-1 ' . ($on ? 'text-leather' : 'text-muted') . '">'
            . '<span class="flex h-7 w-7 items-center justify-center rounded-full border ' . ($on ? 'border-leather bg-leather text-ivory' : 'border-line bg-white') . '">' . ($i + 1) . '</span>' . e($s) . '</li>';
    }
    return $h . '</ol>';
}

function order_box(array $p, int $fee, ?string $shipName): string
{
    $img = $p['images'][0] ?? null;
    return '<div class="flex gap-4 rounded-card border border-line bg-white p-4">'
        . '<div class="relative h-24 w-20 shrink-0 overflow-hidden rounded bg-sand">' . ($img ? product_img($img, '80px', true, 'absolute inset-0 h-full w-full object-cover') : '') . '</div>'
        . '<dl class="min-w-0 flex-1 text-sm leading-6"><dt class="sr-only">作品</dt><dd class="font-medium">' . e($p['name']) . '</dd>'
        . '<dd class="text-xs text-muted">品番 ' . e($p['code']) . '</dd>'
        . '<dd class="mt-1 flex justify-between"><span>作品代金</span><span>' . e(format_price((int) $p['price'])) . '</span></dd>'
        . '<dd class="flex justify-between"><span>送料' . ($shipName ? '（' . e($shipName) . '）' : '') . '</span><span>' . e(format_price($fee)) . '</span></dd>'
        . '<dd class="mt-1 flex justify-between border-t border-line pt-1 font-medium text-leather"><span>お支払い合計（税込）</span><span>' . e(format_price((int) $p['price'] + $fee)) . '</span></dd></dl></div>';
}

const CONSENT_TEXT = "当社がお客様から収集した以下の個人情報等は、カード発行会社が行う不正利用検知・防止のために、お客様が利用されているカード発行会社へ提供させていただきます。\n"
    . "氏名、電話番号、emailアドレス、インターネット利用環境に関する情報等。お客様が利用されているカード発行会社が外国にある場合、これらの情報は当該発行会社が所属する国に移転される場合があります。\n"
    . "当社では、お客様から収集した情報からは、ご利用のカード発行会社及び当該会社が所在する国を特定することができないため、提供先が所在する外国の名称、当該国の個人情報保護制度に関する情報、発行会社の個人情報保護の措置に関する情報を把握してご提供することはできません。\n"
    . "なお、個人情報保護委員会のホームページ（https://www.ppc.go.jp/）では、各国における個人情報保護制度に関する情報について掲載されています。\n"
    . "お客様が未成年の場合、親権者または後見人の承諾を得た上で、本サービスを利用するものとします。";

// ------------------------------------------------------------------ 1. お届け先
function checkout_form(string $slug, array $v = [], ?string $error = null): void
{
    if (!checkout_open()) {
        redirect('/shop/' . $slug);
    }
    $p = checkout_product($slug);
    if (!$p) {
        redirect('/shop/' . $slug);
    }
    $fee = $p['shipping_fee'] !== null ? (int) $p['shipping_fee'] : 0;
    $val = static fn (string $k): string => e((string) ($v[$k] ?? ''));
    $field = static function (string $label, string $control, string $id, string $hint = '', bool $req = true): string {
        return '<div><label for="' . $id . '" class="block text-sm font-medium">' . e($label) . ($req ? ' <span class="text-xs text-camel">必須</span>' : '') . '</label>'
            . $control . ($hint ? '<p class="mt-1 text-xs text-muted">' . e($hint) . '</p>' : '') . '</div>';
    };
    $in = 'mt-1 block w-full min-h-12 rounded border border-line bg-white px-3 text-base focus:border-camel focus:outline-none';
    $prefs = '<option value="">選んでください</option>';
    foreach (PREFECTURES as $i => $pr) {
        $prefs .= '<option value="' . $i . '"' . ((string) ($v['pref'] ?? '33') === (string) $i ? ' selected' : '') . '>' . e($pr) . '</option>';
    }
    $body = '<div class="mx-auto max-w-xl px-5 pb-24 pt-8"><h1 class="text-center text-[1.4rem]">購入手続き</h1>' . steps_bar(1)
        . ($error ? '<p class="mt-6 rounded border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">' . e($error) . '</p>' : '')
        . '<div class="mt-6">' . order_box($p, $fee, $p['shipping_name']) . '</div>'
        . '<form method="post" class="mt-8 space-y-5" novalidate>' . csrf_field()
        . '<h2 class="font-serif text-lg text-leather">お届け先</h2>'
        . $field('お名前', '<input id="name" name="name" required maxlength="50" autocomplete="name" value="' . $val('name') . '" class="' . $in . '">', 'name')
        . $field('メールアドレス', '<input id="email" name="email" type="email" required maxlength="50" autocomplete="email" value="' . $val('email') . '" class="' . $in . '">', 'email', 'ご注文の確認メールをお送りします')
        . $field('電話番号', '<input id="phone" name="phone" type="tel" required maxlength="15" autocomplete="tel" inputmode="tel" value="' . $val('phone') . '" class="' . $in . '">', 'phone', '例：09012345678（ハイフンなしでもOK）')
        . $field('郵便番号', '<input id="postal" name="postal" required maxlength="8" autocomplete="postal-code" inputmode="numeric" value="' . $val('postal') . '" class="' . $in . ' max-w-[10rem]">', 'postal', '例：7310135')
        . $field('都道府県', '<select id="pref" name="pref" required autocomplete="address-level1" class="' . $in . '">' . $prefs . '</select>', 'pref')
        . $field('市区町村', '<input id="city" name="city" required maxlength="40" autocomplete="address-level2" value="' . $val('city') . '" class="' . $in . '">', 'city', '例：広島市安佐南区')
        . $field('番地', '<input id="line1" name="line1" required maxlength="40" autocomplete="address-line1" value="' . $val('line1') . '" class="' . $in . '">', 'line1', '例：長束1-1-14')
        . $field('建物名・部屋番号', '<input id="line2" name="line2" maxlength="40" autocomplete="address-line2" value="' . $val('line2') . '" class="' . $in . '">', 'line2', '', false)
        . $field('ご要望・連絡事項', '<textarea id="note" name="note" rows="3" maxlength="500" class="' . $in . ' py-2">' . $val('note') . '</textarea>', 'note', '贈り物の場合など、あればお書きください', false)
        . '<section class="rounded-card border border-line bg-paper p-4"><h2 class="text-sm font-medium text-leather">個人情報の第三者提供について</h2>'
        . '<div class="mt-2 max-h-40 overflow-y-auto whitespace-pre-line rounded bg-white p-3 text-xs leading-6" tabindex="0">' . e(CONSENT_TEXT) . '</div>'
        . '<p class="mt-2 text-xs leading-5">あわせて <a href="' . e(url('/legal/privacy')) . '" target="_blank" class="text-leather underline">プライバシーポリシー</a>・'
        . '<a href="' . e(url('/legal/tokushoho')) . '" target="_blank" class="text-leather underline">特定商取引法に基づく表記</a>・'
        . '<a href="' . e(url('/legal/returns')) . '" target="_blank" class="text-leather underline">返品・交換</a> をご確認ください。</p>'
        . '<label class="mt-3 flex min-h-11 items-start gap-2 text-sm font-medium"><input type="checkbox" name="consent" value="1" required class="mt-1 h-5 w-5"' . (!empty($v['consent']) ? ' checked' : '') . '> 上記の内容に同意します</label></section>'
        . '<p class="text-xs leading-5 text-muted">次へ進むと、この作品を ' . RESERVE_MINUTES . '分間あなたのために取り置きます（そのあいだ、ほかの人は買えません）。</p>'
        . '<button class="flex min-h-14 w-full items-center justify-center rounded-[2px] bg-leather text-[1rem] tracking-[0.12em] text-ivory hover:bg-charcoal">カード情報の入力へ進む</button>'
        . '<p class="text-center"><a href="' . e(url('/shop/' . $p['slug'])) . '" class="text-sm text-muted underline">作品ページへ戻る</a></p></form></div>';
    render_page(checkout_meta('購入手続き', '/checkout/' . $p['slug']), $body);
}

function checkout_order_save(string $slug): void
{
    verify_csrf();
    if (!checkout_open()) {
        redirect('/shop/' . $slug);
    }
    $p = checkout_product($slug);
    if (!$p) {
        render_page(checkout_meta('購入手続き', '/checkout/' . $slug), '<div class="mx-auto max-w-md px-5 py-24 text-center"><p class="text-lg">ごめんなさい。この作品は、ちょうどほかの方が購入手続きに入ったか、売れてしまいました。</p>'
            . '<p class="mt-6"><a href="' . e(url('/shop')) . '" class="text-leather underline">ほかの作品を見る</a></p></div>', 409);
        return;
    }
    $v = [
        'name' => post_str('name', 50), 'email' => mb_strtolower(post_str('email', 50)), 'phone' => post_str('phone', 15),
        'postal' => post_str('postal', 8), 'pref' => post_str('pref', 2), 'city' => post_str('city', 40), 'line1' => post_str('line1', 40),
        'line2' => post_str('line2', 40), 'note' => post_str('note', 500), 'consent' => ($_POST['consent'] ?? '') === '1',
    ];
    $phone = preg_replace('/[^\d]/', '', mb_convert_kana($v['phone'], 'n'));
    $postal = preg_replace('/[^\d]/', '', mb_convert_kana($v['postal'], 'n'));
    $error = match (true) {
        $v['name'] === '' => 'お名前を入力してください。',
        !filter_var($v['email'], FILTER_VALIDATE_EMAIL) => 'メールアドレスを正しく入力してください。',
        !preg_match('/^0\d{8,10}$/', $phone) => '電話番号を正しく入力してください（0から始まる10〜11桁）。',
        !preg_match('/^\d{7}$/', $postal) => '郵便番号を7桁の数字で入力してください。',
        !preg_match('/^\d{1,2}$/', $v['pref']) || !isset(PREFECTURES[(int) $v['pref']]) => '都道府県を選んでください。',
        $v['city'] === '' || $v['line1'] === '' => '市区町村と番地を入力してください。',
        !$v['consent'] => '個人情報の第三者提供への同意をお願いします。',
        default => null,
    };
    if ($error) {
        checkout_form($slug, $v, $error);
        return;
    }
    $order = create_product_order($p, [
        'name' => $v['name'], 'email' => $v['email'], 'phone' => $phone, 'postal' => substr($postal, 0, 3) . '-' . substr($postal, 3),
        'pref_index' => (int) $v['pref'], 'city' => $v['city'], 'line1' => $v['line1'], 'line2' => $v['line2'], 'note' => $v['note'],
    ]);
    if (!$order) {
        checkout_form($slug, $v, 'ごめんなさい。この作品は、ちょうどほかの方が購入手続きに入ったか、売れてしまいました。');
        return;
    }
    redirect('/checkout/pay/' . $order['access_token']);
}

// ------------------------------------------------------------------ 2. カード情報（ゼウス）
function open_order(string $token): ?array
{
    $o = order_by_token($token);
    if (!$o) {
        return null;
    }
    if ($o['status'] === 'awaiting_payment' && $o['pay_expires_at'] && strtotime($o['pay_expires_at']) < time()) {
        release_expired_reservations();
        $o = order_by_token($token);
    }
    return $o;
}

function checkout_pay(string $token): void
{
    $o = open_order($token);
    if (!$o) {
        render_not_found();
        return;
    }
    if ($o['status'] === 'paid' || $o['status'] === 'shipped') {
        redirect('/checkout/done/' . $token);
    }
    $snap = json_decode((string) $o['snapshot'], true) ?: [];
    if ($o['status'] !== 'awaiting_payment' || !checkout_open()) {
        render_page(checkout_meta('購入手続き', '/checkout'), '<div class="mx-auto max-w-md px-5 py-24 text-center"><p class="text-lg leading-8">手続きの時間（' . RESERVE_MINUTES . '分）が過ぎたため、取り置きを終了しました。</p>'
            . '<p class="mt-6"><a href="' . e(url('/shop/' . ($snap['product']['slug'] ?? ''))) . '" class="text-leather underline">作品ページへ戻る</a></p></div>', 410);
        return;
    }
    $c = zeus_cfg();
    $ep = zeus_ep();
    $left = max(0, strtotime($o['pay_expires_at']) - time());
    $body = '<div class="mx-auto max-w-xl px-5 pb-24 pt-8"><h1 class="text-center text-[1.4rem]">購入手続き</h1>' . steps_bar(2)
        . ($c['endpoint'] === 'sandbox' || $c['mode'] === 'tester' ? '<p class="mt-6 rounded border border-amber-300 bg-amber-50 px-4 py-2 text-xs text-amber-900">テストモードです（' . ($c['endpoint'] === 'sandbox' ? 'サンドボックス' : '本番環境・テストIPコード') . '）。テスト用のカード番号を使ってください。</p>' : '')
        . '<div class="mt-6 rounded-card border border-line bg-white p-4 text-sm leading-7">'
        . '<p class="font-medium">' . e($snap['product']['name'] ?? '') . '<span class="ml-2 text-xs text-muted">品番 ' . e($snap['product']['code'] ?? '') . '</span></p>'
        . '<p class="flex justify-between border-t border-line pt-1 font-medium text-leather"><span>お支払い合計（税込）</span><span>' . e(format_price((int) $o['total'])) . '</span></p>'
        . '<p class="text-xs text-muted">お届け先：' . e($o['customer_name']) . ' 様　〒' . e($o['postal_code']) . ' ' . e($o['address']) . '</p>'
        . '<p class="text-xs text-muted">取り置きの残り時間：<span data-countdown="' . $left . '">' . (int) ceil($left / 60) . '分</span></p></div>'
        . '<form name="zeusform" id="zeusform" class="mt-8" data-checkout data-enroll="' . e(url('/checkout/enroll/' . $token)) . '" data-term="' . e(absolute_url('/checkout/term/' . $token)) . '" data-done="' . e(url('/checkout/done/' . $token)) . '">'
        . '<h2 class="font-serif text-lg text-leather">クレジットカード</h2>'
        . '<p class="mt-1 text-xs leading-5 text-muted">VISA・Mastercard・JCB・American Express・Diners Club がご利用いただけます（一括払いのみ）。カード番号は当サイトのサーバーを通らず、決済会社（株式会社ゼウス）へ直接送られます。</p>'
        . '<div id="zeus_token_card_info_area" class="zeus-area mt-4"></div>'
        . '<p data-pay-status class="mt-4 text-sm text-red-700" role="alert" aria-live="assertive"></p>'
        . '<button type="button" id="btn_go_to_confirm_page" class="mt-4 flex min-h-14 w-full items-center justify-center rounded-[2px] bg-leather text-[1rem] tracking-[0.12em] text-ivory hover:bg-charcoal disabled:opacity-50">'
        . e(format_price((int) $o['total'])) . ' を支払う</button></form>'
        . '<div id="challenge_wait" hidden class="mt-6 rounded border border-line bg-paper px-4 py-3 text-center text-sm">カード会社の本人認証を準備しています。しばらくお待ちください…</div>'
        . '<div id="3dscontainer" class="mt-4"></div>'
        . '<form method="post" action="' . e(url('/checkout/cancel/' . $token)) . '" class="mt-10 text-center">' . csrf_field()
        . '<button class="min-h-11 text-sm text-muted underline">購入をやめる（取り置きを解除）</button></form></div>';
    $origin = $ep['origin'];
    render_page(checkout_meta('お支払い', '/checkout', [
        // 決済ページだけ、ゼウスの読み込み・通信と、カード会社の本人認証画面（iframe）を許可する
        'csp' => ['script' => [$origin], 'style' => [$origin], 'connect' => [$origin], 'img' => [$origin], 'frame' => ['https:'], 'form' => ['https:'], 'style_inline' => true],
        'headStyles' => [$ep['css']],
        'headScripts' => [
            ['src' => asset('assets/checkout.js'), 'data' => ['ipcode' => $c['clientip'], 'csrf' => csrf_token()]],
            ['src' => $ep['js']],
        ],
    ]), $body);
}

function json_input(): array
{
    $raw = file_get_contents('php://input', false, null, 0, 20000) ?: '';
    $d = json_decode($raw, true);
    return is_array($d) ? $d : $_POST;
}

/** 同じサイトから送られてきたか（Origin が分かるときだけ確かめる） */
function same_origin(): bool
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === '' || $origin === 'null') {
        return true;
    }
    $self = parse_url(absolute_url('/'));
    $o = parse_url($origin);
    return ($o['host'] ?? '') === ($self['host'] ?? '') && ($o['scheme'] ?? '') === ($self['scheme'] ?? '');
}

// ------------------------------------------------------------------ EnrolReq（JSから）
function checkout_enroll(string $token): void
{
    verify_csrf();
    $o = open_order($token);
    if (!$o || $o['status'] !== 'awaiting_payment' || !checkout_open()) {
        json_response(['ok' => false, 'message' => '手続きの時間が過ぎたか、すでに終わっています。ページを開き直してください。'], 409);
    }
    $d = json_input();
    $tokenKey = (string) ($d['token_key'] ?? '');
    if (!preg_match('/^[\x21-\x7E]{10,120}$/', $tokenKey)) {
        json_response(['ok' => false, 'message' => 'カード情報を読み取れませんでした。もう一度入力してください。'], 422);
    }
    try {
        $r = zeus_enroll($o, $tokenKey, (string) ($d['card_name'] ?? ''));
    } catch (Throwable $e) {
        error_log('[nori2023 enroll] ' . $e->getMessage());
        json_response(['ok' => false, 'message' => zeus_error_message('')], 502);
    }
    if ($r['xid'] !== '') {
        db_exec('UPDATE orders SET zeus_xid = ?, updated_at = NOW() WHERE id = ?', [$r['xid'], $o['id']]);
    }
    if ($r['status'] === 'success' && $r['xid'] !== '') {
        json_response(['ok' => true, 'next' => '3ds', 'md' => $r['xid'], 'iframeUrl' => $r['iframeUrl'],
            'termUrl' => absolute_url('/checkout/term/' . $token)]);
    }
    if ($r['status'] === 'outside' && $r['xid'] !== '') {
        // 3-Dセキュア未対応のカード：ゼウスの設定で認めている場合だけ、そのままオーソリ
        $res = finish_payment(order_by_token($token), $r['xid']);
        json_response($res, $res['ok'] ? 200 : 402);
    }
    json_response(['ok' => false, 'message' => zeus_error_message($r['status'])], 402);
}

/** PayReq まで進めて結果を返す */
function finish_payment(array $o, string $xid): array
{
    $pay = zeus_pay($xid, $o['id']);
    if ($pay['ok']) {
        mark_order_paid($o, $pay);
        return ['ok' => true, 'redirect' => url('/checkout/done/' . $o['access_token'])];
    }
    return ['ok' => false, 'message' => zeus_error_message($pay['status'])];
}

// ------------------------------------------------------------------ PaRes の受け取り（ゼウスのJSから非同期で届く）
function checkout_term(string $token): void
{
    if (!same_origin()) {
        json_response(['ok' => false, 'message' => '不正な送信です。'], 403);
    }
    $o = open_order($token);
    if (!$o) {
        json_response(['ok' => false, 'message' => '注文が見つかりません。'], 404);
    }
    if ($o['status'] === 'paid') {
        json_response(['ok' => true, 'redirect' => url('/checkout/done/' . $token)]);
    }
    if ($o['status'] !== 'awaiting_payment') {
        json_response(['ok' => false, 'message' => '手続きの時間が過ぎました。最初からお試しください。'], 409);
    }
    $d = json_input();
    $md = (string) ($d['MD'] ?? $d['md'] ?? '');
    $pares = (string) ($d['PaRes'] ?? $d['pares'] ?? '');
    $status = (string) ($d['status'] ?? '');
    zeus_log('pares', $o['id'], ['http' => 200, 'status' => $status, 'code' => $pares, 'error' => ''], null);
    if ($md === '' || !hash_equals((string) $o['zeus_xid'], $md)) {
        json_response(['ok' => false, 'message' => '決済の情報が一致しませんでした。もう一度お試しください。'], 400);
    }
    if ($status !== 'success') {
        json_response(['ok' => false, 'message' => zeus_error_message($status)], 402);
    }
    $auth = zeus_auth($md, $pares);
    if (!$auth['ok']) {
        json_response(['ok' => false, 'message' => zeus_error_message($auth['status'])], 402);
    }
    $res = finish_payment($o, $md);
    json_response($res, $res['ok'] ? 200 : 402);
}

// ------------------------------------------------------------------ 3. 完了／やめる
function checkout_done(string $token): void
{
    $o = order_by_token($token);
    if (!$o || !in_array($o['status'], ['paid', 'shipped', 'completed'], true)) {
        redirect('/checkout/pay/' . $token);
    }
    $snap = json_decode((string) $o['snapshot'], true) ?: [];
    $body = '<div class="mx-auto max-w-xl px-5 pb-24 pt-8 text-center"><h1 class="text-[1.4rem]">ありがとうございました！</h1>' . steps_bar(3)
        . '<p class="mt-8 leading-8">お支払いが完了しました。<br>' . e($o['customer_name']) . ' 様、買うてくれんさって、ほんまにありがとうございます。</p>'
        . '<div class="mx-auto mt-8 max-w-sm rounded-card border border-line bg-white p-5 text-left text-sm leading-7">'
        . '<p>ご注文番号：<strong class="font-mono">' . e($o['order_number']) . '</strong></p>'
        . '<p>作品：' . e($snap['product']['name'] ?? '') . '（品番 ' . e($snap['product']['code'] ?? '') . '）</p>'
        . '<p>お支払い合計：' . e(format_price((int) $o['total'])) . '（税込）</p></div>'
        . '<p class="mt-6 text-sm leading-7 text-muted">ご注文の確認メールを <strong>' . e($o['email']) . '</strong> にお送りしました。<br>'
        . e((string) SELLER_INFO['delivery']) . '<br>メールが届かないときは、迷惑メールのフォルダもご確認ください。</p>'
        . '<p class="mt-10">' . button_link('/', 'トップへ戻る', 'outline') . '</p></div>';
    render_page(checkout_meta('ご注文完了', '/checkout'), $body);
}

function checkout_cancel(string $token): void
{
    verify_csrf();
    $o = order_by_token($token);
    $snap = $o ? (json_decode((string) $o['snapshot'], true) ?: []) : [];
    if ($o) {
        cancel_unpaid_order($o, 'お客様が購入をやめた');
    }
    redirect('/shop/' . ($snap['product']['slug'] ?? ''));
}
