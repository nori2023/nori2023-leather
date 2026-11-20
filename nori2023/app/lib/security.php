<?php
declare(strict_types=1);

/**
 * 管理画面の追加の守り（ゼウス「ECサイトのセキュリティ対策実施状況申告書」(1)① 対応）
 *  - ベーシック認証：管理画面に入る前に、ユーザー名と合言葉を聞く
 *  - 2段階認証：ログインのとき、登録メールアドレスに届く6桁のコードを入れる
 *  どちらも管理画面の「セキュリティ」から設定する。忘れたときは /admin/reset（再設定キー）で解除できる。
 */
const TWOFA_CODE_TTL = 600;      // コードの有効時間（秒）
const TWOFA_MAX_TRIES = 5;       // コードの入力ミスの上限
const TWOFA_RESEND_WAIT = 60;    // 再送までの待ち時間（秒）

// ------------------------------------------------------------------ メール送信
function mail_from(): string
{
    $from = (string) (config()['mail_from'] ?? '');
    if ($from === '') {
        $host = preg_replace('/^www\./', '', parse_url(absolute_url('/'), PHP_URL_HOST) ?: ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $from = 'no-reply@' . $host;
    }
    return $from;
}

function send_mail(string $to, string $subject, string $body, ?string $replyTo = null): bool
{
    $log = (string) (config()['mail_log'] ?? '');
    if ($log !== '') { // 開発用：送らずにファイルへ書く
        return (bool) file_put_contents($log, "To: {$to}\n" . ($replyTo ? "Reply-To: {$replyTo}\n" : '') . "Subject: {$subject}\n\n{$body}\n----\n", FILE_APPEND);
    }
    mb_language('uni'); // UTF-8 で送る（機種依存文字も化けない）
    mb_internal_encoding('UTF-8');
    $from = mail_from();
    $headers = 'From: ' . mb_encode_mimeheader(SITE_NAME, 'UTF-8') . " <{$from}>\r\nContent-Language: ja";
    if ($replyTo !== null && filter_var($replyTo, FILTER_VALIDATE_EMAIL) && !preg_match('/[\r\n]/', $replyTo)) {
        $headers .= "\r\nReply-To: {$replyTo}";
    }
    return @mb_send_mail($to, $subject, $body, $headers, '-f' . $from);
}

// ------------------------------------------------------------------ ベーシック認証
function basic_auth_cfg(): array
{
    $c = get_setting('security.basic', []);
    return is_array($c) && !empty($c['user']) && !empty($c['hash']) ? $c : [];
}

/** ブラウザから送られてきたユーザー名と合言葉（Xserver では .htaccess で Authorization を渡している） */
function basic_auth_sent(): ?array
{
    if (isset($_SERVER['PHP_AUTH_USER'])) {
        return [(string) $_SERVER['PHP_AUTH_USER'], (string) ($_SERVER['PHP_AUTH_PW'] ?? '')];
    }
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (stripos((string) $h, 'basic ') === 0) {
        $dec = base64_decode(substr((string) $h, 6), true);
        if ($dec !== false && str_contains($dec, ':')) {
            return explode(':', $dec, 2);
        }
    }
    return null;
}

function basic_auth_check(array $cfg): bool
{
    $sent = basic_auth_sent();
    if (!$sent) {
        return false;
    }
    $ok = hash_equals((string) $cfg['user'], $sent[0]) && password_verify($sent[1], (string) $cfg['hash']);
    if (!$ok) {
        db_exec('INSERT INTO login_attempts (ip, email, succeeded) VALUES (?, ?, 0)', [client_ip(), 'basic-auth']);
    }
    return $ok;
}

function basic_auth_deny(string $realm, string $note): never
{
    header('WWW-Authenticate: Basic realm="' . $realm . '", charset="UTF-8"');
    http_response_code(401);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>認証が必要です</title>'
        . '<div style="font-family:sans-serif;max-width:28rem;margin:4rem auto;padding:0 1rem;line-height:1.8">'
        . '<h1 style="font-size:1.1rem">管理画面の入口で、ユーザー名と合言葉が必要です</h1><p>' . $note . '</p></div>';
    exit;
}

/** 管理画面の入口でベーシック認証を求める（設定されていなければ何もしない） */
function require_basic_auth(): void
{
    $cfg = basic_auth_cfg();
    if (!$cfg) {
        return;
    }
    if (login_locked(client_ip(), 'basic-auth')) {
        http_response_code(429);
        exit('失敗が続いたため、しばらく入れません。15分ほど待ってからお試しください。');
    }
    if (!basic_auth_check($cfg)) {
        basic_auth_deny('Nori2023 admin', '合言葉を忘れた場合は、再設定キーを使って <a href="' . e(url('/admin/reset')) . '">' . e(url('/admin/reset')) . '</a> から解除できます。');
    }
}

// ------------------------------------------------------------------ 2段階認証（メールのコード）
function twofa_enabled(): bool
{
    return (bool) (get_setting('security.2fa', [])['enabled'] ?? false);
}

function twofa_issue(array $admin, string $purpose): bool
{
    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['twofa'] = [
        'admin' => $admin,
        'purpose' => $purpose,
        'hash' => password_hash($code, PASSWORD_DEFAULT),
        'expires' => time() + TWOFA_CODE_TTL,
        'tries' => 0,
        'sent_at' => time(),
    ];
    $body = "おっさんのレザークラフト 管理画面の確認コードです。\n\n"
        . "　確認コード：{$code}\n\n"
        . "このコードは10分間だけ使えます。\n"
        . "心当たりがない場合は、だれかがパスワードを使ってログインしようとしています。すぐにパスワードを変更してください。\n";
    return send_mail($admin['email'], '【Nori2023 管理画面】確認コード ' . $code, $body);
}

/** 入力されたコードを確かめる。合っていれば pending の管理者を返す */
function twofa_verify(string $code): array
{
    $p = $_SESSION['twofa'] ?? null;
    if (!$p) {
        return ['ok' => false, 'error' => 'もう一度、最初からログインしてください。', 'reset' => true];
    }
    if (time() > $p['expires']) {
        unset($_SESSION['twofa']);
        return ['ok' => false, 'error' => 'コードの有効時間（10分）が過ぎました。もう一度ログインしてください。', 'reset' => true];
    }
    if (!preg_match('/^\d{6}$/', $code) || !password_verify($code, $p['hash'])) {
        $_SESSION['twofa']['tries']++;
        if ($_SESSION['twofa']['tries'] >= TWOFA_MAX_TRIES) {
            unset($_SESSION['twofa']);
            db_exec('INSERT INTO login_attempts (ip, email, succeeded) VALUES (?, ?, 0)', [client_ip(), $p['admin']['email']]);
            return ['ok' => false, 'error' => 'コードの間違いが続いたため、無効にしました。もう一度ログインしてください。', 'reset' => true];
        }
        return ['ok' => false, 'error' => 'コードが違います。（あと' . (TWOFA_MAX_TRIES - $_SESSION['twofa']['tries']) . '回）', 'reset' => false];
    }
    unset($_SESSION['twofa']);
    return ['ok' => true, 'admin' => $p['admin'], 'purpose' => $p['purpose']];
}

/** 再設定キーでパスワードを直したときは、締め出しを防ぐため両方いったん解除する */
function security_disable_all(string $by): void
{
    set_setting('security.2fa', ['enabled' => false], $by);
    set_setting('security.basic', [], $by);
}
