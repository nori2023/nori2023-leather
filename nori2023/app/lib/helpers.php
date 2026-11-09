<?php
declare(strict_types=1);

/** HTML エスケープ */
function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** 設置フォルダ（例：/nori2023）。index.php の場所から自動判定 */
function base_path(): string
{
    static $base = null;
    if ($base === null) {
        $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        $base = rtrim($dir, '/');
        if ($base === '.') {
            $base = '';
        }
    }
    return $base;
}

/** サイト内URL（設置フォルダ付き） */
function url(string $path = '/'): string
{
    if ($path === '' || $path[0] !== '/') {
        $path = '/' . $path;
    }
    return base_path() . $path;
}

/** 静的ファイルURL（更新時刻でキャッシュを切り替え） */
function asset(string $path): string
{
    $file = ROOT_DIR . '/' . ltrim($path, '/');
    $v = is_file($file) ? (string) filemtime($file) : '1';
    return url('/' . ltrim($path, '/')) . '?v=' . $v;
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}

/** サイトの絶対URL（OGP・サイトマップ用） */
function absolute_url(string $path = '/'): string
{
    $configured = config()['site_url'] ?? '';
    if ($configured !== '') {
        return rtrim($configured, '/') . ($path === '/' ? '/' : $path);
    }
    $scheme = is_https() ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . url($path);
}

/** 設置フォルダを除いたリクエストパス（例：/shop/abc） */
function request_path(): string
{
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $uri = rawurldecode($uri);
    $base = base_path();
    if ($base !== '' && str_starts_with($uri, $base)) {
        $uri = substr($uri, strlen($base));
    }
    if ($uri === '' || $uri === false) {
        $uri = '/';
    }
    if ($uri !== '/' && str_ends_with($uri, '/')) {
        $uri = rtrim($uri, '/');
    }
    if ($uri === '/index.php') {
        $uri = '/';
    }
    return $uri;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function redirect(string $path, int $code = 303): never
{
    header('Location: ' . url($path), true, $code);
    exit;
}

function json_response(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function post_str(string $key, int $max = 1000): string
{
    $v = $_POST[$key] ?? '';
    if (!is_string($v)) {
        return '';
    }
    $v = str_replace("\r\n", "\n", $v);
    return mb_substr(trim($v), 0, $max);
}

function query_str(string $key, int $max = 200): string
{
    $v = $_GET[$key] ?? '';
    return is_string($v) ? mb_substr(trim($v), 0, $max) : '';
}

function uuid(): string
{
    $d = random_bytes(16);
    $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
    $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function format_price(int $v): string
{
    return '¥' . number_format($v);
}

function format_date(?string $dt): string
{
    if (!$dt) {
        return '';
    }
    $t = strtotime($dt);
    return $t ? date('Y.m.d', $t) : '';
}

function format_datetime(?string $dt): string
{
    if (!$dt) {
        return '';
    }
    $t = strtotime($dt);
    return $t ? date('Y.m.d H:i', $t) : '';
}

/** アップロード画像のURL */
function upload_url(string $key): string
{
    return url('/' . UPLOAD_DIR_NAME . '/' . implode('/', array_map('rawurlencode', explode('/', $key))));
}

/** JSON-LD をHTMLに安全に埋め込む */
function json_ld(array $data): string
{
    return '<script type="application/ld+json">'
        . str_replace('<', '<', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
        . '</script>';
}

/** 出力バッファでテンプレートを文字列化 */
function capture(callable $fn): string
{
    ob_start();
    try {
        $fn();
    } catch (Throwable $t) {
        ob_end_clean();
        throw $t;
    }
    return (string) ob_get_clean();
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}
