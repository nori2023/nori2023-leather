<?php
declare(strict_types=1);

/**
 * お問い合わせ
 *  GET  /contact          フォーム（?item=作品のslug で、その作品について聞く形にする）
 *  POST /contact          受付 → 保存 → お店へ通知メール・お客さんへ自動返信
 *  GET  /contact/thanks   送信完了
 */

const CONTACT_KINDS = [
    'item'     => '作品について',
    'custom'   => '名入れ・オーダーメイドの相談',
    'workshop' => 'ワークショップについて',
    'order'    => 'ご注文・発送について',
    'other'    => 'その他',
];
const CONTACT_LIMIT_PER_HOUR = 5;

function start_contact_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('nori_ct');
    session_set_cookie_params(['lifetime' => 0, 'path' => (base_path() ?: '') . '/contact', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

function route_contact(string $path): void
{
    start_contact_session();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    match (true) {
        $path === '/contact' && $method === 'POST' => contact_save(),
        $path === '/contact' => contact_form(contact_defaults()),
        $path === '/contact/thanks' => contact_thanks(),
        default => render_not_found(),
    };
}

/** ?item=slug / ?kind=custom などから初期値を作る */
function contact_defaults(): array
{
    $v = ['kind' => '', 'item' => '', 'order' => ''];
    $slug = query_str('item', 80);
    if ($slug !== '' && preg_match('/^[a-z0-9-]+$/', $slug) && ($p = get_public_product($slug))) {
        $v['kind'] = 'item';
        $v['item'] = ($p['code'] ? $p['code'] . '　' : '') . $p['name'];
    }
    $kind = query_str('kind', 20);
    if (isset(CONTACT_KINDS[$kind])) {
        $v['kind'] = $kind;
    }
    return $v;
}

function contact_form(array $v, array $errors = []): void
{
    $val = static fn (string $k): string => e((string) ($v[$k] ?? ''));
    $in = 'mt-1 block w-full min-h-12 rounded border border-line bg-white px-3 text-base focus:border-camel focus:outline-none';
    $field = static function (string $label, string $control, string $id, string $hint = '', bool $req = true) use ($errors): string {
        return '<div><label for="' . $id . '" class="block text-sm font-medium">' . e($label) . ($req ? ' <span class="text-xs text-camel">必須</span>' : ' <span class="text-xs text-muted">任意</span>') . '</label>'
            . $control . ($hint ? '<p class="mt-1 text-xs text-muted">' . e($hint) . '</p>' : '')
            . (isset($errors[$id]) ? '<p class="mt-1 text-sm text-red-700">' . e($errors[$id]) . '</p>' : '') . '</div>';
    };
    $kinds = '';
    foreach (CONTACT_KINDS as $k => $label) {
        $kinds .= '<label class="flex min-h-11 items-center gap-2 text-[0.95rem]"><input type="radio" name="kind" value="' . $k . '" required class="h-5 w-5"'
            . (($v['kind'] ?? '') === $k ? ' checked' : '') . '> ' . e($label) . '</label>';
    }
    $ig = ltrim(INSTAGRAM_HANDLE, '@');
    $body = breadcrumbs([['/contact', 'お問い合わせ']])
        . '<div class="mx-auto max-w-xl px-5 pb-24 pt-8">'
        . '<p class="eyebrow text-center">Contact</p><h1 class="mt-2 text-center text-[1.5rem]">お問い合わせ</h1><div class="stitch mx-auto mt-5 w-16" aria-hidden="true"></div>'
        . '<p class="mt-8 text-[0.95rem] leading-8">作品のこと、名入れのこと、ワークショップのこと。<br>なんでも気軽に聞いてつかぁさい。<br>おっさんが目を通して、<strong class="font-medium text-leather">2〜3日のうちに</strong>メールでお返事します。</p>'
        . '<div class="mt-6 rounded-card border border-line bg-paper p-4 text-sm leading-7">'
        . '<p class="font-medium text-leather">LINE や Instagram でも受けとりますけぇ</p>'
        . '<p class="mt-1 text-muted">写真を送りながら相談したいときは、こっちが早いかもしれません。</p>'
        . '<div class="mt-3 flex flex-wrap gap-2">'
        . '<a href="' . e(LINE_URL) . '" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center gap-2 rounded-[2px] bg-[#06C755] px-4 text-sm font-medium text-white hover:opacity-90">' . chat_icon() . ' 公式LINEで相談（' . e(LINE_ID) . '）</a>'
        . '<a href="https://www.instagram.com/' . e($ig) . '/" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center gap-2 rounded-[2px] border border-line bg-white px-4 text-sm text-leather hover:border-camel">' . instagram_icon() . ' Instagram の DM</a>'
        . '</div></div>'
        . '<p class="mt-8 text-sm font-medium text-leather">メールで問い合わせる</p>'
        . ($errors ? '<p class="mt-6 rounded border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">' . e($errors['_form'] ?? '入力の足りんところがあります。赤い字のところを見てつかぁさい。') . '</p>' : '')
        . '<form method="post" action="' . e(url('/contact')) . '" class="mt-8 space-y-6" novalidate>' . csrf_field()
        // ロボット対策：人には見えない欄（入力されていたら受け付けない）
        . '<div class="hidden" aria-hidden="true"><label>会社のホームページ<input name="website" tabindex="-1" autocomplete="off"></label></div>'
        . '<input type="hidden" name="t" value="' . time() . '">'
        . '<fieldset><legend class="text-sm font-medium">お問い合わせの種類 <span class="text-xs text-camel">必須</span></legend><div class="mt-2 grid gap-1 sm:grid-cols-2">' . $kinds . '</div>'
        . (isset($errors['kind']) ? '<p class="mt-1 text-sm text-red-700">' . e($errors['kind']) . '</p>' : '') . '</fieldset>'
        . $field('気になる作品', '<input id="item" name="item" maxlength="100" value="' . $val('item') . '" class="' . $in . '">', 'item', '作品の名前や品番（例：nori2023-0008）がわかれば', false)
        . $field('ご注文番号', '<input id="order" name="order" maxlength="20" inputmode="text" value="' . $val('order') . '" class="' . $in . ' max-w-[14rem] font-mono">', 'order', 'ご注文についてのときだけ（例：N261120-1234）', false)
        . $field('お名前', '<input id="name" name="name" required maxlength="50" autocomplete="name" value="' . $val('name') . '" class="' . $in . '">', 'name')
        . $field('メールアドレス', '<input id="email" name="email" type="email" required maxlength="100" autocomplete="email" value="' . $val('email') . '" class="' . $in . '">', 'email', 'お返事はこのアドレスに送ります。携帯のメールの方は、mpr-shop.com と mpr.co.jp からのメールを受け取れるようにしてつかぁさい')
        . $field('電話番号', '<input id="phone" name="phone" type="tel" maxlength="15" autocomplete="tel" inputmode="tel" value="' . $val('phone') . '" class="' . $in . ' max-w-[14rem]">', 'phone', '電話で話したほうが早いときだけ使います', false)
        . $field('お問い合わせ内容', '<textarea id="body" name="body" required rows="7" maxlength="2000" class="' . $in . ' py-2 leading-7">' . $val('body') . '</textarea>', 'body', '名入れの相談なら、入れたい文字・色の好み・ほしい時期などを書いてもらえると話が早いです')
        . '<div class="rounded-card border border-line bg-paper p-4 text-sm leading-6">'
        . '<p>いただいた内容は、お返事とご相談の対応のためだけに使います。くわしくは <a href="' . e(url('/legal/privacy')) . '" target="_blank" class="text-leather underline">プライバシーポリシー</a> を見てつかぁさい。</p>'
        . '<label class="mt-3 flex min-h-11 items-start gap-2 font-medium"><input type="checkbox" name="consent" value="1" required class="mt-1 h-5 w-5"' . (!empty($v['consent']) ? ' checked' : '') . '> プライバシーポリシーに同意します</label>'
        . (isset($errors['consent']) ? '<p class="mt-1 text-sm text-red-700">' . e($errors['consent']) . '</p>' : '') . '</div>'
        . '<button class="flex min-h-14 w-full items-center justify-center rounded-[2px] bg-leather text-[1rem] tracking-[0.15em] text-ivory hover:bg-charcoal">送信する</button>'
        . '</form>'
        . '<p class="mt-10 text-center text-xs leading-6 text-muted">' . e(MAKER_SELLER_TEXT) . '<br>お電話：' . e(SELLER_INFO['tel']) . '（' . e((string) SELLER_INFO['tel_hours']) . '）</p>'
        . '</div>';
    render_page(['title' => 'お問い合わせ', 'path' => '/contact', 'description' => '作品のこと、名入れ・オーダーの相談、ワークショップのことなど、お気軽にお問い合わせください。', 'nostore' => true], $body, $errors ? 422 : 200);
}

function contact_save(): void
{
    verify_csrf();
    $v = [];
    foreach (['kind' => 20, 'item' => 100, 'order' => 20, 'name' => 50, 'email' => 100, 'phone' => 15, 'body' => 2000] as $k => $max) {
        $v[$k] = post_str($k, $max);
    }
    $v['consent'] = !empty($_POST['consent']);
    $v['body'] = post_str('body', 2000);

    // ロボット対策（見えない欄が埋まっている／開いてすぐ送信）は、成功したふりをして捨てる
    $elapsed = time() - (int) ($_POST['t'] ?? 0);
    if (post_str('website', 200) !== '' || $elapsed < 3) {
        redirect('/contact/thanks');
    }

    $errors = [];
    if (!isset(CONTACT_KINDS[$v['kind']])) {
        $errors['kind'] = 'お問い合わせの種類を選んでつかぁさい。';
    }
    if ($v['name'] === '') {
        $errors['name'] = 'お名前を入れてつかぁさい。';
    }
    if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'メールアドレスを正しく入れてつかぁさい。';
    }
    $phone = preg_replace('/[^0-9]/', '', mb_convert_kana($v['phone'], 'n'));
    if ($v['phone'] !== '' && (strlen($phone) < 10 || strlen($phone) > 11)) {
        $errors['phone'] = '電話番号は10〜11桁の数字で入れてつかぁさい。';
    }
    if (mb_strlen(trim($v['body'])) < 5) {
        $errors['body'] = 'お問い合わせ内容を書いてつかぁさい。';
    }
    if (!$v['consent']) {
        $errors['consent'] = 'プライバシーポリシーへの同意にチェックを入れてつかぁさい。';
    }
    // 同じ回線からの連続送信を止める（1時間に5回まで）
    $recent = (int) db_value("SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND email = 'contact-form' AND attempted_at > (NOW() - INTERVAL 1 HOUR)", [client_ip()]);
    if ($recent >= CONTACT_LIMIT_PER_HOUR) {
        $errors['_form'] = '短い時間にたくさん送られたので、いったん止めています。時間をおいてもう一度送るか、お電話でご連絡ください。';
    }
    if ($errors) {
        contact_form($v, $errors);
        return;
    }

    $kindLabel = CONTACT_KINDS[$v['kind']];
    $subject = mb_substr($kindLabel . ($v['item'] !== '' ? '：' . $v['item'] : '') . ($v['order'] !== '' ? '（注文 ' . $v['order'] . '）' : ''), 0, 200);
    $id = uuid();
    db_exec('INSERT INTO contact_messages (id, kind, name, email, phone, subject, body) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$id, $v['kind'] === 'custom' ? 'custom_request' : 'contact', $v['name'], $v['email'], $phone !== '' ? $phone : null, $subject, $v['body']]);
    db_exec("INSERT INTO login_attempts (ip, email, succeeded) VALUES (?, 'contact-form', 1)", [client_ip()]);

    $summary = "■種類：{$kindLabel}\n"
        . ($v['item'] !== '' ? "■作品：{$v['item']}\n" : '')
        . ($v['order'] !== '' ? "■ご注文番号：{$v['order']}\n" : '')
        . "■お名前：{$v['name']} 様\n■メール：{$v['email']}\n"
        . ($phone !== '' ? "■電話：{$phone}\n" : '')
        . "\n■内容\n{$v['body']}\n";
    // お店へ（返信ボタンでお客さんに返せるよう Reply-To を付ける）
    send_mail((string) SELLER_INFO['email'], '【お問い合わせ】' . $v['name'] . ' 様（' . $kindLabel . '）',
        "サイトからお問い合わせが届きました。\nこのメールに返信すると、お客さんに届きます。\n\n" . $summary
        . "\n管理画面：" . absolute_url('/admin/inquiries/' . $id) . "\n", $v['email']);
    // お客さんへ（自動返信）
    send_mail($v['email'], '【' . SITE_NAME . '】お問い合わせを受け付けました',
        "{$v['name']} 様\n\nお問い合わせ、ありがとうございます。\n「" . SITE_NAME . "」です。\n\n"
        . "次の内容で受け付けました。2〜3日のうちに、メールでお返事します。\n"
        . "数日たってもお返事が届かないときは、お手数ですが迷惑メールのフォルダをご確認いただくか、お電話でご連絡ください。\n\n"
        . "------------------------------\n" . $summary . "------------------------------\n\n"
        . "※このメールは送信専用のアドレスから自動でお送りしています。このメールへの返信は届きませんので、ご了承ください。\n\n"
        . SITE_NAME . "\n" . MAKER_SELLER_TEXT . "\n" . SELLER_INFO['company'] . "\nTEL " . SELLER_INFO['tel'] . "（" . SELLER_INFO['tel_hours'] . "）\n" . absolute_url('/') . "\n");
    audit('contact.received', $id, ['kind' => $v['kind']]);
    $_SESSION['contact_done'] = $v['email'];
    redirect('/contact/thanks');
}

function contact_thanks(): void
{
    $email = (string) ($_SESSION['contact_done'] ?? '');
    unset($_SESSION['contact_done']);
    $body = breadcrumbs([['/contact', 'お問い合わせ']])
        . '<div class="mx-auto max-w-xl px-5 py-24 text-center"><p class="eyebrow">Thank you</p>'
        . '<h1 class="mt-3 text-[1.5rem]">送信しました</h1><div class="stitch mx-auto mt-6 w-16" aria-hidden="true"></div>'
        . '<p class="mt-8 text-[0.95rem] leading-8">お問い合わせ、ありがとうございます。<br>2〜3日のうちに、メールでお返事しますけぇ。</p>'
        . ($email !== '' ? '<p class="mt-4 text-sm leading-7 text-muted">受付のメールを <strong class="font-medium text-ink">' . e($email) . '</strong> に送りました。<br>届かんときは、迷惑メールのフォルダも見てつかぁさい。</p>' : '')
        . '<div class="mt-10 flex flex-wrap justify-center gap-3">' . button_link('/shop', '作品を見る', 'outline') . button_link('/', 'トップに戻る', 'outline') . '</div></div>';
    render_page(['title' => '送信しました', 'path' => '/contact/thanks', 'noindex' => true, 'nostore' => true], $body);
}
