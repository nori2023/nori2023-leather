<?php
declare(strict_types=1);

/**
 * 初回設定（app/config.php がないときだけ表示）
 * データベースの接続情報と管理者アカウントを登録し、テーブルを作成する。
 * 第三者に先に設定されないよう「設置キー」（app/install_key.php）の入力が必要。
 */
function route_install(string $path): void
{
    send_security_headers(true);
    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex');
    if ($path !== '/install') {
        header('Location: ' . url('/install'), true, 302);
        exit;
    }
    session_name('nori_install');
    session_set_cookie_params(['path' => base_path() . '/install', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Strict']);
    session_start();
    $_SESSION['csrf'] ??= bin2hex(random_bytes(16));

    $error = null;
    $v = ['db_host' => 'localhost', 'db_name' => '', 'db_user' => '', 'email' => '', 'site_url' => ''];
    if (is_post()) {
        foreach (array_keys($v) as $k) {
            $v[$k] = post_str($k, 255);
        }
        $error = install_submit($v);
        if ($error === null) {
            header('Location: ' . url('/admin/login?installed=1'), true, 303);
            exit;
        }
    }
    install_form($v, $error);
}

function install_submit(array $v): ?string
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string) ($_POST['_csrf'] ?? ''))) {
        return '画面の有効期限が切れました。もう一度お試しください。';
    }
    $key = require APP_DIR . '/install_key.php';
    if (!hash_equals((string) $key, strtoupper(post_str('install_key', 50)))) {
        sleep(2);
        return '設置キーが違います。';
    }
    $dbPass = (string) ($_POST['db_pass'] ?? '');
    $pw = (string) ($_POST['password'] ?? '');
    if ($v['db_name'] === '' || $v['db_user'] === '') {
        return 'データベース名とユーザー名を入力してください。';
    }
    if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) {
        return '管理者のメールアドレスを正しく入力してください。';
    }
    if ($problem = password_problem($pw)) {
        return $problem;
    }
    if ($pw !== (string) ($_POST['password2'] ?? '')) {
        return '確認用のパスワードが一致しません。';
    }
    if ($v['site_url'] !== '' && !preg_match('#^https://[^\s]+$#', $v['site_url'])) {
        return '公開URLは https:// から始まる形で入力してください（空欄でも可）。';
    }
    try {
        $pdo = db_connect($v['db_host'], $v['db_name'], $v['db_user'], $dbPass);
    } catch (Throwable $e) {
        return 'データベースに接続できませんでした。ホスト名・データベース名・ユーザー名・パスワードを確認してください。';
    }
    // 別のシステムのデータベースを指定していないか確認（このサイトの表以外があれば中止）
    $existing = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $foreign = array_values(array_diff($existing, install_known_tables()));
    if ($foreign) {
        return 'このデータベースには、ほかのシステムの表（' . implode('、', array_slice($foreign, 0, 5))
            . (count($foreign) > 5 ? ' ほか' : '') . '）が入っています。このサイト専用に新しく作ったデータベースを指定してください。';
    }
    try {
        db_migrate($pdo);
        // 途中で止まった設定のやり直しにも対応（同じメールアドレスならパスワードを上書き）
        $st = $pdo->prepare('INSERT INTO admin_users (id, email, password_hash) VALUES (?, ?, ?)
                             ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)');
        $st->execute([uuid(), mb_strtolower($v['email']), password_hash($pw, PASSWORD_DEFAULT)]);
    } catch (Throwable $e) {
        error_log('[nori2023 install] ' . $e->getMessage());
        return 'テーブルの作成に失敗しました。この画面をスクリーンショットで Claude に送ってください。（詳細：'
            . mb_substr($e->getMessage(), 0, 300) . '）';
    }
    $config = [
        'db' => ['host' => $v['db_host'], 'name' => $v['db_name'], 'user' => $v['db_user'], 'pass' => $dbPass, 'port' => 3306],
        'site_url' => rtrim($v['site_url'], '/'),
    ];
    $php = "<?php\n// 自動生成された設定ファイル（公開しないこと）\nreturn " . var_export($config, true) . ";\n";
    if (file_put_contents(APP_DIR . '/config.php', $php, LOCK_EX) === false) {
        return '設定ファイルを書き込めませんでした。app フォルダの書き込み権限を確認してください。';
    }
    @chmod(APP_DIR . '/config.php', 0600);
    return null;
}

/** このサイトが作る表の名前（migrations の CREATE TABLE から取得） */
function install_known_tables(): array
{
    $names = ['schema_migrations'];
    foreach (glob(APP_DIR . '/migrations/*.sql') ?: [] as $f) {
        preg_match_all('/CREATE TABLE (?:IF NOT EXISTS )?`?(\w+)`?/i', (string) file_get_contents($f), $m);
        $names = array_merge($names, $m[1]);
    }
    return array_values(array_unique($names));
}

function install_form(array $v, ?string $error): void
{
    $input = 'mt-1 block w-full rounded border border-line bg-white px-3 py-2.5 text-[16px]';
    $field = function (string $name, string $label, string $type = 'text', string $hint = '', bool $keep = true) use ($v, $input): string {
        return '<label class="block text-sm font-medium">' . e($label)
            . '<input name="' . e($name) . '" type="' . e($type) . '" ' . ($keep && isset($v[$name]) ? 'value="' . e($v[$name]) . '"' : '')
            . ' class="' . $input . '" autocomplete="off"></label>'
            . ($hint ? '<p class="mt-1 text-xs text-muted">' . e($hint) . '</p>' : '');
    };
    ?>
<!DOCTYPE html>
<html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex"><title>初回設定｜おっさんのレザークラフト</title>
<link rel="stylesheet" href="<?= e(asset('assets/app.css')) ?>"></head>
<body class="admin min-h-screen bg-paper text-[15px] text-charcoal">
<main class="mx-auto max-w-lg px-4 py-10">
  <h1 class="font-serif text-xl text-leather">初回設定</h1>
  <p class="mt-2 text-sm text-muted">データベースの接続情報と、管理画面に入るためのアカウントを登録します。この画面は設定が終わると表示されなくなります。</p>
  <?php if ($error): ?><p role="alert" class="mt-5 rounded border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800"><?= e($error) ?></p><?php endif ?>
  <form method="post" class="mt-6 space-y-6 rounded-md border border-line bg-white p-5">
    <input type="hidden" name="_csrf" value="<?= e($_SESSION['csrf']) ?>">
    <?= $field('install_key', '設置キー', 'text', 'Claude から伝えた英数字12文字', false) ?>
    <fieldset class="space-y-4"><legend class="font-serif text-leather">データベース（エックスサーバーの「MySQL設定」で作成したもの）</legend>
      <?= $field('db_host', 'MySQLホスト名', 'text', 'エックスサーバーの MySQL設定画面に表示されている「MySQLホスト名」（例：localhost または mysqlXXXX.xserver.jp）') ?>
      <?= $field('db_name', 'データベース名', 'text', '例：xs123456_nori2023') ?>
      <?= $field('db_user', 'MySQLユーザー名', 'text', '例：xs123456_nori') ?>
      <?= $field('db_pass', 'MySQLパスワード', 'password', '', false) ?>
    </fieldset>
    <fieldset class="space-y-4"><legend class="font-serif text-leather">管理者アカウント</legend>
      <?= $field('email', 'メールアドレス', 'email') ?>
      <?= $field('password', 'パスワード', 'password', '10文字以上・英字と数字を含む。忘れないようにメモしてください', false) ?>
      <?= $field('password2', 'パスワード（確認）', 'password', '', false) ?>
    </fieldset>
    <?= $field('site_url', '公開URL（任意）', 'url', '例：https://mpr-shop.com/nori2023　空欄なら自動で判定します') ?>
    <button class="inline-flex min-h-11 w-full items-center justify-center rounded bg-leather px-5 text-sm font-medium text-ivory">設定して始める</button>
  </form>
</main></body></html>
<?php
}
