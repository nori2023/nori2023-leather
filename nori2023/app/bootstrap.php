<?php
/**
 * 共通の初期化：設定読み込み・DB接続・共通関数
 */
declare(strict_types=1);

const APP_DIR = __DIR__;
define('ROOT_DIR', dirname(__DIR__));
const UPLOAD_DIR_NAME = 'uploads';

date_default_timezone_set('Asia/Tokyo');
mb_internal_encoding('UTF-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);

require APP_DIR . '/lib/helpers.php';
require APP_DIR . '/lib/db.php';
require APP_DIR . '/lib/site.php';
require APP_DIR . '/lib/settings.php';
require APP_DIR . '/lib/auth.php';
require APP_DIR . '/lib/products.php';
require APP_DIR . '/lib/images.php';
require APP_DIR . '/lib/instagram.php';
require_once APP_DIR . '/lib/security.php';
require APP_DIR . '/lib/zeus.php';
require APP_DIR . '/lib/orders.php';
require APP_DIR . '/lib/counter.php';
require APP_DIR . '/lib/customer_files.php';

set_exception_handler(function (Throwable $e): void {
    error_log('[nori2023] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo 'いま表示できんのよ。ちいと時間をおいて、もう一回試してみてつかぁさい。';
});

function config(): array
{
    static $config = null;
    if ($config === null) {
        $file = APP_DIR . '/config.php';
        $config = is_file($file) ? require $file : [];
    }
    return $config;
}

function is_installed(): bool
{
    return is_file(APP_DIR . '/config.php');
}

/** 共通のセキュリティヘッダー */
function send_security_headers(bool $admin = false, array $extra = []): void
{
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: ' . ($admin ? 'DENY' : 'SAMEORIGIN'));
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    // 読み込んでよいのは自サイトと Google Fonts だけ（不正なスクリプトの埋め込み対策）
    // 公開ページは Google アナリティクス（GA4）の読み込み・送信先を許可する（管理画面は許可しない）
    // $extra：決済ページだけ、ゼウスとカード会社の本人認証画面を追加で許可する
    $ga = $admin ? '' : ' https://www.googletagmanager.com';
    $gaConnect = $admin ? '' : ' https://*.google-analytics.com https://*.analytics.google.com https://*.googletagmanager.com';
    $add = static fn (string $k): string => !empty($extra[$k]) ? ' ' . implode(' ', $extra[$k]) : '';
    $styleInline = !empty($extra['style_inline']) ? " 'unsafe-inline'" : '';
    $frame = !empty($extra['frame']) ? 'frame-src' . $add('frame') . '; ' : '';
    header("Content-Security-Policy: default-src 'self'; script-src 'self'{$ga}" . $add('script') . "; style-src 'self' https://fonts.googleapis.com" . $add('style') . "{$styleInline}; "
        . "font-src https://fonts.gstatic.com; img-src 'self' data:{$gaConnect}" . $add('img') . "; connect-src 'self'{$gaConnect}" . $add('connect') . "; {$frame}object-src 'none'; base-uri 'self'; "
        . "form-action 'self'" . $add('form') . "; frame-ancestors " . ($admin ? "'none'" : "'self'"));
    // HSTS は mpr-shop.com 全体に効いてしまうため、ここでは送らない（常時SSLは .htaccess で対応）
    if ($admin) {
        header('Cache-Control: no-store');
    }
}
