<?php
declare(strict_types=1);

/** 管理画面：注文・決済設定 */

function order_badge(string $status): string
{
    $color = ['awaiting_payment' => 'bg-amber-100 text-amber-900', 'paid' => 'bg-red-100 text-red-800', 'shipped' => 'bg-green-100 text-green-800',
        'completed' => 'bg-gray-100 text-gray-700', 'cancelled' => 'bg-gray-100 text-gray-500'][$status] ?? 'bg-gray-100';
    return '<span class="shrink-0 rounded px-2 py-1 text-xs ' . $color . '">' . e(ORDER_STATUS_LABEL[$status] ?? $status) . '</span>';
}

// ================================================================== 注文
function admin_orders(): void
{
    release_expired_reservations();
    $filter = query_str('status', 20);
    $where = match ($filter) {
        'todo' => "WHERE o.status = 'paid'",
        'all' => '',
        default => "WHERE o.status <> 'cancelled'",
    };
    $rows = db_all("SELECT o.* FROM orders o {$where} ORDER BY o.created_at DESC LIMIT 200");
    $tab = static fn (string $label, string $key, string $cur) => '<a href="' . e(url('/admin/orders' . ($key ? '?status=' . $key : ''))) . '" class="inline-flex min-h-10 items-center rounded-full border px-4 text-sm '
        . ($cur === $key ? 'border-leather bg-leather text-ivory' : 'border-line bg-white') . '">' . e($label) . '</a>';
    $h = '<div class="mb-4 flex flex-wrap gap-2">' . $tab('進行中', '', $filter) . $tab('発送待ちだけ', 'todo', $filter) . $tab('キャンセルも含めて全部', 'all', $filter) . '</div>';
    if (!$rows) {
        $h .= '<p class="' . A_CARD . ' text-sm text-muted">注文はまだありません。</p>';
    } else {
        $h .= '<ul class="space-y-2">';
        foreach ($rows as $o) {
            $snap = json_decode((string) $o['snapshot'], true) ?: [];
            $h .= '<li><a href="' . e(url('/admin/orders/' . $o['id'])) . '" class="' . A_CARD . ' flex flex-wrap items-center gap-x-4 gap-y-1 !p-3 hover:border-camel">'
                . order_badge($o['status'])
                . '<span class="font-mono text-sm">' . e($o['order_number']) . '</span>'
                . '<span class="text-sm">' . e($o['customer_name']) . ' 様</span>'
                . '<span class="text-sm text-muted">' . e(($snap['product']['name'] ?? '') . '（' . ($snap['product']['code'] ?? '') . '）') . '</span>'
                . '<span class="ml-auto text-sm">' . e(format_price((int) $o['total'])) . '</span>'
                . '<span class="w-full text-xs text-muted sm:w-auto">' . e(format_datetime($o['created_at'])) . '</span></a></li>';
        }
        $h .= '</ul>';
    }
    render_admin('注文', $h);
}

function admin_order_detail(string $id, ?string $error = null): void
{
    $o = db_one('SELECT * FROM orders WHERE id = ?', [$id]);
    if (!$o) {
        admin_not_found();
        return;
    }
    $snap = json_decode((string) $o['snapshot'], true) ?: [];
    $pay = db_one("SELECT * FROM payments WHERE order_id = ? ORDER BY created_at DESC LIMIT 1", [$id]);
    $payInfo = $pay ? (json_decode((string) $pay['raw_result'], true) ?: []) : [];
    $ship = db_one('SELECT * FROM shipping_records WHERE order_id = ? ORDER BY created_at DESC LIMIT 1', [$id]);
    $row = static fn (string $k, string $v) => '<div class="grid grid-cols-[7.5rem_1fr] gap-2 py-1.5"><dt class="text-muted">' . e($k) . '</dt><dd>' . $v . '</dd></div>';
    $h = '<p class="mb-4"><a href="' . e(url('/admin/orders')) . '" class="text-sm text-leather underline">← 注文の一覧へ</a></p>'
        . '<div class="grid gap-4 lg:grid-cols-2"><section class="' . A_CARD . '"><h2 class="flex items-center gap-3 font-serif text-lg text-leather">' . e($o['order_number']) . ' ' . order_badge($o['status']) . '</h2>'
        . '<dl class="mt-3 divide-y divide-line text-sm">'
        . $row('注文日時', e(format_datetime($o['created_at'])))
        . $row('作品', e(($snap['product']['name'] ?? '') . '（品番 ' . ($snap['product']['code'] ?? '') . '）'))
        . $row('作品代金', e(format_price((int) $o['subtotal'])))
        . $row('送料', e(format_price((int) $o['shipping_fee']) . '（' . ($snap['shipping']['name'] ?? '') . '）'))
        . $row('合計', '<strong>' . e(format_price((int) $o['total'])) . '</strong>')
        . $row('お名前', e($o['customer_name']) . ' 様')
        . $row('メール', '<a href="mailto:' . e($o['email']) . '" class="break-all text-leather underline">' . e($o['email']) . '</a>')
        . $row('電話', '<a href="tel:' . e($o['phone']) . '" class="text-leather underline">' . e($o['phone']) . '</a>')
        . $row('お届け先', '〒' . e($o['postal_code']) . '<br>' . e($o['address']))
        . ($o['customer_note'] ? $row('ご要望', '<span class="whitespace-pre-line">' . e($o['customer_note']) . '</span>') : '')
        . '</dl></section><section class="' . A_CARD . '"><h2 class="font-serif text-lg text-leather">決済・発送</h2><dl class="mt-3 divide-y divide-line text-sm">'
        . $row('決済', $pay ? e('カード ' . ($payInfo['brand'] ?? '') . ' 下4桁 ' . ($payInfo['card_suffix'] ?? '')) . '<br><span class="text-xs text-muted">ゼウスのオーダーNo：' . e((string) ($payInfo['order_number'] ?? ''))
            . ((($payInfo['endpoint'] ?? '') === 'sandbox') ? '（サンドボックス）' : '') . '</span>' : '—')
        . ($payInfo['sales_limit_date'] ?? '' ? $row('売上の期限', e($payInfo['sales_limit_date']) . '<br><span class="text-xs text-muted">ゼウスの設定が「仮売上」の場合、この日までに売上管理画面で売上確定が必要です</span>') : '')
        . $row('入金', $o['paid_at'] ? e(format_datetime($o['paid_at'])) : '—')
        . $row('発送', $ship ? e(format_date($ship['shipped_on']) . '　' . $ship['carrier'] . ($ship['tracking_number'] ? '　' . $ship['tracking_number'] : '')) : '—')
        . ($o['admin_note'] ? $row('メモ', '<span class="whitespace-pre-line text-xs">' . e($o['admin_note']) . '</span>') : '')
        . '</dl>';
    if ($o['status'] === 'paid') {
        $h .= '<form method="post" action="' . e(url('/admin/orders/' . $id . '/ship')) . '" class="mt-4 space-y-3 border-t border-line pt-4">' . csrf_field()
            . '<h3 class="font-medium">発送したら</h3>'
            . admin_field('配送方法', '<input id="carrier" name="carrier" required maxlength="50" value="' . e((string) ($snap['shipping']['name'] ?? '')) . '" class="' . A_INPUT . '">', 'carrier')
            . admin_field('お問い合わせ番号', '<input id="tracking" name="tracking" maxlength="60" inputmode="numeric" class="' . A_INPUT . ' font-mono">', 'tracking', 'レターパックの追跡番号など')
            . '<label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" name="notify" value="1" checked> お客さんに発送のお知らせメールを送る</label>'
            . '<button class="' . A_BTN_PRIMARY . '">発送済みにする</button></form>';
    }
    if ($o['status'] === 'awaiting_payment') {
        $h .= '<form method="post" action="' . e(url('/admin/orders/' . $id . '/cancel')) . '" class="mt-6 space-y-2 border-t border-line pt-4" data-confirm="この決済待ちの注文を取り消して、作品を販売中に戻します。よろしいですか？">' . csrf_field()
            . '<p class="text-xs leading-5 text-muted">お客さんが支払いの途中でやめた注文です。そのままでも ' . RESERVE_MINUTES . '分たつと自動で取り消されます。<br>念のため、決済設定の「ゼウスとの通信の記録」にこの注文の「pay … success」が無いことを確かめてから取り消してください。</p>'
            . '<button class="' . A_BTN_DANGER . '">取り消して作品を販売中に戻す</button></form>';
    }
    if (in_array($o['status'], ['paid', 'shipped'], true)) {
        $h .= '<form method="post" action="' . e(url('/admin/orders/' . $id . '/cancel')) . '" class="mt-6 space-y-2 border-t border-line pt-4" data-confirm="この注文をキャンセル扱いにします。返金（カードの取消）はゼウスの売上管理画面で行ってください。よろしいですか？">' . csrf_field()
            . '<p class="text-xs leading-5 text-muted">キャンセルにしても、<strong>カードの取消（返金）は自動では行われません</strong>。ゼウスの売上管理画面で取消してください。</p>'
            . '<label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" name="restock" value="1"> 作品を「販売中」に戻す</label>'
            . '<button class="' . A_BTN_DANGER . '">キャンセルにする</button></form>';
    }
    $h .= '</section></div>';
    render_admin('注文の詳細', $h, $error);
}

function admin_order_ship(string $id): void
{
    $o = db_one('SELECT * FROM orders WHERE id = ?', [$id]);
    if (!$o || $o['status'] !== 'paid') {
        redirect('/admin/orders/' . $id);
    }
    $carrier = post_str('carrier', 50);
    $tracking = preg_replace('/[^\w\-]/u', '', post_str('tracking', 60));
    if ($carrier === '') {
        admin_order_detail($id, '配送方法を入力してください。');
        return;
    }
    db_exec('INSERT INTO shipping_records (id, order_id, carrier, tracking_number, shipped_on, address_snapshot) VALUES (?, ?, ?, ?, CURDATE(), ?)',
        [uuid(), $id, $carrier, $tracking ?: null, json_encode(['name' => $o['customer_name'], 'postal' => $o['postal_code'], 'address' => $o['address']], JSON_UNESCAPED_UNICODE)]);
    db_exec("UPDATE orders SET status = 'shipped', shipped_at = NOW(), updated_at = NOW() WHERE id = ?", [$id]);
    if (($_POST['notify'] ?? '') === '1') {
        send_shipped_mail($o, $carrier, $tracking);
        db_exec('UPDATE shipping_records SET notified_at = NOW() WHERE order_id = ?', [$id]);
    }
    audit('order.shipped', $id, ['number' => $o['order_number'], 'tracking' => $tracking]);
    redirect('/admin/orders/' . $id . '?notice=saved');
}

function admin_order_cancel(string $id): void
{
    $o = db_one('SELECT * FROM orders WHERE id = ?', [$id]);
    if ($o && $o['status'] === 'awaiting_payment') {
        cancel_unpaid_order($o, '管理画面で取り消し（決済待ち・' . current_admin()['email'] . '）');
        audit('order.cancel_unpaid', $id, ['number' => $o['order_number']]);
        redirect('/admin/orders/' . $id . '?notice=saved');
    }
    if ($o && in_array($o['status'], ['paid', 'shipped'], true)) {
        db_exec("UPDATE orders SET status = 'cancelled', cancelled_at = NOW(), admin_note = CONCAT(COALESCE(admin_note, ''), ?), updated_at = NOW() WHERE id = ?",
            ['管理画面でキャンセル（' . current_admin()['email'] . "）\n", $id]);
        if (($_POST['restock'] ?? '') === '1') {
            $item = db_one('SELECT product_id FROM order_items WHERE order_id = ? LIMIT 1', [$id]);
            if ($item && $item['product_id']) {
                db_exec("UPDATE products SET status = 'on_sale', sold_at = NULL, updated_at = NOW() WHERE id = ? AND status = 'sold_out'", [$item['product_id']]);
            }
        }
        audit('order.cancel', $id, ['number' => $o['order_number']]);
    }
    redirect('/admin/orders/' . $id . '?notice=saved');
}

// ================================================================== 決済設定
function admin_payment(?string $error = null): void
{
    $c = zeus_cfg();
    $tester = tester_cookie_valid();
    $radio = static fn (string $name, string $value, string $label, string $cur, string $hint = '') => '<label class="flex min-h-11 items-start gap-2 text-sm"><input type="radio" name="' . $name . '" value="' . $value . '" class="mt-1"' . ($cur === $value ? ' checked' : '') . '><span>' . e($label) . ($hint ? '<br><span class="text-xs text-muted">' . e($hint) . '</span>' : '') . '</span></label>';
    $h = '<form method="post" action="' . e(url('/admin/payment')) . '" class="' . A_CARD . ' space-y-5" autocomplete="off">' . csrf_field()
        . '<h2 class="font-serif text-lg text-leather">ゼウス（クレジットカード決済）</h2>'
        . '<fieldset><legend class="text-sm font-medium">購入の受付</legend>'
        . $radio('mode', 'off', '停止（購入ボタンを押せない）', $c['mode'])
        . $radio('mode', 'tester', 'テスト担当の端末だけ購入できる', $c['mode'], '下の「この端末をテスト担当にする」を押したパソコン・スマホだけ、購入手続きに進めます')
        . $radio('mode', 'live', '公開（だれでも購入できる）', $c['mode'], '本番のIPコード・認証キーと「本番」の接続先で、テストが済んでからにしてください') . '</fieldset>'
        . '<fieldset><legend class="text-sm font-medium">接続先</legend>'
        . $radio('endpoint', 'sandbox', 'サンドボックス（3-Dセキュアの動作確認用。お金は動きません）', $c['endpoint'])
        . $radio('endpoint', 'production', '本番環境（テストIPコードならテストカードだけ、本番IPコードなら実際の決済）', $c['endpoint']) . '</fieldset>'
        . admin_field('IPコード', '<input id="clientip" name="clientip" inputmode="numeric" maxlength="10" value="' . e($c['clientip']) . '" class="' . A_INPUT . ' font-mono">', 'clientip', 'ゼウスの売上管理画面「設定情報確認」に出ている数字（5桁または10桁）')
        . admin_field('認証キー', '<input id="zkey" name="key" type="password" maxlength="40" autocomplete="new-password" placeholder="' . ($c['key'] !== '' ? '登録済み（変えるときだけ入力）' : '') . '" class="' . A_INPUT . ' font-mono">', 'zkey', '画面には再表示しません。変えないときは空欄のまま保存してください')
        . '<label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" name="confirm_live" value="1"> 「公開」にするときは、ここにチェックを入れてください</label>'
        . '<button class="' . A_BTN_PRIMARY . '">保存する</button></form>';

    $h .= '<section class="' . A_CARD . ' mt-6 space-y-3"><h2 class="font-serif text-lg text-leather">この端末でテスト購入</h2>'
        . '<p class="text-sm">いまの状態：' . ($tester ? '<strong class="text-green-800">この端末はテスト担当です</strong>' : 'この端末はテスト担当ではありません') . '</p>'
        . '<form method="post" action="' . e(url('/admin/payment/tester')) . '" class="flex flex-wrap gap-3">' . csrf_field()
        . '<button name="act" value="on" class="' . A_BTN_GHOST . '">この端末をテスト担当にする</button>'
        . ($tester ? '<button name="act" value="off" class="' . A_BTN_GHOST . '">テスト担当をやめる</button>' : '') . '</form>'
        . '<p class="text-xs leading-5 text-muted">テスト担当にできるのは、いま使っているブラウザだけです（ほかの端末で試すときは、その端末で管理画面に入って押してください）。もう一度押すと、前の端末はテスト担当でなくなります。</p></section>';

    $events = db_all("SELECT payload, received_at FROM payment_events WHERE provider = 'zeus' ORDER BY received_at DESC LIMIT 15");
    $h .= '<section class="' . A_CARD . ' mt-6"><h2 class="font-serif text-lg text-leather">ゼウスとの通信の記録（新しい順）</h2>';
    if (!$events) {
        $h .= '<p class="mt-2 text-sm text-muted">まだありません。</p>';
    } else {
        $h .= '<ul class="mt-2 divide-y divide-line text-xs">';
        foreach ($events as $ev) {
            $p = json_decode((string) $ev['payload'], true) ?: [];
            $h .= '<li class="py-2"><span class="text-muted">' . e(format_datetime($ev['received_at'])) . '</span>　<strong>' . e((string) ($p['label'] ?? '')) . '</strong>　'
                . e('status=' . ($p['status'] ?? '') . ' code=' . ($p['code'] ?? '') . ' http=' . ($p['http'] ?? '')) . (!empty($p['error']) ? ' <span class="text-red-700">' . e((string) $p['error']) . '</span>' : '')
                . (!empty($p['body']) ? '<details class="mt-1"><summary class="cursor-pointer text-muted">ゼウスの返事を見る</summary><pre class="mt-1 whitespace-pre-wrap break-all rounded bg-paper p-2">' . e(mb_substr((string) $p['body'], 0, 1500)) . '</pre></details>' : '') . '</li>';
        }
        $h .= '</ul>';
    }
    $h .= '</section>';
    render_admin('決済設定', $h, $error);
}

function admin_payment_save(): void
{
    $c = zeus_cfg();
    $mode = in_array($_POST['mode'] ?? '', ['off', 'tester', 'live'], true) ? $_POST['mode'] : 'off';
    $endpoint = ($_POST['endpoint'] ?? '') === 'production' ? 'production' : 'sandbox';
    $clientip = preg_replace('/\D/', '', post_str('clientip', 10));
    $key = trim(post_str('key', 40));
    if ($clientip !== '' && !preg_match('/^\d{5,10}$/', $clientip)) {
        admin_payment('IPコードは5桁または10桁の数字です。');
        return;
    }
    if ($key !== '' && !preg_match('/^[A-Za-z0-9]{10,40}$/', $key)) {
        admin_payment('認証キーの形が違います（英数字）。コピーした前後に空白が入っていないか確認してください。');
        return;
    }
    if ($mode === 'live' && (($_POST['confirm_live'] ?? '') !== '1' || $endpoint !== 'production')) {
        admin_payment('「公開」にするには、接続先を「本番環境」にして、確認のチェックを入れてください。');
        return;
    }
    $new = ['mode' => $mode, 'endpoint' => $endpoint, 'clientip' => $clientip, 'key' => $key !== '' ? $key : $c['key']];
    if ($mode !== 'off' && ($new['clientip'] === '' || $new['key'] === '')) {
        admin_payment('購入を受け付けるには、IPコードと認証キーの両方が必要です。');
        return;
    }
    set_setting('payment.zeus', $new, current_admin()['email']);
    audit('payment.settings', null, ['mode' => $mode, 'endpoint' => $endpoint, 'clientip' => $clientip, 'key_changed' => $key !== '']);
    redirect('/admin/payment?notice=saved');
}

function admin_payment_tester(): void
{
    $path = (base_path() ?: '') . '/';
    if (($_POST['act'] ?? '') === 'on') {
        $t = bin2hex(random_bytes(24));
        set_setting('payment.tester', ['hash' => hash('sha256', $t)], current_admin()['email']);
        setcookie(TESTER_COOKIE, $t, ['expires' => time() + 60 * 60 * 24 * 30, 'path' => $path, 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax']);
        $_COOKIE[TESTER_COOKIE] = $t;
        audit('payment.tester_on');
    } else {
        set_setting('payment.tester', [], current_admin()['email']);
        setcookie(TESTER_COOKIE, '', ['expires' => time() - 3600, 'path' => $path, 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax']);
        audit('payment.tester_off');
    }
    redirect('/admin/payment?notice=saved');
}

// ================================================================== お問い合わせ
function admin_inquiries(): void
{
    $all = query_str('show', 10) === 'all';
    $q = query_str('q', 60);
    if ($q !== '') {
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $rows = db_all('SELECT * FROM contact_messages WHERE subject LIKE ? OR name LIKE ? OR email LIKE ? OR phone LIKE ? OR body LIKE ? ORDER BY created_at DESC LIMIT 200', [$like, $like, $like, $like, $like]);
        $all = true;
    } else {
        $rows = db_all('SELECT * FROM contact_messages ' . ($all ? '' : 'WHERE handled = 0 ') . 'ORDER BY created_at DESC LIMIT 200');
    }
    $tab = static fn (string $label, string $q, bool $on) => '<a href="' . e(url('/admin/inquiries' . $q)) . '" class="inline-flex min-h-10 items-center rounded-full border px-4 text-sm '
        . ($on ? 'border-leather bg-leather text-ivory' : 'border-line bg-white') . '">' . e($label) . '</a>';
    $h = '<form method="get" action="' . e(url('/admin/inquiries')) . '" class="mb-4 flex flex-wrap gap-2"><input name="q" value="' . e($q) . '" placeholder="受付番号・名前・LINEの表示名で探す（例：M261126-1234）" class="' . A_INPUT . ' max-w-md flex-1"><button class="' . A_BTN_GHOST . '">探す</button></form>'
        . '<div class="mb-4 flex flex-wrap gap-2">' . $tab('未対応', '', !$all && $q === '') . $tab('対応済みも含めて全部', '?show=all', $all && $q === '') . '</div>';
    if (!$rows) {
        $h .= '<p class="' . A_CARD . ' text-sm text-muted">' . ($q !== '' ? '「' . e($q) . '」に当てはまるものはありません。' : ($all ? 'お問い合わせはまだありません。' : '未対応のお問い合わせはありません。')) . '</p>';
    } else {
        $h .= '<ul class="space-y-2">';
        foreach ($rows as $r) {
            $h .= '<li><a href="' . e(url('/admin/inquiries/' . $r['id'])) . '" class="' . A_CARD . ' block hover:border-camel">'
                . '<p class="flex flex-wrap items-center gap-2 text-sm"><span class="rounded px-2 py-0.5 text-xs ' . ($r['handled'] ? 'bg-gray-100 text-gray-600' : 'bg-amber-100 text-amber-900') . '">' . ($r['handled'] ? '対応済み' : '未対応') . '</span>'
                . '<span class="text-muted">' . e(date('Y.m.d H:i', strtotime($r['created_at']))) . '</span><span class="font-medium">' . e($r['name']) . ' 様</span></p>'
                . '<p class="mt-1 text-sm">' . e((string) $r['subject']) . '</p>'
                . '<p class="mt-1 line-clamp-2 text-xs text-muted">' . e(mb_substr($r['body'], 0, 120)) . '</p></a></li>';
        }
        $h .= '</ul>';
    }
    render_admin('お問い合わせ', $h);
}

function admin_inquiry_detail(string $id): void
{
    $r = db_one('SELECT * FROM contact_messages WHERE id = ?', [$id]);
    if (!$r) {
        redirect('/admin/inquiries');
    }
    $reply = 'mailto:' . rawurlencode($r['email']) . '?subject=' . rawurlencode('Re: お問い合わせの件（' . SITE_NAME . '）');
    $h = '<p class="mb-4"><a href="' . e(url('/admin/inquiries')) . '" class="text-sm text-muted underline">← 一覧へ戻る</a></p>'
        . '<div class="' . A_CARD . ' space-y-3 text-sm leading-7">'
        . '<p><span class="text-muted">受付：</span>' . e(date('Y年n月j日 H:i', strtotime($r['created_at']))) . '</p>'
        . '<p><span class="text-muted">種類：</span>' . e((string) $r['subject']) . '</p>'
        . '<p><span class="text-muted">お名前：</span>' . e($r['name']) . ' 様</p>'
        . '<p><span class="text-muted">メール：</span><a href="' . e($reply) . '" class="text-leather underline">' . e($r['email']) . '</a></p>'
        . ($r['phone'] ? '<p><span class="text-muted">電話：</span><a href="tel:' . e($r['phone']) . '" class="text-leather underline">' . e($r['phone']) . '</a></p>' : '')
        . '<div class="border-t border-line pt-3"><p class="text-muted">内容</p><p class="mt-1 whitespace-pre-wrap break-words">' . e($r['body']) . '</p></div></div>'
        . admin_inquiry_files_html($id)
        . '<div class="mt-4 flex flex-wrap gap-3"><a href="' . e($reply) . '" class="' . A_BTN_PRIMARY . '">メールで返事を書く</a>'
        . '<form method="post" action="' . e(url('/admin/inquiries/' . $id)) . '">' . csrf_field()
        . '<button class="' . A_BTN_GHOST . '">' . ($r['handled'] ? '未対応に戻す' : '対応済みにする') . '</button></form></div>'
        . '<p class="mt-3 text-xs leading-5 text-muted">お知らせメール（kiyokawa@ 宛て）に「返信」しても、お客さんに届きます。</p>';
    render_admin('お問い合わせ', $h);
}

function admin_inquiry_toggle(string $id): void
{
    db_exec('UPDATE contact_messages SET handled = 1 - handled WHERE id = ?', [$id]);
    audit('contact.toggle', $id);
    redirect('/admin/inquiries/' . $id . '?notice=saved');
}

function admin_inquiry_files_html(string $id): string
{
    $files = customer_files_of($id);
    if (!$files) {
        return '';
    }
    $li = '';
    foreach ($files as $f) {
        $u = url('/admin/inquiries/' . $id . '/files/' . $f['id']);
        $li .= '<li class="flex flex-wrap items-center gap-4 border-t border-line py-3 first:border-0">'
            . ((int) $f['is_image'] === 1 ? '<a href="' . e($u . '?inline=1') . '" target="_blank" rel="noopener"><img src="' . e($u . '?inline=1') . '" alt="" class="h-20 w-20 rounded border border-line bg-white object-contain"></a>' : '<span class="inline-flex h-20 w-20 items-center justify-center rounded border border-line bg-paper text-xs uppercase text-muted">' . e(pathinfo($f['original_name'], PATHINFO_EXTENSION) ?: 'file') . '</span>')
            . '<div class="min-w-0 flex-1 text-sm"><p class="break-all font-medium">' . e($f['original_name']) . '</p><p class="text-xs text-muted">' . e(customer_file_size_text((int) $f['size'])) . '</p>'
            . '<div class="mt-2 flex flex-wrap gap-2"><a href="' . e($u) . '" class="' . A_BTN_GHOST . '">ダウンロード</a>'
            . '<form method="post" action="' . e($u) . '" data-confirm="このファイルを削除します。元に戻せません。よろしいですか？">' . csrf_field() . '<button class="' . A_BTN_GHOST . ' text-red-700">削除</button></form></div></div></li>';
    }
    return '<div class="' . A_CARD . ' mt-4"><h2 class="font-serif text-lg text-leather">添付ファイル（' . count($files) . '）</h2>'
        . '<p class="mt-1 text-xs leading-5 text-muted">お客さんから届いたファイルです。見知らぬ形式のファイルは開かないでください。制作が終わって不要になったら削除してください。</p><ul class="mt-3">' . $li . '</ul></div>';
}

function admin_inquiry_file(string $id, string $fid): void
{
    $f = db_one('SELECT * FROM contact_files WHERE id = ? AND message_id = ?', [$fid, $id]);
    if (!$f) {
        redirect('/admin/inquiries/' . $id);
    }
    customer_file_send($f, query_str('inline', 2) === '1');
}

function admin_inquiry_file_delete(string $id, string $fid): void
{
    $f = db_one('SELECT * FROM contact_files WHERE id = ? AND message_id = ?', [$fid, $id]);
    if ($f) {
        customer_file_delete($f);
        audit('contact.file_delete', $id, ['name' => $f['original_name']]);
    }
    redirect('/admin/inquiries/' . $id . '?notice=saved');
}
