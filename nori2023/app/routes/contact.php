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
    session_set_cookie_params(['lifetime' => 0, 'path' => (base_path() ?: '') . '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

function route_contact(string $path): void
{
    start_contact_session();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    match (true) {
        $path === '/custom-cardcase' && $method === 'POST' => cardcase_save(),
        $path === '/custom-cardcase' => cardcase_page([]),
        $path === '/custom-cardcase/thanks' => cardcase_thanks(),
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

// ================================================================== オリジナル名刺・カードケース 注文書
const CARDCASE_LINE_URL = LINE_URL; // ロゴを送ってもらう公式LINE（@228tbemy）
const CARDCASE_USES = ['self' => 'ご自分用', 'gift' => 'プレゼント', 'other' => 'その他'];
const CARDCASE_MARKS = ['text' => '文字を入れる', 'logo' => '企業ロゴを入れる', 'both' => '文字＋企業ロゴ', 'none' => '刻印なし'];
const CARDCASE_RECEIVE = ['store' => '店頭受取', 'ship' => '配送希望', 'consult' => '相談したい'];

function cardcase_page(array $v, array $errors = []): void
{
    $val = static fn (string $k): string => e((string) ($v[$k] ?? ''));
    $in = 'mt-1 block w-full min-h-12 rounded border border-line bg-white px-3 text-base focus:border-camel focus:outline-none';
    $err = static fn (string $k): string => isset($errors[$k]) ? '<p class="mt-1 text-sm text-red-700">' . e($errors[$k]) . '</p>' : '';
    $row = static function (string $label, string $control, string $id, bool $req, string $hint = '') use ($err): string {
        return '<div><label for="' . $id . '" class="block text-sm font-medium">' . e($label) . ($req ? ' <span class="text-xs text-camel">必須</span>' : ' <span class="text-xs text-muted">任意</span>') . '</label>'
            . $control . ($hint ? '<p class="mt-1 text-xs leading-5 text-muted">' . $hint . '</p>' : '') . $err($id) . '</div>';
    };
    $choices = static function (string $name, array $opts, string $current) : string {
        $h = '';
        foreach ($opts as $k => $label) {
            $h .= '<label class="flex min-h-11 items-center gap-2 text-[0.95rem]"><input type="radio" name="' . $name . '" value="' . $k . '" class="h-5 w-5"' . ($current === $k ? ' checked' : '') . '> ' . e($label) . '</label>';
        }
        return $h;
    };
    $block = static fn (string $no, string $title, string $inner): string => '<section class="rounded-card border border-line bg-white p-5 sm:p-6">'
        . '<h3 class="flex items-center gap-3 font-serif text-lg text-leather"><span class="inline-flex h-8 w-8 items-center justify-center rounded-[2px] bg-leather text-sm text-ivory">' . $no . '</span>' . e($title) . '</h3>'
        . '<div class="mt-5 space-y-6">' . $inner . '</div></section>';

    $features = '';
    foreach ([['名前や企業ロゴを入れることができます', '名前・お店や会社のロゴを、革に刻印します。'], ['使うほどに革がなじみ、深い味わいに', 'ヌメ革は、使うほどに色が深まり、ツヤが出てきます。'], ['世界にひとつだけのオリジナル', 'おっさんがひとつひとつ手で仕立てます。']] as [$t, $d]) {
        $features .= '<li class="rounded-card border border-gold/30 p-5"><p class="font-serif text-[1.05rem] leading-7 text-ivory">' . e($t) . '</p><p class="mt-2 text-sm leading-7 text-ivory/70">' . e($d) . '</p></li>';
    }
    $steps = '';
    foreach (['この注文書を送る', 'ロゴは公式LINEへ送る', '作成イメージとお見積りを確認', 'OKをもろうたら制作開始', 'お渡し（店頭受取・配送）'] as $i => $t) {
        $steps .= '<li class="flex items-center gap-3 rounded-card border border-line bg-white px-4 py-3 text-sm"><span class="font-serif text-xl text-camel">' . ($i + 1) . '</span>' . e($t) . '</li>';
    }

    $form = '<form method="post" action="' . e(url('/custom-cardcase')) . '#order-form" enctype="multipart/form-data" class="mt-8 space-y-6" novalidate>' . csrf_field()
        . '<div class="hidden" aria-hidden="true"><label>会社のホームページ<input name="website" tabindex="-1" autocomplete="off"></label></div>'
        . '<input type="hidden" name="t" value="' . time() . '">'
        . $block('1', 'ご注文内容',
            $row('ご注文数量', '<div class="mt-1 flex items-center gap-2"><input id="qty" name="qty" type="number" min="1" max="999" inputmode="numeric" required value="' . $val('qty') . '" class="' . $in . ' !mt-0 max-w-[8rem]"><span>個</span></div>', 'qty', true)
            . '<fieldset><legend class="text-sm font-medium">ご希望用途 <span class="text-xs text-camel">必須</span></legend><div class="mt-2 flex flex-wrap gap-x-6">' . $choices('use', CARDCASE_USES, (string) ($v['use'] ?? '')) . '</div>'
            . '<input name="use_other" maxlength="50" placeholder="その他の用途（例：記念品、開店祝い）" value="' . $val('use_other') . '" class="' . $in . '" aria-label="その他の用途">' . $err('use') . '</fieldset>'
            . '<fieldset><legend class="text-sm font-medium">名入れ・刻印 <span class="text-xs text-camel">必須</span></legend><div class="mt-2 grid gap-x-6 sm:grid-cols-2">' . $choices('mark', CARDCASE_MARKS, (string) ($v['mark'] ?? '')) . '</div>' . $err('mark') . '</fieldset>'
            . $row('刻印する文字（名前・会社名など）', '<input id="engrave" name="engrave" maxlength="100" value="' . $val('engrave') . '" class="' . $in . '">', 'engrave', false, '「文字を入れる」「文字＋企業ロゴ」の方は書いてつかぁさい。<br><strong class="font-medium text-red-700">文字は12ポイント以上になる大きさで入れます。</strong>細かい文字は再現できんことがあります。')
            . $row('配置・デザインのご希望', '<textarea id="design" name="design" rows="3" maxlength="500" class="' . $in . ' py-2 leading-7">' . $val('design') . '</textarea>', 'design', false, '例：フタの真ん中に名前、裏に小さくロゴ など'))
        . $block('2', 'ご注文者・ご連絡先',
            $row('お名前・会社名', '<input id="name" name="name" required maxlength="80" autocomplete="name" value="' . $val('name') . '" class="' . $in . '">', 'name', true)
            . $row('お電話番号', '<input id="phone" name="phone" type="tel" required maxlength="15" autocomplete="tel" inputmode="tel" value="' . $val('phone') . '" class="' . $in . ' max-w-[14rem]">', 'phone', true)
            . $row('メールアドレス', '<input id="email" name="email" type="email" required maxlength="100" autocomplete="email" value="' . $val('email') . '" class="' . $in . '">', 'email', true, '作成イメージやお見積りをお送りします。携帯のメールの方は、mpr-shop.com と mpr.co.jp からのメールを受け取れるようにしてつかぁさい')
            . '<fieldset><legend class="text-sm font-medium">お受け取り方法 <span class="text-xs text-camel">必須</span></legend><div class="mt-2 flex flex-wrap gap-x-6">' . $choices('receive', CARDCASE_RECEIVE, (string) ($v['receive'] ?? '')) . '</div>' . $err('receive') . '</fieldset>'
            . $row('配送希望の場合の住所', '<textarea id="address" name="address" rows="2" maxlength="200" autocomplete="street-address" placeholder="〒731-0135 広島県広島市安佐南区…" class="' . $in . ' py-2 leading-7">' . $val('address') . '</textarea>', 'address', false, '「配送希望」の方は、郵便番号から書いてつかぁさい'))
        . $block('3', 'ロゴデータの送付・デザイン確認',
            '<div><label for="files" class="block text-sm font-medium">ロゴ・画像データ <span class="text-xs text-muted">任意</span></label>'
            . '<input id="files" name="files[]" type="file" multiple accept="' . e(CUSTOMER_FILE_ACCEPT) . '" class="mt-2 block w-full rounded border border-line bg-white p-3 text-sm file:mr-3 file:min-h-10 file:rounded-[2px] file:border-0 file:bg-leather file:px-4 file:text-ivory">'
            . '<p class="mt-1 text-xs leading-5 text-muted">ここから送れます。' . CUSTOMER_FILE_MAX_COUNT . 'つまで・1つ10MBまで（JPG・PNG・PDF・AI・EPS・SVG・PSD・ZIP）。<br>写真で撮ったロゴでも大丈夫です。</p>'
            . (isset($errors['files']) ? '<p class="mt-1 text-sm text-red-700">' . e($errors['files']) . '</p>' : '')
            . (($v['_had_files'] ?? false) && $errors ? '<p class="mt-1 text-sm text-red-700">お手数ですが、ファイルはもう一度選び直してつかぁさい。</p>' : '') . '</div>'
            . '<p class="text-[0.95rem] leading-8">大きいデータや、あとから送りたいときは、<strong class="font-medium text-leather">前川印刷株式会社の公式LINE</strong>へ送ってつかぁさい。作成イメージも公式LINEで確認していただけます。</p>'
            . '<div class="flex flex-wrap items-center gap-5"><a href="' . e(CARDCASE_LINE_URL) . '" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-12 items-center gap-2 rounded-[2px] bg-[#06C755] px-5 text-[0.95rem] font-medium text-white hover:opacity-90">' . chat_icon() . ' 公式LINEを開く</a>'
            . '<figure class="hidden text-center sm:block"><img src="' . e(url('/images/brand/line-qr.svg')) . '" alt="公式LINEのQRコード" width="116" height="116" class="h-[116px] w-[116px]"><figcaption class="mt-1 text-xs text-muted">スマホで読み取り</figcaption></figure></div>'
            . '<p class="text-xs leading-5 text-muted">LINE で送るときは、この注文書と同じお名前を書いてもらえると、照らし合わせがスムーズです。</p>')
        . $block('4', 'その他・ご相談事項',
            $row('ご相談・ご希望', '<textarea id="note" name="note" rows="4" maxlength="1500" class="' . $in . ' py-2 leading-7">' . $val('note') . '</textarea>', 'note', false, '色の好み、ほしい時期、ご予算など、なんでも書いてつかぁさい'))
        . '<div class="rounded-card border border-line bg-paper p-4 text-sm leading-6">'
        . '<p>いただいた内容は、お見積り・制作・お渡しの連絡のためだけに使います。くわしくは <a href="' . e(url('/legal/privacy')) . '" target="_blank" class="text-leather underline">プライバシーポリシー</a> を見てつかぁさい。</p>'
        . '<label class="mt-3 flex min-h-11 items-start gap-2 font-medium"><input type="checkbox" name="consent" value="1" required class="mt-1 h-5 w-5"' . (!empty($v['consent']) ? ' checked' : '') . '> プライバシーポリシーに同意します</label>' . $err('consent') . '</div>'
        . '<p class="text-sm leading-7 text-muted">送信しただけでは、注文は確定しません。内容を見て、作成イメージとお見積りをお送りします。それを見てOKをもろうてから制作に入りますけぇ、安心してつかぁさい。</p>'
        . '<button class="flex min-h-14 w-full items-center justify-center rounded-[2px] bg-leather text-[1rem] tracking-[0.15em] text-ivory hover:bg-charcoal">この内容で注文書を送る</button>'
        . '</form>';

    $body = breadcrumbs([['/custom-cardcase', 'オリジナル名刺・カードケース']])
        . '<section class="bg-ink text-ivory"><div class="mx-auto grid max-w-6xl items-center gap-10 px-5 py-14 md:grid-cols-2 md:py-20">'
        . '<div class="relative mx-auto aspect-square w-full max-w-md overflow-hidden rounded-card md:order-2"><img src="' . e(url('/images/brand/cardcase-gift2.jpg')) . '" alt="名入れ・ロゴ刻印ができる、封筒型の名刺・カードケース" data-zoom class="absolute inset-0 h-full w-full object-cover"></div>'
        . '<div><p class="eyebrow !text-gold">Custom card case</p><h1 class="mt-3 text-[1.6rem] leading-relaxed !text-ivory sm:text-[2rem]">想いを刻み、<br>長く愛される贈りもの。</h1>'
        . '<div class="mt-5 h-px w-16 bg-gold/70" aria-hidden="true"></div>'
        . '<p class="mt-6 text-[0.95rem] leading-8 text-ivory/80">使えば使うほど革がなじみ、あなただけの風合いに。<br>名前や企業ロゴを刻んだ、世界にひとつだけの名刺・カードケースです。<br>大切な人へのプレゼントにもおすすめ。</p>'
        . '<div class="mt-8">' . button_link('/custom-cardcase#order-form', '注文書を書く', 'gold') . '</div></div></div>'
        . '<div class="mx-auto max-w-6xl px-5 pb-14"><ul class="grid gap-4 sm:grid-cols-3">' . $features . '</ul></div></section>'
        . '<section class="mx-auto max-w-6xl px-5 py-16"><div class="grid items-center gap-10 md:grid-cols-[2fr_3fr]">'
        . '<div class="relative mx-auto aspect-square w-full max-w-sm overflow-hidden rounded-card"><img src="' . e(url('/images/brand/cardcase-open.jpeg')) . '" alt="名刺を入れたカードケースの中" loading="lazy" data-zoom class="absolute inset-0 h-full w-full object-cover"></div>'
        . '<div>' . section_heading('ご注文の流れ', 'How to order', null, 'left') . '<ol class="mt-6 space-y-2">' . $steps . '</ol>'
        . '<p class="mt-5 text-sm leading-7 text-muted">お値段は、数量や刻印の内容で変わるけぇ、お見積りでお知らせします。<br>ロゴのデータがない方や、どうしたらええか迷う方は「相談したい」で送ってつかぁさい。</p></div></div></section>'
        . '<section id="order-form" class="scroll-mt-20 bg-paper"><div class="mx-auto max-w-2xl px-5 py-16">'
        . '<p class="eyebrow text-center">Order form</p><h2 class="mt-2 text-center text-[1.4rem]">オリジナル 名刺・カードケース 注文書</h2>'
        . '<p class="mt-2 text-center text-sm text-muted">名前や企業ロゴを刻んだ、世界にひとつのレザーアイテム</p>'
        . ($errors ? '<p class="mt-6 rounded border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">' . e($errors['_form'] ?? '入力の足りんところがあります。赤い字のところを見てつかぁさい。') . '</p>' : '')
        . $form
        . '<p class="mt-10 text-center text-xs leading-6 text-muted">販売者：' . e(SELLER_INFO['company']) . '　／　製造者：Nori2023<br>お電話：' . e(SELLER_INFO['tel']) . '（' . e((string) SELLER_INFO['tel_hours']) . '）</p>'
        . '</div></section>';
    render_page([
        'title' => 'オリジナル名刺・カードケース（名入れ・ロゴ刻印）',
        'path' => '/custom-cardcase',
        'description' => '名前や企業ロゴを刻んだ、世界にひとつのヌメ革の名刺・カードケース。プレゼントや記念品にも。注文書からご相談・お見積りを受け付けています。',
        'image' => '/images/brand/cardcase-gift2.jpg',
        'nostore' => true,
    ], $body, $errors ? 422 : 200);
}

function cardcase_save(): void
{
    // ファイルが大きすぎてサーバーが受け取りを止めたとき（フォームの中身が空になる）
    if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        cardcase_page([], ['files' => 'ファイルが大きすぎて受け取れませんでした。1つ10MBまでにするか、公式LINEで送ってつかぁさい。', '_form' => 'ファイルが大きすぎたため、送信できませんでした。お手数ですが、もう一度入力してつかぁさい。']);
        return;
    }
    verify_csrf();
    $v = [];
    foreach (['qty' => 5, 'use' => 10, 'use_other' => 50, 'mark' => 10, 'engrave' => 100, 'design' => 500, 'name' => 80, 'phone' => 20, 'email' => 100, 'receive' => 10, 'address' => 200, 'note' => 1500] as $k => $max) {
        $v[$k] = post_str($k, $max);
    }
    $v['consent'] = !empty($_POST['consent']);
    if (post_str('website', 200) !== '' || time() - (int) ($_POST['t'] ?? 0) < 3) {
        redirect('/custom-cardcase/thanks');
    }
    $errors = [];
    $qty = (int) mb_convert_kana($v['qty'], 'n');
    if ($qty < 1 || $qty > 999) {
        $errors['qty'] = 'ご注文数量を、1〜999の数字で入れてつかぁさい。';
    }
    if (!isset(CARDCASE_USES[$v['use']])) {
        $errors['use'] = 'ご希望用途を選んでつかぁさい。';
    }
    if (!isset(CARDCASE_MARKS[$v['mark']])) {
        $errors['mark'] = '名入れ・刻印の内容を選んでつかぁさい。';
    } elseif (in_array($v['mark'], ['text', 'both'], true) && $v['engrave'] === '') {
        $errors['engrave'] = '刻印する文字を書いてつかぁさい。';
    }
    if ($v['name'] === '') {
        $errors['name'] = 'お名前・会社名を入れてつかぁさい。';
    }
    $phone = preg_replace('/[^0-9]/', '', mb_convert_kana($v['phone'], 'n'));
    if (strlen($phone) < 10 || strlen($phone) > 11) {
        $errors['phone'] = '電話番号を10〜11桁の数字で入れてつかぁさい。';
    }
    if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'メールアドレスを正しく入れてつかぁさい。';
    }
    if (!isset(CARDCASE_RECEIVE[$v['receive']])) {
        $errors['receive'] = 'お受け取り方法を選んでつかぁさい。';
    } elseif ($v['receive'] === 'ship' && mb_strlen($v['address']) < 8) {
        $errors['address'] = '配送先の住所を書いてつかぁさい。';
    }
    if (!$v['consent']) {
        $errors['consent'] = 'プライバシーポリシーへの同意にチェックを入れてつかぁさい。';
    }
    $up = customer_files_check('files');
    if ($up['error'] !== '') {
        $errors['files'] = $up['error'];
    }
    $v['_had_files'] = $up['files'] !== [] || $up['error'] !== '';
    $recent = (int) db_value("SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND email = 'contact-form' AND attempted_at > (NOW() - INTERVAL 1 HOUR)", [client_ip()]);
    if ($recent >= CONTACT_LIMIT_PER_HOUR) {
        $errors['_form'] = '短い時間にたくさん送られたので、いったん止めています。時間をおいてもう一度送るか、お電話でご連絡ください。';
    }
    if ($errors) {
        cardcase_page($v, $errors);
        return;
    }

    $use = CARDCASE_USES[$v['use']] . ($v['use_other'] !== '' ? '（' . $v['use_other'] . '）' : '');
    $summary = "【1】ご注文内容\n"
        . "■ご注文数量：{$qty} 個\n■ご希望用途：{$use}\n■名入れ・刻印：" . CARDCASE_MARKS[$v['mark']] . "\n"
        . ($v['engrave'] !== '' ? "■刻印する文字：{$v['engrave']}\n" : '')
        . ($v['design'] !== '' ? "■配置・デザインのご希望：\n{$v['design']}\n" : '')
        . "\n【2】ご注文者・ご連絡先\n■お名前・会社名：{$v['name']} 様\n■お電話：{$phone}\n■メール：{$v['email']}\n■お受け取り方法：" . CARDCASE_RECEIVE[$v['receive']] . "\n"
        . ($v['address'] !== '' ? "■配送先：{$v['address']}\n" : '')
        . "\n【3】ロゴデータ：" . ($up['files'] ? "フォームで受け取り\n" . implode('', array_map(static fn ($f) => '・' . $f['name'] . '（' . customer_file_size_text($f['size']) . "）\n", $up['files'])) : "公式LINEで受け取り（" . CARDCASE_LINE_URL . "）\n")
        . ($v['note'] !== '' ? "\n【4】その他・ご相談事項\n{$v['note']}\n" : '');
    $id = uuid();
    db_exec('INSERT INTO contact_messages (id, kind, name, email, phone, subject, body) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$id, 'custom_request', $v['name'], $v['email'], $phone, mb_substr('オリジナル名刺・カードケース注文書（' . $qty . '個・' . CARDCASE_MARKS[$v['mark']] . '）', 0, 200), $summary]);
    db_exec("INSERT INTO login_attempts (ip, email, succeeded) VALUES (?, 'contact-form', 1)", [client_ip()]);
    $savedFiles = customer_files_store($id, $up['files']);
    if ($savedFiles < count($up['files'])) {
        error_log('[nori2023 cardcase] saved ' . $savedFiles . ' of ' . count($up['files']) . ' files for ' . $id);
    }

    send_mail((string) SELLER_INFO['email'], '【名刺・カードケース注文書】' . $v['name'] . ' 様（' . $qty . '個）',
        "オリジナル名刺・カードケースの注文書が届きました。\nこのメールに返信すると、お客さんに届きます。\n"
        . ($savedFiles ? "添付ファイルが{$savedFiles}件あります。管理画面からダウンロードしてください。\n\n" : "ロゴは公式LINEに届いていないか確認してください。\n\n") . $summary
        . "\n管理画面：" . absolute_url('/admin/inquiries/' . $id) . "\n", $v['email']);
    send_mail($v['email'], '【' . SITE_NAME . '】オリジナル名刺・カードケースの注文書を受け付けました',
        "{$v['name']} 様\n\nオリジナル名刺・カードケースの注文書を送っていただき、ありがとうございます。\n「" . SITE_NAME . "」です。\n\n"
        . "次の内容で受け付けました。まだご注文は確定していません。\n内容を確認して、作成イメージとお見積りをお送りしますので、少しお待ちください。\n\n"
        . (in_array($v['mark'], ['logo', 'both'], true) && !$up['files'] ? "▼企業ロゴ・画像データは、前川印刷株式会社の公式LINEへお送りください。\n" . CARDCASE_LINE_URL . "\n（注文書と同じお名前を書いていただくと、照らし合わせがスムーズです）\n\n" : '')
        . "------------------------------\n" . $summary . "------------------------------\n\n"
        . "※文字は12ポイント以上の大きさで刻印します。細かい文字は再現できない場合があります。\n"
        . "※このメールは送信専用のアドレスから自動でお送りしています。このメールへの返信は届きませんので、ご了承ください。\n\n"
        . SITE_NAME . "\n販売者：" . SELLER_INFO['company'] . " ／ 製造者：Nori2023\nTEL " . SELLER_INFO['tel'] . "（" . SELLER_INFO['tel_hours'] . "）\n" . absolute_url('/') . "\n");
    audit('cardcase.order_form', $id, ['qty' => $qty, 'mark' => $v['mark']]);
    $_SESSION['contact_done'] = $v['email'];
    $_SESSION['cardcase_logo'] = in_array($v['mark'], ['logo', 'both'], true) && !$up['files'];
    redirect('/custom-cardcase/thanks');
}

function cardcase_thanks(): void
{
    $email = (string) ($_SESSION['contact_done'] ?? '');
    $logo = !empty($_SESSION['cardcase_logo']);
    unset($_SESSION['contact_done'], $_SESSION['cardcase_logo']);
    $body = breadcrumbs([['/custom-cardcase', 'オリジナル名刺・カードケース']])
        . '<div class="mx-auto max-w-xl px-5 py-20 text-center"><p class="eyebrow">Thank you</p>'
        . '<h1 class="mt-3 text-[1.5rem]">注文書を受け付けました</h1><div class="stitch mx-auto mt-6 w-16" aria-hidden="true"></div>'
        . '<p class="mt-8 text-[0.95rem] leading-8">ありがとうございます。<br>内容を見て、作成イメージとお見積りをお送りしますけぇ、ちいと待っとってつかぁさい。<br><span class="text-sm text-muted">（まだご注文は確定していません）</span></p>'
        . ($logo ? '<div class="mt-8 rounded-card border border-line bg-white p-5 text-left text-sm leading-7"><p class="font-medium text-leather">ロゴ・画像データを送ってつかぁさい</p><p class="mt-1">前川印刷株式会社の公式LINEへ、注文書と同じお名前を添えて送ってください。</p>'
            . '<a href="' . e(CARDCASE_LINE_URL) . '" target="_blank" rel="noopener noreferrer" class="mt-3 inline-flex min-h-12 items-center gap-2 rounded-[2px] bg-[#06C755] px-5 font-medium text-white hover:opacity-90">' . chat_icon() . ' 公式LINEを開く</a></div>' : '')
        . ($email !== '' ? '<p class="mt-6 text-sm leading-7 text-muted">受付のメールを <strong class="font-medium text-leather">' . e($email) . '</strong> に送りました。<br>届かんときは、迷惑メールのフォルダも見てつかぁさい。</p>' : '')
        . '<div class="mt-10 flex flex-wrap justify-center gap-3">' . button_link('/shop', '作品を見る', 'outline') . button_link('/', 'トップに戻る', 'outline') . '</div></div>';
    render_page(['title' => '注文書を受け付けました', 'path' => '/custom-cardcase/thanks', 'noindex' => true, 'nostore' => true], $body);
}
