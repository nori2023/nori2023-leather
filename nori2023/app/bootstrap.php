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
function send_security_headers(bool $admin = false): void
{
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: ' . ($admin ? 'DENY' : 'SAMEORIGIN'));
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    if (is_https()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
    if ($admin) {
        header('Cache-Control: no-store');
    }
}
