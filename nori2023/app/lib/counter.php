<?php
declare(strict_types=1);

/*
 * トップページの訪問者カウンター
 * - 数えるのはブラウザのJSから届いた /visit だけ（JSを動かさないロボットは数えない）
 * - 同じ端末は24時間に1回だけ（クッキー）
 * - 「この端末を数えない」にした端末（おっさん本人など）は数えない
 */
const VISIT_COOKIE = 'nori_visit';
const NOCOUNT_COOKIE = 'nori_nocount';
const COUNTER_DIGITS = 6;

function counter_cfg(): array
{
    return array_merge(['enabled' => true], (array) get_setting('counter', []));
}

/** 今の数（テーブルがまだ無いときは null） */
function counter_total(): ?int
{
    try {
        $v = db()->query('SELECT total FROM visitor_counter WHERE id = 1')->fetchColumn();
        return $v === false ? null : (int) $v;
    } catch (Throwable) {
        return null;
    }
}

function counter_cookie_path(): string
{
    return (base_path() ?: '') . '/';
}

function counter_is_bot(): bool
{
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    if ($ua === '' || strlen($ua) < 20) {
        return true;
    }
    return (bool) preg_match('/bot|crawl|spider|slurp|headless|lighthouse|pagespeed|preview|monitor|scan|curl|wget|python|java\/|go-http|okhttp|axios|node-fetch|php\//i', $ua);
}

/** 1を足して新しい数を返す（同時アクセスでも数がずれないよう、DBの中で足す） */
function counter_increment(): ?int
{
    try {
        $pdo = db();
        $st = $pdo->prepare('UPDATE visitor_counter SET total = LAST_INSERT_ID(total + 1), updated_at = NOW() WHERE id = 1');
        $st->execute();
        if ($st->rowCount() !== 1) {
            return null;
        }
        return (int) $pdo->query('SELECT LAST_INSERT_ID()')->fetchColumn();
    } catch (Throwable) {
        return null;
    }
}

/** POST /visit：数えたかどうかと今の数を返す */
function counter_visit(): never
{
    header('Cache-Control: no-store');
    $cfg = counter_cfg();
    if (!$cfg['enabled'] || !same_origin() || ($_SERVER['HTTP_X_NORI_VISIT'] ?? '') !== '1') {
        json_response(['ok' => false], 403);
    }
    $counted = false;
    $n = null;
    if (empty($_COOKIE[VISIT_COOKIE]) && empty($_COOKIE[NOCOUNT_COOKIE]) && !counter_is_bot()) {
        $n = counter_increment();
        if ($n !== null) {
            $counted = true;
            setcookie(VISIT_COOKIE, '1', ['expires' => time() + 86400, 'path' => counter_cookie_path(), 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax']);
        }
    }
    $n ??= counter_total();
    if ($n === null) {
        json_response(['ok' => false], 503);
    }
    json_response(['ok' => true, 'n' => $n, 'counted' => $counted]);
}

/** トップページに入れるカウンターの欄 */
function counter_section(): string
{
    if (!counter_cfg()['enabled']) {
        return '';
    }
    $n = counter_total();
    if ($n === null) {
        return '';
    }
    $text = str_pad((string) $n, COUNTER_DIGITS, '0', STR_PAD_LEFT);
    return '<section class="nori-counter border-t border-line/60 px-5 py-16 sm:py-20" aria-label="訪問者カウンター" data-counter data-n="' . $n . '" data-digits="' . COUNTER_DIGITS . '" data-endpoint="' . e(url('/visit')) . '">'
        . '<p class="nori-counter__lead">懐かしいじゃろ？<br class="sm:hidden"> おっさん、こんなん付けてみました（笑）</p>'
        . '<div class="tally" aria-hidden="true"><div class="tally__ring"></div>'
        . '<div class="tally__button"><div class="tally__button-stem"></div><div class="tally__button-cap"></div></div>'
        . '<div class="tally__body"><div class="tally__brand">NORI2023</div></div><div class="tally__knob"></div>'
        . '<div class="tally__window" data-counter-window></div></div>'
        . '<p class="nori-counter__foot">あなたは <span class="num" data-counter-num>' . e($text) . '</span> 人目の訪問者さんです。<br class="sm:hidden">ようこそ！</p>'
        . '</section>';
}
