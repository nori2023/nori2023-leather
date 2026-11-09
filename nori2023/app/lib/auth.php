<?php
declare(strict_types=1);

/**
 * 管理者ログイン（メールアドレス＋パスワード）
 *  - パスワードは password_hash（bcrypt）で保存
 *  - 同じIPから15分に5回失敗すると15分ロック
 *  - セッションCookieは HttpOnly / SameSite=Lax / HTTPS時 Secure、パスは設置フォルダ
 *  - 管理画面の変更操作はすべて CSRF トークンを確認
 */
const LOGIN_MAX_FAILS = 5;          // 同じIPからの失敗回数の上限
const LOGIN_MAX_FAILS_EMAIL = 10;   // 同じメールアドレスへの失敗回数の上限（IPを変える攻撃対策）
const LOGIN_WINDOW_MIN = 15;
const SESSION_IDLE_SEC = 60 * 60 * 8;

function start_admin_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('nori_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => (base_path() ?: '') . '/admin',
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    $last = $_SESSION['last_seen'] ?? 0;
    if ($last && time() - $last > SESSION_IDLE_SEC) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['last_seen'] = time();
}

function current_admin(): ?array
{
    return $_SESSION['admin'] ?? null;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/** POST の CSRF 確認（フォームは _csrf、JS の fetch は X-CSRF-Token ヘッダー） */
function verify_csrf(): void
{
    $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $originOk = $origin === '' || parse_url($origin, PHP_URL_HOST) === parse_url('//' . $host, PHP_URL_HOST);
    if (!is_string($sent) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent) || !$originOk) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo '画面の有効期限が切れました。もう一度ページを開き直してください。';
        exit;
    }
}

function login_locked(string $ip, string $email = ''): bool
{
    $byIp = (int) db_value(
        'SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND succeeded = 0 AND attempted_at > (NOW() - INTERVAL ? MINUTE)',
        [$ip, LOGIN_WINDOW_MIN],
    );
    if ($byIp >= LOGIN_MAX_FAILS) {
        return true;
    }
    if ($email === '') {
        return false;
    }
    $byEmail = (int) db_value(
        'SELECT COUNT(*) FROM login_attempts WHERE email = ? AND succeeded = 0 AND attempted_at > (NOW() - INTERVAL ? MINUTE)',
        [mb_strtolower(trim($email)), LOGIN_WINDOW_MIN],
    );
    return $byEmail >= LOGIN_MAX_FAILS_EMAIL;
}

function attempt_login(string $email, string $password): bool
{
    $ip = client_ip();
    $email = mb_strtolower(trim($email));
    $user = db_one('SELECT * FROM admin_users WHERE email = ?', [$email]);
    // ユーザーが存在しない場合も同じ時間がかかるようにダミーで照合
    static $dummy = null;
    $dummy ??= password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
    $ok = password_verify($password, $user['password_hash'] ?? $dummy) && $user !== null;
    db_exec('INSERT INTO login_attempts (ip, email, succeeded) VALUES (?, ?, ?)', [$ip, mb_substr($email, 0, 255), $ok ? 1 : 0]);
    if (!$ok || !$user) {
        return false;
    }
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        db_exec('UPDATE admin_users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }
    session_regenerate_id(true);
    $_SESSION['admin'] = ['id' => $user['id'], 'email' => $user['email']];
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    db_exec('UPDATE admin_users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);
    db_exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 30 DAY)');
    return true;
}

function logout_admin(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** パスワードの最低条件：10文字以上、英字と数字を含む */
function password_problem(string $pw): ?string
{
    if (mb_strlen($pw) < 10) {
        return 'パスワードは10文字以上にしてください。';
    }
    if (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) {
        return 'パスワードには英字と数字の両方を入れてください。';
    }
    return null;
}
