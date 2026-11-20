<?php
declare(strict_types=1);

/**
 * 1点ものの注文
 *  - 注文を受けた時点で作品を「購入手続き中（reserved）」にして、二重販売を防ぐ
 *  - 30分以内に決済が終わらなければ、自動で「販売中」に戻す
 */
const RESERVE_MINUTES = 30;

const ORDER_STATUS_LABEL = [
    'awaiting_payment' => '決済待ち',
    'paid' => '入金済み（発送待ち）',
    'shipped' => '発送済み',
    'completed' => '完了',
    'cancelled' => 'キャンセル',
];

/** 期限切れの仮押さえを戻す（ページを開いたときなどに呼ぶ） */
function release_expired_reservations(): void
{
    try {
        $rows = db_all("SELECT r.id, r.product_id, r.order_id FROM product_reservations r
                         WHERE r.status = 'active' AND r.expires_at < NOW() LIMIT 20");
        foreach ($rows as $r) {
            $pdo = db();
            $pdo->beginTransaction();
            db_exec("UPDATE product_reservations SET status = 'expired', released_at = NOW() WHERE id = ? AND status = 'active'", [$r['id']]);
            db_exec("UPDATE products SET status = 'on_sale', updated_at = NOW() WHERE id = ? AND status = 'reserved'", [$r['product_id']]);
            if ($r['order_id']) {
                db_exec("UPDATE orders SET status = 'cancelled', cancelled_at = NOW(), admin_note = CONCAT(COALESCE(admin_note, ''), '決済されないまま期限切れ（自動）\\n'), updated_at = NOW()
                         WHERE id = ? AND status = 'awaiting_payment'", [$r['order_id']]);
            }
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        error_log('[nori2023 release] ' . $e->getMessage());
    }
}

function new_order_number(): string
{
    for ($i = 0; $i < 10; $i++) {
        $n = 'N' . date('ymd') . '-' . random_int(1000, 9999);
        if (!db_value('SELECT COUNT(*) FROM orders WHERE order_number = ?', [$n])) {
            return $n;
        }
    }
    return 'N' . date('ymdHis');
}

/** ネットで買えるだけの情報（送料）がそろっているか */
function product_shippable(array $p): bool
{
    return ($p['shipping_name'] ?? null) !== null && ($p['shipping_fee'] ?? null) !== null;
}

/** カード決済の完了後の発送の目安（お客さん向け） */
const SHIP_AFTER_PAID = 'お支払いを確認しましたので、3営業日以内に発送いたします。';

/**
 * 作品を仮押さえして注文を作る。売れてしまっていたら null。
 * $in：お客さんの入力（検証済み）
 */
function create_product_order(array $product, array $in): ?array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        // 同時に2人が押しても1人だけ通るように、行をロックしてから確かめる
        $p = db_one('SELECT p.*, s.name AS shipping_name, s.fee AS shipping_fee FROM products p
                     LEFT JOIN shipping_profiles s ON s.id = p.shipping_profile_id WHERE p.id = ? FOR UPDATE', [$product['id']]);
        if (!$p || $p['status'] !== 'on_sale' || !product_shippable($p)) {
            $pdo->rollBack();
            return null;
        }
        $fee = $p['shipping_fee'] !== null ? (int) $p['shipping_fee'] : 0;
        $id = uuid();
        $token = bin2hex(random_bytes(20));
        $number = new_order_number();
        $address = PREFECTURES[$in['pref_index']] . $in['city'] . $in['line1'] . ($in['line2'] !== '' ? ' ' . $in['line2'] : '');
        $snapshot = [
            'product' => ['id' => $p['id'], 'code' => $p['code'], 'name' => $p['name'], 'slug' => $p['slug'], 'price' => (int) $p['price']],
            'shipping' => ['name' => $p['shipping_name'], 'fee' => $fee],
            'ship' => ['pref_index' => $in['pref_index'], 'city' => $in['city'], 'line1' => $in['line1'], 'line2' => $in['line2']],
            'consent_at' => date('c'),
        ];
        db_exec('INSERT INTO orders (id, order_number, access_token, order_type, status, customer_name, email, phone, postal_code, address,
                 subtotal, shipping_fee, total, customer_note, snapshot, pay_expires_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))',
            [$id, $number, $token, 'product', 'awaiting_payment', $in['name'], $in['email'], $in['phone'], $in['postal'], $address,
                (int) $p['price'], $fee, (int) $p['price'] + $fee, $in['note'] !== '' ? $in['note'] : null,
                json_encode($snapshot, JSON_UNESCAPED_UNICODE), RESERVE_MINUTES]);
        db_exec('INSERT INTO order_items (id, order_id, product_id, name_snapshot, unit_price, quantity, line_total) VALUES (?, ?, ?, ?, ?, 1, ?)',
            [uuid(), $id, $p['id'], $p['name'] . '（品番 ' . $p['code'] . '）', (int) $p['price'], (int) $p['price']]);
        db_exec("INSERT INTO product_reservations (id, product_id, order_id, status, expires_at) VALUES (?, ?, ?, 'active', DATE_ADD(NOW(), INTERVAL ? MINUTE))",
            [uuid(), $p['id'], $id, RESERVE_MINUTES]);
        db_exec("UPDATE products SET status = 'reserved', updated_at = NOW() WHERE id = ?", [$p['id']]);
        $pdo->commit();
        return order_by_token($token);
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function order_by_token(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{40}$/', $token)) {
        return null;
    }
    return db_one('SELECT * FROM orders WHERE access_token = ?', [$token]);
}

/** 決済が通ったときの処理（何度呼ばれても1回分だけ反映） */
function mark_order_paid(array $order, array $pay): bool
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $o = db_one('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$order['id']]);
        if (!$o || $o['status'] !== 'awaiting_payment') {
            $pdo->rollBack();
            return false;
        }
        db_exec("UPDATE orders SET status = 'paid', paid_at = NOW(), updated_at = NOW() WHERE id = ?", [$o['id']]);
        db_exec("INSERT INTO payments (id, order_id, provider, provider_txn_id, amount, status, verified_at, raw_result)
                 VALUES (?, ?, 'zeus', ?, ?, 'succeeded', NOW(), ?)",
            [uuid(), $o['id'], $pay['order_number'] ?: $o['zeus_xid'], (int) $o['total'],
                json_encode(['order_number' => $pay['order_number'] ?? '', 'sales_limit_date' => $pay['sales_limit_date'] ?? '',
                    'card_suffix' => $pay['card_suffix'] ?? '', 'brand' => $pay['ctype'] ?? '', 'endpoint' => zeus_cfg()['endpoint']], JSON_UNESCAPED_UNICODE)]);
        $item = db_one('SELECT product_id FROM order_items WHERE order_id = ? LIMIT 1', [$o['id']]);
        if ($item && $item['product_id']) {
            db_exec("UPDATE products SET status = 'sold_out', sold_at = NOW(), updated_at = NOW() WHERE id = ?", [$item['product_id']]);
            db_exec("UPDATE product_reservations SET status = 'converted', released_at = NOW() WHERE order_id = ? AND status = 'active'", [$o['id']]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    $fresh = db_one('SELECT * FROM orders WHERE id = ?', [$order['id']]);
    send_order_mails($fresh);
    audit('order.paid', $order['id'], ['number' => $order['order_number']]);
    return true;
}

/** お客さんが購入をやめたとき（決済前） */
function cancel_unpaid_order(array $order, string $why): void
{
    $pdo = db();
    $pdo->beginTransaction();
    $o = db_one('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$order['id']]);
    if ($o && $o['status'] === 'awaiting_payment') {
        db_exec("UPDATE orders SET status = 'cancelled', cancelled_at = NOW(), admin_note = CONCAT(COALESCE(admin_note, ''), ?), updated_at = NOW() WHERE id = ?", [$why . "\n", $o['id']]);
        $r = db_one("SELECT * FROM product_reservations WHERE order_id = ? AND status = 'active'", [$o['id']]);
        if ($r) {
            db_exec("UPDATE product_reservations SET status = 'released', released_at = NOW() WHERE id = ?", [$r['id']]);
            db_exec("UPDATE products SET status = 'on_sale', updated_at = NOW() WHERE id = ? AND status = 'reserved'", [$r['product_id']]);
        }
    }
    $pdo->commit();
}

function order_summary_text(array $o): string
{
    $snap = json_decode((string) $o['snapshot'], true) ?: [];
    return "■ご注文番号：{$o['order_number']}\n"
        . '■作品：' . ($snap['product']['name'] ?? '') . '（品番 ' . ($snap['product']['code'] ?? '') . "）\n"
        . '■作品代金：' . number_format((int) $o['subtotal']) . "円（税込）\n"
        . '■送料：' . number_format((int) $o['shipping_fee']) . '円（' . ($snap['shipping']['name'] ?? '') . "）\n"
        . '■お支払い合計：' . number_format((int) $o['total']) . "円（税込・クレジットカード）\n\n"
        . "■お届け先\n{$o['customer_name']} 様\n〒{$o['postal_code']} {$o['address']}\n電話：{$o['phone']}\n"
        . ($o['customer_note'] ? "\n■ご要望・連絡事項\n{$o['customer_note']}\n" : '');
}

function send_order_mails(array $o): void
{
    $si = SELLER_INFO;
    $sig = "\n――――――――――――――――\n" . SITE_NAME . "（制作：" . MAKER_NAME . "）\n販売：{$si['company']}\n"
        . "{$si['postal']} {$si['address']}\nTEL {$si['tel']}（{$si['tel_hours']}）\nMail {$si['email']}\n" . absolute_url('/') . "\n";
    $body = "{$o['customer_name']} 様\n\nこのたびは「" . SITE_NAME . "」の作品をお買い上げいただき、ありがとうございます。\n"
        . "クレジットカードでのお支払いが完了しました。ご注文の内容は次のとおりです。\n\n"
        . order_summary_text($o)
        . "\n" . SHIP_AFTER_PAID . "\n発送しましたら、お問い合わせ番号をメールでお知らせします。\n"
        . "\nご不明な点は、このメールに返信するか、下記までお問い合わせください。\n" . $sig;
    send_mail($o['email'], '【' . SITE_NAME . '】ご注文ありがとうございます（' . $o['order_number'] . '）', $body);
    send_mail($si['email'], '【注文】' . $o['order_number'] . ' ' . $o['customer_name'] . ' 様',
        "ネットショップで注文があり、カード決済が完了しました。\n\n" . order_summary_text($o) . "\nメール：{$o['email']}\n\n管理画面：" . absolute_url('/admin/orders') . "\n");
}

function send_shipped_mail(array $o, string $carrier, string $tracking): void
{
    $si = SELLER_INFO;
    $body = "{$o['customer_name']} 様\n\nご注文の作品を発送しました。届くまで、もうしばらくお待ちください。\n\n"
        . "■ご注文番号：{$o['order_number']}\n■配送方法：{$carrier}\n" . ($tracking !== '' ? "■お問い合わせ番号：{$tracking}\n" : '')
        . "\n使やぁ使うほど、ええ味が出てくる革小物です。長う使うてもらえたら、うれしいです。\n"
        . "\n――――――――――――――――\n" . SITE_NAME . "\n販売：{$si['company']}\nTEL {$si['tel']}　Mail {$si['email']}\n";
    send_mail($o['email'], '【' . SITE_NAME . '】発送のお知らせ（' . $o['order_number'] . '）', $body);
}
