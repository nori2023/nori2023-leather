<?php
declare(strict_types=1);

/**
 * ゼウス決済（Secure API：トークン型＋EMV 3-Dセキュア）
 *  仕様書：system_creditcard_s-api_token.pdf（EnrolReq → PaReq/PaRes → AuthReq → PayReq）
 *  - カード番号はこのサーバーを通らない（ブラウザでゼウスのJSがトークンに置きかえる）
 *  - IPコード・認証キーは管理画面の「決済設定」で保存（画面には再表示しない）
 */

const ZEUS_ENDPOINTS = [
    'production' => [
        'api' => 'https://linkpt.cardservice.co.jp/cgi-bin/secure/api.cgi',
        'js' => 'https://linkpt.cardservice.co.jp/api/token/2.0/zeus_token_cvv2.js',
        'css' => 'https://linkpt.cardservice.co.jp/api/token/1.0/zeus_token.css',
        'origin' => 'https://linkpt.cardservice.co.jp',
    ],
    'sandbox' => [
        'api' => 'https://secure2-sandbox.cardservice.co.jp/cgi-bin/secure/api.cgi',
        'js' => 'https://secure2-sandbox.cardservice.co.jp/api/token/2.0/zeus_token_cvv2.js',
        'css' => 'https://secure2-sandbox.cardservice.co.jp/api/token/1.0/zeus_token.css',
        'origin' => 'https://secure2-sandbox.cardservice.co.jp',
    ],
];

const PREFECTURES = ['北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県', '茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県',
    '新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県', '岐阜県', '静岡県', '愛知県', '三重県', '滋賀県', '京都府', '大阪府', '兵庫県', '奈良県', '和歌山県',
    '鳥取県', '島根県', '岡山県', '広島県', '山口県', '徳島県', '香川県', '愛媛県', '高知県', '福岡県', '佐賀県', '長崎県', '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県'];

function zeus_cfg(): array
{
    $c = get_setting('payment.zeus', []);
    $c = is_array($c) ? $c : [];
    return [
        'mode' => in_array($c['mode'] ?? '', ['off', 'tester', 'live'], true) ? $c['mode'] : 'off',
        'endpoint' => ($c['endpoint'] ?? '') === 'production' ? 'production' : 'sandbox',
        'clientip' => preg_match('/^\d{5,10}$/', (string) ($c['clientip'] ?? '')) ? (string) $c['clientip'] : '',
        'key' => preg_match('/^[A-Za-z0-9]{1,40}$/', (string) ($c['key'] ?? '')) ? (string) $c['key'] : '',
    ];
}

function zeus_ready(): bool
{
    $c = zeus_cfg();
    return $c['clientip'] !== '' && $c['key'] !== '';
}

function zeus_ep(): array
{
    if (!empty(config()['zeus_mock'])) { // 開発用（手元の環境だけ）：ゼウスにつながず、まねをする
        return ['api' => 'mock', 'js' => asset('assets/dev/zeus-mock.js'), 'css' => asset('assets/app.css'), 'origin' => "'self'"];
    }
    return ZEUS_ENDPOINTS[zeus_cfg()['endpoint']];
}

/** 開発用のまね（config.php に 'zeus_mock' => true があるときだけ） */
function zeus_mock_response(string $xml, string $label): string
{
    $fail = str_contains($xml, '<token_key>FAIL');
    $status = $fail && $label === 'enroll' ? 'failure' : 'success';
    return match ($label) {
        'enroll' => '<?xml version="1.0" encoding="utf-8"?><response service="secure_link_3d" action="enroll"><result><status>' . $status . '</status><code>' . ($fail ? 'E99' : '001') . '</code></result>'
            . '<xid>MOCK' . bin2hex(random_bytes(8)) . '</xid><threeDS2flag>1</threeDS2flag><iframeUrl>about%3Ablank</iframeUrl></response>',
        'auth' => '<?xml version="1.0" encoding="utf-8"?><response service="secure_link_3d" action="authentication"><result><status>success</status><code>002</code></result></response>',
        default => '<?xml version="1.0" encoding="utf-8"?><response service="secure_link_3d" action="payment" version="v2"><result><status>success</status><code>000</code></result>'
            . '<order_number>MOCK-' . random_int(100000, 999999) . '</order_number><sales_limit_date>' . date('Ymd', strtotime('+60 days')) . '</sales_limit_date>'
            . '<card><number><prefix>41</prefix><suffix>0100</suffix></number></card><addition_value><ctype>T</ctype></addition_value></response>',
    };
}

// ------------------------------------------------------------------ テスト購入できる端末
const TESTER_COOKIE = 'nori_tester';

function tester_cookie_valid(): bool
{
    $t = (string) ($_COOKIE[TESTER_COOKIE] ?? '');
    $h = (string) (get_setting('payment.tester', [])['hash'] ?? '');
    return $t !== '' && $h !== '' && hash_equals($h, hash('sha256', $t));
}

/** 購入手続きを受け付けるか（本番公開中、またはテスト担当の端末） */
function checkout_open(): bool
{
    $c = zeus_cfg();
    if (!zeus_ready()) {
        return false;
    }
    return $c['mode'] === 'live' || ($c['mode'] === 'tester' && tester_cookie_valid());
}

// ------------------------------------------------------------------ 通信
/** XML を送って、返ってきた XML を配列で返す */
function zeus_post(string $xml, string $label, ?string $orderId = null): array
{
    if (strlen($xml) > 8192) {
        throw new RuntimeException('ZEUS request too large');
    }
    if (!empty(config()['zeus_mock'])) {
        $body = zeus_mock_response($xml, $label);
        $res = ['http' => 200, 'ok' => false, 'status' => '', 'code' => '', 'xml' => null, 'error' => ''];
        $x = simplexml_load_string($body);
        $res['xml'] = $x;
        $res['status'] = (string) $x->result->status;
        $res['code'] = (string) $x->result->code;
        $res['ok'] = $res['status'] === 'success';
        zeus_log($label, $orderId, $res, $body);
        return $res;
    }
    $ch = curl_init(zeus_ep()['api']);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $xml,
        CURLOPT_HTTPHEADER => ['Content-Type: application/xml; charset=utf-8'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 40,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_PROTOCOLS => defined('CURLPROTO_HTTPS') ? constant('CURLPROTO_HTTPS') : 2,
        // TLS 1.2 以上（サーバーによって定数名が無いことがあるので数値でも指定できるようにする）
        CURLOPT_SSLVERSION => defined('CURL_SSLVERSION_TLSv1_2') ? constant('CURL_SSLVERSION_TLSv1_2') : 6,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    $res = ['http' => $code, 'ok' => false, 'status' => '', 'code' => '', 'xml' => null, 'error' => $err];
    if (is_string($body) && $body !== '') {
        $prev = libxml_use_internal_errors(true);
        $x = simplexml_load_string($body, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_use_internal_errors($prev);
        if ($x !== false) {
            $res['xml'] = $x;
            $res['status'] = (string) ($x->result->status ?? '');
            $res['code'] = (string) ($x->result->code ?? '');
            $res['ok'] = $res['status'] === 'success';
        }
    }
    zeus_log($label, $orderId, $res, $body);
    return $res;
}

/** 通信の記録（認証キーやトークンは残さない） */
function zeus_log(string $label, ?string $orderId, array $res, mixed $body): void
{
    try {
        if (is_string($body) && !mb_check_encoding($body, 'UTF-8')) {
            // ゼウスの返事が Shift_JIS のときも記録できるようにする
            $body = mb_convert_encoding($body, 'UTF-8', 'SJIS-win');
        }
        $clean = is_string($body) ? preg_replace('#<(key|token_key)>[^<]*</#', '<$1>***</', $body) : null;
        $payload = ['label' => $label, 'order' => $orderId, 'http' => $res['http'] ?? 0, 'status' => $res['status'] ?? '', 'code' => $res['code'] ?? '', 'error' => $res['error'] ?? '',
            'body' => is_string($clean) ? mb_substr($clean, 0, 3000) : null];
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        db_exec('INSERT INTO payment_events (id, provider, event_key, payload, processed) VALUES (?, ?, ?, ?, 1)',
            [uuid(), 'zeus', $label . ':' . uuid(), $json ?: '{"label":"log-error"}']);
    } catch (Throwable $e) {
        error_log('[nori2023 zeus log] ' . $e->getMessage());
    }
}

function x(string $v): string
{
    return htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

/** 文字数（バイト数）を超えないように切る */
function zeus_cut(string $v, int $bytes): string
{
    return mb_strcut($v, 0, $bytes, 'UTF-8');
}

// ------------------------------------------------------------------ EnrolReq（3-Dセキュア認証判定）
function zeus_enroll(array $order, string $token, string $cardName): array
{
    $c = zeus_cfg();
    $tel = preg_replace('/\D/', '', (string) $order['phone']);
    $mobile = ltrim($tel, '0');
    $snap = json_decode((string) $order['snapshot'], true) ?: [];
    $ship = $snap['ship'] ?? [];
    $prefCode = str_pad((string) ((int) ($ship['pref_index'] ?? -1) + 1), 2, '0', STR_PAD_LEFT);
    $name = strtoupper(trim(preg_replace('/[^A-Za-z .\-]/', '', $cardName) ?? ''));
    $risk = '<cardHolderInfo>'
        . '<addrMatch>Y</addrMatch>'
        . ($name !== '' && strlen($name) >= 2 ? '<cardholderName>' . x(zeus_cut($name, 45)) . '</cardholderName>' : '')
        . '<email>' . x($order['email']) . '</email>'
        . ($mobile !== '' ? '<mobilePhone><cc>392</cc><subscriber>' . x(zeus_cut($mobile, 15)) . '</subscriber></mobilePhone>' : '')
        . '<shipAddrCity>' . x(zeus_cut((string) ($ship['city'] ?? ''), 50)) . '</shipAddrCity>'
        . '<shipAddrCountry>392</shipAddrCountry>'
        . '<shipAddrLine1>' . x(zeus_cut((string) ($ship['line1'] ?? ''), 50)) . '</shipAddrLine1>'
        . (($ship['line2'] ?? '') !== '' ? '<shipAddrLine2>' . x(zeus_cut((string) $ship['line2'], 50)) . '</shipAddrLine2>' : '')
        . '<shipAddrPostCode>' . x((string) $order['postal_code']) . '</shipAddrPostCode>'
        . ($prefCode !== '00' ? '<shipAddrState>' . $prefCode . '</shipAddrState>' : '')
        . '</cardHolderInfo>';
    $xml = '<?xml version="1.0" encoding="utf-8"?>'
        . '<request service="secure_link_3d" action="enroll">'
        . '<authentication><clientip>' . x($c['clientip']) . '</clientip><key>' . x($c['key']) . '</key></authentication>'
        . '<token_key>' . x($token) . '</token_key>'
        . '<payment><amount>' . (int) $order['total'] . '</amount><count>01</count></payment>'
        . '<user><telno validation="strict">' . x($tel) . '</telno><email language="japanese">' . x(zeus_cut($order['email'], 50)) . '</email></user>'
        . '<uniq_key><sendid>' . x($order['order_number']) . '</sendid><sendpoint>' . x(zeus_cut((string) ($snap['product']['code'] ?? ''), 50)) . '</sendpoint></uniq_key>'
        . '<use_3ds2_flag>1</use_3ds2_flag>'
        . $risk
        . '</request>';
    $r = zeus_post($xml, 'enroll', $order['id']);
    $r['xid'] = $r['xml'] ? (string) ($r['xml']->xid ?? '') : '';
    $r['iframeUrl'] = $r['xml'] ? (string) ($r['xml']->iframeUrl ?? '') : '';
    return $r;
}

// ------------------------------------------------------------------ AuthReq（認証結果）／PayReq（オーソリ）
function zeus_auth(string $xid, string $pares): array
{
    $xml = '<?xml version="1.0" encoding="utf-8"?><request service="secure_link_3d" action="authentication">'
        . '<xid>' . x($xid) . '</xid><PaRes>' . ($pares === 'Y' ? 'Y' : 'N') . '</PaRes></request>';
    return zeus_post($xml, 'auth');
}

function zeus_pay(string $xid, ?string $orderId): array
{
    $xml = '<?xml version="1.0" encoding="utf-8"?><request service="secure_link_3d" action="payment" version="v2">'
        . '<xid>' . x($xid) . '</xid><print_addition_value>yes</print_addition_value></request>';
    $r = zeus_post($xml, 'pay', $orderId);
    if ($r['xml']) {
        $r['order_number'] = (string) ($r['xml']->order_number ?? '');
        $r['sales_limit_date'] = (string) ($r['xml']->sales_limit_date ?? '');
        $r['card_suffix'] = (string) ($r['xml']->card->number->suffix ?? '');
        $r['ctype'] = (string) ($r['xml']->addition_value->ctype ?? '');
    }
    return $r;
}

/** お客さんに見せるエラーの言葉（細かいコードは管理画面の記録で見る） */
function zeus_error_message(string $status): string
{
    return match ($status) {
        'failure' => 'カード会社で決済（本人認証）が通りませんでした。別のカードでお試しいただくか、カード会社にお問い合わせください。',
        'invalid' => '入力内容に誤りがありました。カード情報をもう一度ご確認ください。',
        'maintenance' => '決済システムのメンテナンス中です。しばらくしてからお試しください。',
        default => '決済の処理ができませんでした。時間をおいてもう一度お試しください。',
    };
}
