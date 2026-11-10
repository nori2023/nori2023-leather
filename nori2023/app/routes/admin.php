<?php
declare(strict_types=1);

require APP_DIR . '/views/admin_layout.php';
require APP_DIR . '/routes/admin_content.php';

function route_admin(string $path): void
{
    start_admin_session();
    db_migrate();

    if ($path === '/admin/login') {
        admin_login();
        return;
    }
    if (!current_admin()) {
        if (is_post() || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            json_response(['error' => 'ログインが切れました。ページを開き直してください。'], 401);
        }
        redirect('/admin/login');
    }
    if (is_post()) {
        verify_csrf();
    }

    $post = is_post();
    $m = [];
    match (true) {
        $path === '/admin/logout' && $post => admin_logout(),
        $path === '/admin' => admin_dashboard(),
        $path === '/admin/products' && !$post => admin_products(),
        $path === '/admin/products/new' => admin_product_form(null),
        $path === '/admin/products' && $post => admin_product_save(null),
        (bool) preg_match('#^/admin/products/([0-9a-f-]{36})$#', $path, $m) => $post ? admin_product_save($m[1]) : admin_product_edit($m[1]),
        (bool) preg_match('#^/admin/products/([0-9a-f-]{36})/delete$#', $path, $m) && $post => admin_product_delete($m[1]),
        (bool) preg_match('#^/admin/products/([0-9a-f-]{36})/images$#', $path, $m) && $post => admin_image_upload($m[1]),
        (bool) preg_match('#^/admin/products/([0-9a-f-]{36})/images/order$#', $path, $m) && $post => admin_image_order($m[1]),
        (bool) preg_match('#^/admin/images/([0-9a-f-]{36})/(main|alt|delete)$#', $path, $m) && $post => admin_image_action($m[1], $m[2]),
        $path === '/admin/categories' && !$post => admin_categories(),
        (bool) preg_match('#^/admin/categories(?:/([0-9a-z-]{1,36}))?$#', $path, $m) && $post => admin_category_save($m[1] ?? null),
        $path === '/admin/shipping' && !$post => admin_shipping(),
        (bool) preg_match('#^/admin/shipping(?:/([0-9a-f-]{36}))?$#', $path, $m) && $post => admin_shipping_save($m[1] ?? null),
        $path === '/admin/settings' => $post ? admin_settings_save() : admin_settings(),
        $path === '/admin/settings/hero-image' && $post => admin_hero_upload(),
        $path === '/admin/settings/hero-image/reset' && $post => admin_hero_reset(),
        $path === '/admin/workshops' => $post ? admin_workshop_save(null) : admin_workshops(),
        $path === '/admin/workshops/new' => admin_workshop_form(null),
        (bool) preg_match('#^/admin/workshops/([0-9a-f-]{36})$#', $path, $m) => $post ? admin_workshop_save($m[1]) : admin_workshop_form($m[1]),
        (bool) preg_match('#^/admin/workshops/([0-9a-f-]{36})/delete$#', $path, $m) && $post => admin_workshop_delete($m[1]),
        $path === '/admin/aging' => $post ? admin_aging_save(null) : admin_aging(),
        $path === '/admin/aging/new' => admin_aging_form(null),
        (bool) preg_match('#^/admin/aging/([0-9a-f-]{36})$#', $path, $m) => $post ? admin_aging_save($m[1]) : admin_aging_form($m[1]),
        (bool) preg_match('#^/admin/aging/([0-9a-f-]{36})/delete$#', $path, $m) && $post => admin_aging_delete($m[1]),
        $path === '/admin/instagram' => admin_instagram(),
        $path === '/admin/instagram/token' && $post => admin_instagram_token(),
        $path === '/admin/instagram/sync' && $post => admin_instagram_sync(),
        $path === '/admin/instagram/posts' && $post => admin_instagram_post_save(),
        (bool) preg_match('#^/admin/instagram/posts/(m_[0-9a-f]{16}|[0-9A-Za-z_]{1,50})/delete$#', $path, $m) && $post => admin_instagram_post_delete($m[1]),
        $path === '/admin/instagram/token/delete' && $post => admin_instagram_token_delete(),
        $path === '/admin/logs' => admin_logs(),
        $path === '/admin/account' => $post ? admin_account_save() : admin_account(),
        default => admin_not_found(),
    };
}

function admin_not_found(): void
{
    http_response_code(404);
    render_admin('ページが見つかりません', '<a class="' . A_BTN_GHOST . '" href="' . e(url('/admin')) . '">ダッシュボードへ</a>');
}

// ================================================================== ログイン
function admin_login(): void
{
    if (current_admin()) {
        redirect('/admin');
    }
    $error = null;
    $email = '';
    if (is_post()) {
        verify_csrf();
        $email = post_str('email', 255);
        if (login_locked(client_ip(), $email)) {
            $error = 'ログインの失敗が続いたため、しばらくログインできません。15分ほど待ってからお試しください。';
        } elseif (attempt_login($email, (string) ($_POST['password'] ?? ''))) {
            redirect('/admin');
        } else {
            $error = 'メールアドレスかパスワードが違います。';
        }
    }
    $installed = query_str('installed', 2) === '1'
        ? '<p class="mb-4 rounded border border-green-700/30 bg-green-50 px-4 py-3 text-sm text-green-900">初回設定が完了しました。登録したメールアドレスとパスワードでログインしてください。</p>'
        : '';
    $body = '<div class="mx-auto max-w-sm">' . $installed . '<form method="post" data-autofill class="' . A_CARD . ' space-y-4">' . csrf_field()
        . admin_field('メールアドレス', '<input id="email" name="email" type="email" required autocomplete="username" value="' . e($email) . '" class="' . A_INPUT . '">', 'email')
        . admin_field('パスワード', '<input id="password" name="password" type="password" required autocomplete="current-password" class="' . A_INPUT . '">', 'password')
        . '<button class="' . A_BTN_PRIMARY . ' w-full">ログイン</button></form></div>';
    render_admin('ログイン', $body, $error, true);
}

function admin_logout(): void
{
    logout_admin();
    redirect('/admin/login');
}

// ================================================================== ダッシュボード
function admin_dashboard(): void
{
    $count = fn (string $sql) => (int) db_value($sql);
    $tiles = [
        ['販売中の商品', $count("SELECT COUNT(*) FROM products WHERE status IN ('on_sale','reserved')"), '/admin/products?status=on_sale'],
        ['SOLD OUT', $count("SELECT COUNT(*) FROM products WHERE status = 'sold_out'"), '/admin/products?status=sold_out'],
        ['非公開（下書き）', $count("SELECT COUNT(*) FROM products WHERE status = 'draft'"), '/admin/products?status=draft'],
        ['新規注文', $count("SELECT COUNT(*) FROM orders WHERE status = 'received'"), null],
        ['決済待ち', $count("SELECT COUNT(*) FROM orders WHERE status = 'awaiting_payment'"), null],
        ['未発送', $count("SELECT COUNT(*) FROM orders WHERE status IN ('paid','in_production','completed')"), null],
        ['名刺入れ受注', $count("SELECT COUNT(*) FROM orders WHERE order_type = 'custom' AND status NOT IN ('shipped','cancelled')"), null],
        ['ワークショップ申込', $count("SELECT COUNT(*) FROM workshop_reservations WHERE status = 'confirmed'"), null],
    ];
    $h = '<ul class="grid grid-cols-2 gap-3 sm:grid-cols-4">';
    foreach ($tiles as [$t, $v, $href]) {
        $inner = '<p class="text-xs text-muted">' . e($t) . '</p><p class="mt-1 font-serif text-3xl text-leather">' . $v . '</p>';
        $h .= '<li>' . ($href ? '<a href="' . e(url($href)) . '" class="' . A_CARD . ' block hover:border-camel">' . $inner . '</a>' : '<div class="' . A_CARD . '">' . $inner . '</div>') . '</li>';
    }
    $h .= '</ul><div class="mt-6 flex flex-wrap gap-3"><a href="' . e(url('/admin/products/new')) . '" class="' . A_BTN_PRIMARY . '">＋ 完成品を登録する</a>'
        . '<a href="' . e(url('/admin/settings')) . '" class="' . A_BTN_GHOST . '">ページ設定</a></div>'
        . '<p class="mt-8 rounded border border-amber-300 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900">オンライン購入（STEP 3）は未実装のため、販売中の商品も購入ボタンは押せません。注文・受注制作・ワークショップの件数は、各機能の完成後に表示されます。</p>';
    render_admin('ダッシュボード', $h);
}

// ================================================================== 完成品
function admin_products(): void
{
    $status = query_str('status', 20) ?: null;
    $q = query_str('q', 100) ?: null;
    $view = query_str('view', 10) === 'photo' ? 'photo' : 'list';
    $sort = in_array(query_str('sort', 12), ['code', 'code_desc', 'updated'], true) ? query_str('sort', 12) : ($view === 'list' ? 'code' : 'updated');
    $products = list_all_products($status, $q, $sort);
    $cs = product_code_status();
    $qs = static fn (array $over): string => url('/admin/products') . '?' . http_build_query(array_filter(array_merge(['status' => $status, 'q' => $q, 'view' => $view, 'sort' => $sort], $over), static fn ($v) => $v !== null && $v !== ''));

    $opts = '<option value="">すべての状態</option>';
    foreach (STATUS_LABEL as $k => $label) {
        $opts .= '<option value="' . e($k) . '"' . ($k === $status ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    $h = '';
    if ($cs['last']) {
        $h .= '<div class="' . A_CARD . ' mb-4 grid grid-cols-3 gap-2 text-center text-sm">'
            . '<div><p class="text-xs text-muted">登録数</p><p class="mt-1 text-lg font-medium">' . e((string) $cs['count']) . '件</p></div>'
            . '<div><p class="text-xs text-muted">最後の品番</p><p class="mt-1 whitespace-nowrap font-mono text-[0.78rem] sm:text-base">' . e($cs['last']) . '</p></div>'
            . '<div><p class="text-xs text-muted">次の品番</p><p class="mt-1 whitespace-nowrap font-mono text-[0.78rem] text-camel sm:text-base">' . e($cs['next']) . '</p></div></div>';
    }
    $h .= '<div class="mb-4 flex flex-wrap items-end gap-3"><a href="' . e(url('/admin/products/new')) . '" class="' . A_BTN_PRIMARY . '">＋ 新しく登録</a>'
        . '<form method="get" class="flex flex-1 flex-wrap items-end gap-2"><input type="hidden" name="view" value="' . e($view) . '"><input type="hidden" name="sort" value="' . e($sort) . '">'
        . '<select name="status" class="' . A_INPUT . ' !mt-0 !w-auto" aria-label="販売状態で絞り込み">' . $opts . '</select>'
        . '<input name="q" value="' . e($q ?? '') . '" placeholder="商品名・品番" class="' . A_INPUT . ' !mt-0 !w-auto flex-1" aria-label="検索">'
        . '<button class="' . A_BTN_GHOST . '">絞り込む</button></form></div>';
    $tab = static fn (string $label, bool $on, string $href): string => '<a href="' . e($href) . '" class="inline-flex min-h-10 items-center rounded-full border px-4 text-sm '
        . ($on ? 'border-leather bg-leather text-ivory' : 'border-line bg-white') . '"' . ($on ? ' aria-current="page"' : '') . '>' . e($label) . '</a>';
    $h .= '<div class="mb-3 flex flex-wrap items-center justify-between gap-2"><div class="flex gap-2">'
        . $tab('リスト', $view === 'list', $qs(['view' => 'list', 'sort' => 'code'])) . $tab('写真で見る', $view === 'photo', $qs(['view' => 'photo', 'sort' => 'updated'])) . '</div>'
        . '<div class="flex gap-2 text-sm"><span class="self-center text-muted">並び：</span>'
        . $tab('品番 ↑', $sort === 'code', $qs(['sort' => 'code'])) . $tab('品番 ↓', $sort === 'code_desc', $qs(['sort' => 'code_desc'])) . $tab('更新順', $sort === 'updated', $qs(['sort' => 'updated'])) . '</div></div>';

    if (!$products) {
        $h .= '<p class="' . A_CARD . ' text-center text-muted">' . ($q || $status ? '条件に合う商品はありません。' : '商品はまだありません。「＋ 新しく登録」から登録してください。') . '</p>';
    } elseif ($view === 'list') {
        $h .= '<div class="overflow-x-auto rounded-lg border border-line bg-white"><table class="w-full min-w-[34rem] text-sm"><thead class="bg-paper text-left text-xs text-muted"><tr>'
            . '<th class="px-3 py-2 font-medium">品番</th><th class="px-3 py-2 font-medium">商品名</th><th class="px-3 py-2 font-medium">状態</th>'
            . '<th class="px-3 py-2 text-right font-medium">価格</th><th class="px-3 py-2 text-right font-medium">写真</th><th class="px-3 py-2 font-medium">登録日</th></tr></thead><tbody class="divide-y divide-line">';
        foreach ($products as $p) {
            $link = e(url('/admin/products/' . $p['id']));
            $h .= '<tr class="hover:bg-paper"><td class="whitespace-nowrap px-3 py-2.5 font-mono"><a href="' . $link . '" class="text-leather underline underline-offset-2">' . e($p['code']) . '</a></td>'
                . '<td class="px-3 py-2.5"><a href="' . $link . '">' . e($p['name']) . '</a></td>'
                . '<td class="whitespace-nowrap px-3 py-2.5">' . status_badge($p['status']) . '</td>'
                . '<td class="whitespace-nowrap px-3 py-2.5 text-right">' . e(format_price($p['price'])) . '</td>'
                . '<td class="px-3 py-2.5 text-right' . (count($p['images']) ? '' : ' text-red-700') . '">' . count($p['images']) . '枚</td>'
                . '<td class="whitespace-nowrap px-3 py-2.5 text-muted">' . e(format_date($p['created_at'])) . '</td></tr>';
        }
        $h .= '</tbody></table></div><p class="mt-2 text-xs text-muted">表は横にスクロールできます。品番か商品名を押すと編集画面が開きます。</p>';
    } else {
        $h .= '<ul class="space-y-2">';
        foreach ($products as $p) {
            $thumb = $p['images'] ? '<img src="' . e(upload_url($p['images'][0]['key_small'])) . '" alt="" class="h-full w-full object-cover" loading="lazy">' : '';
            $h .= '<li><a href="' . e(url('/admin/products/' . $p['id'])) . '" class="' . A_CARD . ' flex items-center gap-3 !p-3 hover:border-camel">'
                . '<div class="relative h-16 w-16 shrink-0 overflow-hidden rounded bg-sand">' . $thumb . '</div>'
                . '<div class="min-w-0 flex-1"><p class="truncate font-medium">' . e($p['name']) . '</p>'
                . '<p class="text-xs text-muted">' . e($p['code']) . '　' . e(format_price($p['price'])) . '　写真' . count($p['images']) . '枚</p></div>'
                . status_badge($p['status']) . '</a></li>';
        }
        $h .= '</ul>';
    }
    render_admin('完成品（1点もの）', $h);
}

const PRODUCT_FIELDS = ['code' => 40, 'name' => 120, 'slug' => 80, 'category_id' => 36, 'description' => 5000, 'material' => 200,
    'size' => 200, 'color' => 100, 'price' => 12, 'shipping_profile_id' => 36, 'status' => 20, 'sold_note' => 300];

function admin_product_edit(string $id): void
{
    $p = get_product($id);
    $p ? admin_product_form($p) : admin_not_found();
}

function admin_product_form(?array $product, ?array $values = null, ?string $error = null): void
{
    $categories = list_categories();
    $shipping = list_shipping_profiles();
    $isNew = $product === null;
    $v = $values ?? ($product ?: ['status' => 'draft', 'code' => product_code_status()['next'] ?? '']);
    if (($v['status'] ?? '') === 'reserved') {
        $v['status'] = 'on_sale';
    }
    $val = fn (string $k) => e((string) ($v[$k] ?? ''));
    $h = '';
    if (!$isNew) {
        $h .= '<div class="mb-4 flex flex-wrap items-center gap-3 text-sm">' . status_badge($product['status']);
        if (in_array($product['status'], PUBLIC_STATUSES, true)) {
            $h .= '<a href="' . e(url('/shop/' . $product['slug'])) . '" target="_blank" class="text-leather underline">公開ページを見る ↗</a>';
        }
        $h .= '<span class="text-muted">最終更新 ' . e(format_datetime($product['updated_at'])) . '</span></div>';
        if ($product['status'] === 'reserved') {
            $h .= '<p class="mb-4 rounded border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">この商品は購入手続き中です。決済の結果が出るまで、販売状態は変更できません。</p>';
        }
        $h .= image_manager($product);
    }
    $catOpts = '<option value="">（未設定）</option>';
    foreach ($categories as $c) {
        $catOpts .= '<option value="' . e($c['id']) . '"' . ($c['id'] === ($v['category_id'] ?? null) ? ' selected' : '') . '>' . e($c['name']) . '</option>';
    }
    $shipOpts = '<option value="">（未設定：購入手続きのときにお知らせ）</option>';
    foreach ($shipping as $s) {
        $shipOpts .= '<option value="' . e($s['id']) . '"' . ($s['id'] === ($v['shipping_profile_id'] ?? null) ? ' selected' : '') . '>'
            . e($s['name'] . ($s['fee'] !== null ? '（' . format_price((int) $s['fee']) . '）' : '（金額未定）') . ($s['is_active'] ? '' : '【無効】')) . '</option>';
    }
    $statusOpts = '';
    foreach (['draft' => '非公開（下書き）', 'on_sale' => '販売中（公開）', 'sold_out' => 'SOLD OUT（作品集に掲載）', 'suspended' => '販売停止（非公開）'] as $k => $label) {
        $statusOpts .= '<option value="' . $k . '"' . (($v['status'] ?? 'draft') === $k ? ' selected' : '') . '>' . $label . '</option>';
    }
    $photoWarn = (!$isNew && !photo_count_ok(count($product['images'])))
        ? '<p class="mt-1 text-xs text-red-700">' . e(PHOTO_RULE_TEXT) . '（現在' . count($product['images']) . '枚）</p>' : '';
    $action = $isNew ? url('/admin/products') : url('/admin/products/' . $product['id']);

    $h .= '<form method="post" action="' . e($action) . '" autocomplete="off" class="' . A_CARD . ' mt-5 grid gap-5 sm:grid-cols-2">' . csrf_field()
        . '<h2 class="font-serif text-lg text-leather sm:col-span-2">商品情報</h2>'
        . admin_field('商品管理番号 *', '<input id="code" name="code" required maxlength="40" value="' . $val('code') . '" class="' . A_INPUT . '">', 'code', '例：KH-0001（ほかの商品と重複しない番号）')
        . admin_field('商品名 *', '<input id="name" name="name" required maxlength="120" value="' . $val('name') . '" class="' . A_INPUT . '">', 'name')
        . admin_field('カテゴリー', '<select id="category_id" name="category_id" class="' . A_INPUT . '">' . $catOpts . '</select>', 'category_id')
        . admin_field('税込販売価格（円） *', '<input id="price" name="price" type="number" inputmode="numeric" min="0" step="1" required value="' . $val('price') . '" class="' . A_INPUT . '">', 'price')
        . '<div class="sm:col-span-2">' . admin_field('説明文', '<textarea id="description" name="description" rows="7" class="' . A_INPUT . '">' . $val('description') . '</textarea>', 'description', '改行はそのまま表示されます') . '</div>'
        . admin_field('使用素材', '<input id="material" name="material" value="' . $val('material') . '" placeholder="例：ヌメ革（栃木レザー）" class="' . A_INPUT . '">', 'material')
        . admin_field('サイズ', '<input id="size" name="size" value="' . $val('size') . '" placeholder="例：約 縦7cm × 横9cm" class="' . A_INPUT . '">', 'size')
        . admin_field('革の色', '<input id="color" name="color" value="' . $val('color') . '" placeholder="例：ナチュラル" class="' . A_INPUT . '">', 'color')
        . admin_field('送料設定', '<select id="shipping_profile_id" name="shipping_profile_id" class="' . A_INPUT . '">' . $shipOpts . '</select>', 'shipping_profile_id', '送料は「送料設定」で登録します')
        . admin_field('URL用の名前', '<input id="slug" name="slug" value="' . $val('slug') . '" placeholder="例：kh-0001" class="' . A_INPUT . '">', 'slug', '空欄なら商品管理番号から自動作成。公開後は変更しないでください')
        . admin_field('販売状態', '<select id="status" name="status" class="' . A_INPUT . '"' . (($product['status'] ?? '') === 'reserved' ? ' disabled' : '') . '>' . $statusOpts . '</select>' . $photoWarn, 'status')
        . '<div class="sm:col-span-2">' . admin_field('販売終了後のコメント', '<textarea id="sold_note" name="sold_note" rows="3" maxlength="300" class="' . A_INPUT . '">' . $val('sold_note') . '</textarea>', 'sold_note',
            'SOLD OUT のときだけ、商品ページの「おっさんからひとこと」欄と作品集に表示されます。例：「大事に使うてもらえたら、うれしいです。」お客様のお名前や地域を載せる場合は、ご本人の了承をとってください（300文字まで）') . '</div>'
        . ($error ? '<p role="alert" class="rounded border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-800 sm:col-span-2">' . e($error) . '</p>' : '')
        . '<div class="flex flex-wrap gap-3 sm:col-span-2"><button class="' . A_BTN_PRIMARY . '">' . ($isNew ? '登録して写真の追加へ' : '保存する') . '</button>'
        . '<a href="' . e(url('/admin/products')) . '" class="' . A_BTN_GHOST . '">一覧へ戻る</a></div></form>';

    if (!$isNew && in_array($product['status'], ['draft', 'suspended'], true) && !$product['sold_at']) {
        $h .= '<form method="post" action="' . e(url('/admin/products/' . $product['id'] . '/delete')) . '" class="mt-6 text-right" data-confirm="この商品と写真を完全に削除します。よろしいですか？">'
            . csrf_field() . '<button class="' . A_BTN_DANGER . '">この商品を削除</button></form>';
    }
    render_admin($isNew ? '完成品を登録' : '編集：' . $product['name'], $h);
}

function image_manager(array $p): string
{
    $n = count($p['images']);
    $ok = photo_count_ok($n);
    $h = '<section class="' . A_CARD . '" data-image-manager data-product-id="' . e($p['id']) . '" aria-labelledby="img-heading">'
        . '<div class="flex flex-wrap items-center justify-between gap-2"><h2 id="img-heading" class="font-serif text-lg text-leather">写真（' . $n . '枚）</h2>'
        . '<p class="text-xs text-muted">1枚目（★）が一覧に表示されます。スマホで撮った写真をそのまま選べます</p></div>'
        . '<p class="mt-2 rounded px-3 py-2 text-xs ' . ($ok ? 'bg-green-50 text-green-900' : 'bg-amber-50 text-amber-900') . '">'
        . e($ok ? "写真の枚数OK（{$n}枚）：販売中にできます" : PHOTO_RULE_TEXT . "。いま{$n}枚です") . '</p>'
        . '<label data-dropzone class="mt-4 flex min-h-28 cursor-pointer flex-col items-center justify-center gap-1 rounded border-2 border-dashed border-line bg-paper px-4 py-6 text-center text-sm text-muted hover:border-camel">'
        . '<span class="text-base text-leather">＋ 写真を追加</span><span>タップして選ぶ／ここにドラッグ（複数可）</span>'
        . '<input type="file" accept="image/*" multiple class="sr-only" data-image-input></label>'
        . '<p data-upload-status class="mt-2 text-sm text-muted" aria-live="polite"></p>';
    if ($n) {
        $h .= '<ul class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4" data-image-list>';
        foreach ($p['images'] as $i => $img) {
            $h .= '<li class="rounded border border-line bg-white p-2" data-image-id="' . e($img['id']) . '"><div class="relative aspect-square overflow-hidden rounded bg-sand">'
                . '<img src="' . e(upload_url($img['key_small'])) . '" alt="' . e($img['alt']) . '" class="h-full w-full object-cover" loading="lazy">'
                . ($img['is_main'] ? '<span class="absolute left-1 top-1 rounded bg-gold px-1.5 text-xs text-ink">★ メイン</span>' : '') . '</div>'
                . '<input value="' . e($img['alt']) . '" placeholder="写真の説明（代替テキスト）" aria-label="写真の説明（代替テキスト）" data-image-alt class="mt-2 w-full rounded border border-line px-2 py-1.5 text-[16px] sm:text-sm">'
                . '<div class="mt-2 grid grid-cols-4 gap-1 text-sm">'
                . '<button type="button" data-image-move="-1"' . ($i === 0 ? ' disabled' : '') . ' class="min-h-10 rounded border border-line disabled:opacity-30" aria-label="前へ">←</button>'
                . '<button type="button" data-image-move="1"' . ($i === $n - 1 ? ' disabled' : '') . ' class="min-h-10 rounded border border-line disabled:opacity-30" aria-label="後ろへ">→</button>'
                . '<button type="button" data-image-main' . ($img['is_main'] ? ' disabled' : '') . ' class="min-h-10 rounded border border-line disabled:opacity-30" aria-label="メインにする">★</button>'
                . '<button type="button" data-image-delete class="min-h-10 rounded border border-red-200 text-red-700" aria-label="削除">✕</button></div></li>';
        }
        $h .= '</ul>';
    }
    return $h . '</section>';
}

function read_product_post(): array
{
    $v = [];
    foreach (PRODUCT_FIELDS as $k => $max) {
        $v[$k] = post_str($k, $max);
    }
    if ($v['status'] === '') {
        $v['status'] = 'draft';
    }
    return $v;
}

function validate_product(array $v): ?string
{
    if ($v['code'] === '') {
        return '商品管理番号を入力してください。';
    }
    if ($v['name'] === '') {
        return '商品名を入力してください。';
    }
    if ($v['slug'] !== '' && !preg_match('/^[a-z0-9-]+$/', $v['slug'])) {
        return 'URL用の名前は半角英小文字・数字・ハイフンのみ使えます。';
    }
    if (!preg_match('/^\d{1,8}$/', $v['price'])) {
        return '価格は0以上の整数（数字のみ）で入力してください。';
    }
    if (!in_array($v['status'], ['draft', 'on_sale', 'sold_out', 'suspended'], true)) {
        return '販売状態が正しくありません。';
    }
    return null;
}

function slug_from_code(string $code): string
{
    $s = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($code)), '-');
    return $s !== '' ? $s : 'item-' . base_convert((string) time(), 10, 36);
}

function friendly_db_error(Throwable $e): string
{
    $m = $e->getMessage();
    if (str_contains($m, "'code'") || str_contains($m, 'products.code')) {
        return 'この商品管理番号はすでに使われています。';
    }
    if (str_contains($m, "'slug'") || str_contains($m, 'products.slug')) {
        return 'このURL用の名前はすでに使われています。';
    }
    error_log('[nori2023] ' . $m);
    return '保存できませんでした。入力内容を確認してください。';
}

function admin_product_save(?string $id): void
{
    $p = $id ? get_product($id) : null;
    if ($id && !$p) {
        admin_not_found();
        return;
    }
    $v = read_product_post();
    if ($p && $p['status'] === 'reserved') {
        $v['status'] = 'on_sale';
    }
    if ($err = validate_product($v)) {
        admin_product_form($p, $v, $err);
        return;
    }
    $status = ($p && $p['status'] === 'reserved') ? 'reserved' : $v['status'];
    if ($status === 'on_sale' && !photo_count_ok($p ? count($p['images']) : 0)) {
        admin_product_form($p, $v, $p
            ? PHOTO_RULE_TEXT . '（現在' . count($p['images']) . '枚）。写真を追加してから販売中にしてください。'
            : '写真を追加してから販売中にしてください。' . PHOTO_RULE_TEXT . '。');
        return;
    }
    $catId = $v['category_id'] !== '' && db_value('SELECT 1 FROM categories WHERE id = ?', [$v['category_id']]) ? $v['category_id'] : null;
    $shipId = $v['shipping_profile_id'] !== '' && db_value('SELECT 1 FROM shipping_profiles WHERE id = ?', [$v['shipping_profile_id']]) ? $v['shipping_profile_id'] : null;
    $soldAt = $status === 'sold_out' ? ($p['sold_at'] ?? now()) : null;
    $publishedAt = $p['published_at'] ?? ($status === 'on_sale' ? now() : null);
    $params = [
        $v['code'], $v['slug'] !== '' ? $v['slug'] : ($p['slug'] ?? slug_from_code($v['code'])), $v['name'], $catId, $v['description'],
        $v['material'] ?: null, $v['size'] ?: null, $v['color'] ?: null, (int) $v['price'], $shipId, $status,
        $status === 'sold_out' ? 0 : 1, $soldAt, $v['sold_note'] ?: null, $publishedAt,
    ];
    try {
        if ($p) {
            db_exec('UPDATE products SET code=?, slug=?, name=?, category_id=?, description=?, material=?, size=?, color=?, price=?,
                     shipping_profile_id=?, status=?, stock=?, sold_at=?, sold_note=?, published_at=?, updated_at=NOW() WHERE id=?', [...$params, $p['id']]);
            audit('product.update', $p['id'], ['from' => $p['status'], 'to' => $status, 'price' => (int) $v['price']]);
            redirect('/admin/products/' . $p['id'] . '?notice=saved');
        }
        $newId = uuid();
        db_exec('INSERT INTO products (code, slug, name, category_id, description, material, size, color, price,
                 shipping_profile_id, status, stock, sold_at, sold_note, published_at, id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [...$params, $newId]);
        audit('product.create', $newId, ['code' => $v['code'], 'status' => $status]);
        redirect('/admin/products/' . $newId . '?notice=created');
    } catch (PDOException $e) {
        admin_product_form($p, $v, friendly_db_error($e));
    }
}

function admin_product_delete(string $id): void
{
    $p = get_product($id);
    if (!$p) {
        admin_not_found();
        return;
    }
    $ordered = db_value('SELECT 1 FROM order_items WHERE product_id = ? LIMIT 1', [$id]);
    if (!in_array($p['status'], ['draft', 'suspended'], true) || $p['sold_at'] || $ordered) {
        admin_product_form($p, null, '販売実績のある商品は削除できません（SOLD OUT作品として残します）。');
        return;
    }
    $keys = [];
    foreach ($p['images'] as $img) {
        $keys[] = $img['key_large'];
        $keys[] = $img['key_small'];
    }
    db_exec('DELETE FROM products WHERE id = ?', [$id]);
    delete_uploads($keys);
    @rmdir(ROOT_DIR . '/' . UPLOAD_DIR_NAME . '/products/' . $id);
    audit('product.delete', $id, ['code' => $p['code']]);
    redirect('/admin/products?notice=deleted');
}

// ------------------------------------------------------------------ 商品写真（admin.js から呼ぶ JSON API）
function admin_image_upload(string $pid): void
{
    $p = get_product($pid);
    if (!$p) {
        json_response(['error' => '商品が見つかりません'], 404);
    }
    if (count($p['images']) >= 20) {
        json_response(['error' => '写真は1商品20枚までです'], 400);
    }
    $large = read_uploaded_image('large');
    $small = read_uploaded_image('small', 1024 * 1024);
    if (!$large || !$small) {
        json_response(['error' => '画像を読み込めませんでした（JPEG/WebP、4MB以下）'], 400);
    }
    $id = uuid();
    $keyLarge = "products/{$pid}/{$id}.{$large['ext']}";
    $keySmall = "products/{$pid}/{$id}_sm.{$small['ext']}";
    store_upload($keyLarge, $large['bytes']);
    store_upload($keySmall, $small['bytes']);
    $alt = post_str('alt', 200);
    db_exec(
        'INSERT INTO product_images (id, product_id, key_large, key_small, alt, width, height, sort_order, is_main) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$id, $pid, $keyLarge, $keySmall, $alt !== '' ? $alt : $p['name'], $large['width'], $large['height'], count($p['images']), count($p['images']) === 0 ? 1 : 0],
    );
    db_exec('UPDATE products SET updated_at = NOW() WHERE id = ?', [$pid]);
    audit('image.add', $pid, ['image' => $id]);
    json_response(['ok' => true, 'id' => $id]);
}

function json_body(): array
{
    $d = json_decode((string) file_get_contents('php://input'), true);
    return is_array($d) ? $d : [];
}

function admin_image_order(string $pid): void
{
    $ids = json_body()['ids'] ?? null;
    if (!is_array($ids)) {
        json_response(['error' => '不正なリクエスト'], 400);
    }
    $pdo = db();
    $pdo->beginTransaction();
    foreach (array_values($ids) as $i => $imgId) {
        db_exec('UPDATE product_images SET sort_order = ? WHERE id = ? AND product_id = ?', [$i, (string) $imgId, $pid]);
    }
    db_exec('UPDATE products SET updated_at = NOW() WHERE id = ?', [$pid]);
    $pdo->commit();
    json_response(['ok' => true]);
}

function admin_image_action(string $imgId, string $action): void
{
    $img = db_one('SELECT * FROM product_images WHERE id = ?', [$imgId]);
    if (!$img) {
        json_response(['error' => '写真が見つかりません'], 404);
    }
    $pid = $img['product_id'];
    if ($action === 'alt') {
        db_exec('UPDATE product_images SET alt = ? WHERE id = ?', [mb_substr((string) (json_body()['alt'] ?? ''), 0, 200), $imgId]);
        json_response(['ok' => true]);
    }
    if ($action === 'main') {
        $pdo = db();
        $pdo->beginTransaction();
        db_exec('UPDATE product_images SET is_main = 0 WHERE product_id = ?', [$pid]);
        db_exec('UPDATE product_images SET is_main = 1 WHERE id = ?', [$imgId]);
        db_exec('UPDATE products SET updated_at = NOW() WHERE id = ?', [$pid]);
        $pdo->commit();
        json_response(['ok' => true]);
    }
    // 削除：販売中の商品は写真が0枚にならないようにする
    $prod = db_one('SELECT status, (SELECT COUNT(*) FROM product_images WHERE product_id = ?) AS n FROM products WHERE id = ?', [$pid, $pid]);
    if ($prod && in_array($prod['status'], ['on_sale', 'reserved'], true) && !photo_count_ok((int) $prod['n'] - 1)) {
        json_response(['error' => '販売中のため削除できません。' . PHOTO_RULE_TEXT . '。先に写真を追加するか、販売状態を変えてください。'], 400);
    }
    db_exec('DELETE FROM product_images WHERE id = ?', [$imgId]);
    delete_uploads([$img['key_large'], $img['key_small']]);
    if ($img['is_main']) {
        $first = db_value('SELECT id FROM product_images WHERE product_id = ? ORDER BY sort_order LIMIT 1', [$pid]);
        if ($first) {
            db_exec('UPDATE product_images SET is_main = 1 WHERE id = ?', [$first]);
        }
    }
    db_exec('UPDATE products SET updated_at = NOW() WHERE id = ?', [$pid]);
    audit('image.delete', $pid, ['image' => $imgId]);
    json_response(['ok' => true]);
}

// ================================================================== カテゴリー
function admin_categories(?string $error = null): void
{
    $row = function (?array $c): string {
        $action = $c ? url('/admin/categories/' . $c['id']) : url('/admin/categories');
        return '<form method="post" action="' . e($action) . '" class="grid items-end gap-3 sm:grid-cols-[1fr_1fr_6rem_auto]">' . csrf_field()
            . admin_field($c ? '表示名' : '新しいカテゴリー', '<input name="name" required maxlength="40" value="' . e($c['name'] ?? '') . '" placeholder="例：ペンケース" class="' . A_INPUT . '">')
            . admin_field('URL用の名前', '<input name="slug" required pattern="[a-z0-9-]+" maxlength="80" value="' . e($c['slug'] ?? '') . '" placeholder="例：pen-case" class="' . A_INPUT . '">')
            . admin_field('並び順', '<input name="sort_order" type="number" value="' . e((string) ($c['sort_order'] ?? 50)) . '" class="' . A_INPUT . '">')
            . '<button class="' . ($c ? A_BTN_GHOST : A_BTN_PRIMARY) . '">' . ($c ? '保存' : '追加') . '</button></form>';
    };
    $h = '<ul class="space-y-3">';
    foreach (list_categories() as $c) {
        $h .= '<li class="' . A_CARD . '">' . $row($c) . '</li>';
    }
    $h .= '</ul><div class="' . A_CARD . ' mt-6">' . $row(null) . '</div>';
    render_admin('カテゴリー', $h, $error);
}

function admin_category_save(?string $id): void
{
    $name = post_str('name', 40);
    $slug = post_str('slug', 80);
    $sort = (int) post_str('sort_order', 6);
    if ($name === '' || !preg_match('/^[a-z0-9-]+$/', $slug)) {
        admin_categories('表示名と、URL用の名前（半角英小文字・数字・ハイフン）を入力してください。');
        return;
    }
    try {
        if ($id) {
            db_exec('UPDATE categories SET name = ?, slug = ?, sort_order = ? WHERE id = ?', [$name, $slug, $sort, $id]);
        } else {
            db_exec('INSERT INTO categories (id, name, slug, sort_order) VALUES (?, ?, ?, ?)', [uuid(), $name, $slug, $sort]);
        }
    } catch (PDOException) {
        admin_categories('URL用の名前が、ほかのカテゴリーと重複しています。');
        return;
    }
    audit($id ? 'category.update' : 'category.create', $id, ['name' => $name, 'slug' => $slug]);
    redirect('/admin/categories?notice=saved');
}

// ================================================================== 送料設定
function admin_shipping(): void
{
    $row = function (?array $s): string {
        $action = $s ? url('/admin/shipping/' . $s['id']) : url('/admin/shipping');
        return '<form method="post" action="' . e($action) . '" class="grid items-end gap-3 sm:grid-cols-[1fr_8rem_1fr_auto_auto]">' . csrf_field()
            . admin_field($s ? '配送方法' : '新しい配送方法', '<input name="name" required maxlength="60" value="' . e($s['name'] ?? '') . '" placeholder="例：クリックポスト" class="' . A_INPUT . '">')
            . admin_field('送料（税込・円）', '<input name="fee" type="number" min="0" inputmode="numeric" value="' . e(isset($s['fee']) ? (string) $s['fee'] : '') . '" placeholder="未定なら空欄" class="' . A_INPUT . '">')
            . admin_field('備考', '<input name="note" maxlength="200" value="' . e($s['note'] ?? '') . '" class="' . A_INPUT . '">')
            . '<label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1"' . (!$s || $s['is_active'] ? ' checked' : '') . '> 有効</label>'
            . '<button class="' . ($s ? A_BTN_GHOST : A_BTN_PRIMARY) . '">' . ($s ? '保存' : '追加') . '</button></form>';
    };
    $h = '<p class="mb-4 text-sm text-muted">ここで登録した配送方法を、商品ごとに選びます。金額が決まっていない場合は空欄にしておくと、お客様には「購入手続きのときにお知らせします」と表示されます。</p><ul class="space-y-3">';
    foreach (list_shipping_profiles() as $s) {
        $h .= '<li class="' . A_CARD . '">' . $row($s) . '</li>';
    }
    $h .= '</ul><div class="' . A_CARD . ' mt-6">' . $row(null) . '</div>';
    render_admin('送料設定', $h);
}

function admin_shipping_save(?string $id): void
{
    $name = post_str('name', 60);
    $feeRaw = post_str('fee', 8);
    $fee = $feeRaw === '' ? null : (preg_match('/^\d+$/', $feeRaw) ? (int) $feeRaw : false);
    if ($name === '' || $fee === false) {
        redirect('/admin/shipping');
    }
    $note = post_str('note', 200) ?: null;
    $active = ($_POST['is_active'] ?? '') === '1' ? 1 : 0;
    if ($id) {
        db_exec('UPDATE shipping_profiles SET name = ?, fee = ?, note = ?, is_active = ? WHERE id = ?', [$name, $fee, $note, $active, $id]);
    } else {
        db_exec('INSERT INTO shipping_profiles (id, name, fee, note, is_active) VALUES (?, ?, ?, ?, ?)', [uuid(), $name, $fee, $note, $active]);
    }
    audit($id ? 'shipping.update' : 'shipping.create', $id, ['name' => $name, 'fee' => $fee]);
    redirect('/admin/shipping?notice=saved');
}

// ================================================================== ページ設定
function admin_settings(): void
{
    $hero = hero_setting();
    $about = get_setting('page.about', ['body' => null]);
    $img = $hero['imageKey'] ? upload_url($hero['imageKey']) : url('/images/works/coincase-natural-2.jpg');
    $h = '<div class="space-y-6"><section class="' . A_CARD . '"><h2 class="font-serif text-lg text-leather">トップページのメイン画像</h2>'
        . '<div class="mt-3 flex flex-wrap items-start gap-4"><img src="' . e($img) . '" alt="現在のメイン画像" class="h-40 w-32 rounded object-cover">'
        . '<div class="flex-1 space-y-2 text-sm"><p class="text-muted">' . ($hero['imageKey'] ? 'アップロードした画像を表示中' : '初期画像（ナチュラルのコインケース）を表示中') . '</p>'
        . '<label class="' . A_BTN_GHOST . ' cursor-pointer">画像を選んで変更<input type="file" accept="image/*" class="sr-only" data-hero-input></label>'
        . '<p data-hero-status class="text-muted" aria-live="polite"></p>';
    if ($hero['imageKey']) {
        $h .= '<form method="post" action="' . e(url('/admin/settings/hero-image/reset')) . '">' . csrf_field() . '<button class="text-sm text-red-700 underline">初期画像に戻す</button></form>';
    }
    $h .= '</div></div></section>'
        . '<form method="post" action="' . e(url('/admin/settings')) . '" class="' . A_CARD . ' space-y-4">' . csrf_field() . '<h2 class="font-serif text-lg text-leather">文言</h2>'
        . admin_field('キャッチコピー', '<input id="hero_catch" name="hero_catch" maxlength="60" value="' . e($hero['catch']) . '" class="' . A_INPUT . '">', 'hero_catch', '「、」の後ろで改行して表示します')
        . admin_field('サブコピー', '<input id="hero_sub" name="hero_sub" maxlength="80" value="' . e($hero['sub']) . '" class="' . A_INPUT . '">', 'hero_sub')
        . admin_field('制作者紹介の本文', '<textarea id="about_body" name="about_body" rows="14" class="' . A_INPUT . '">' . e((string) ($about['body'] ?? '')) . '</textarea>', 'about_body', '空行で段落が分かれます。空欄にすると初期の文章を表示します')
        . '<button class="' . A_BTN_PRIMARY . '">保存する</button></form></div>';
    render_admin('ページ設定', $h);
}

function admin_settings_save(): void
{
    $hero = hero_setting();
    $actor = current_admin()['email'];
    set_setting('home.hero', ['catch' => post_str('hero_catch', 60) ?: SITE_CATCH, 'sub' => post_str('hero_sub', 80) ?: SITE_SUB, 'imageKey' => $hero['imageKey']], $actor);
    $body = post_str('about_body', 5000);
    set_setting('page.about', ['body' => $body !== '' ? $body : null], $actor);
    audit('settings.update', 'home.hero,page.about');
    redirect('/admin/settings?notice=saved');
}

function admin_hero_upload(): void
{
    $large = read_uploaded_image('large');
    if (!$large) {
        json_response(['error' => '画像を読み込めませんでした'], 400);
    }
    $key = 'site/hero-' . uuid() . '.' . $large['ext'];
    store_upload($key, $large['bytes']);
    $hero = hero_setting();
    if ($hero['imageKey']) {
        delete_uploads([$hero['imageKey']]);
    }
    set_setting('home.hero', ['catch' => $hero['catch'], 'sub' => $hero['sub'], 'imageKey' => $key], current_admin()['email']);
    audit('settings.hero_image', $key);
    json_response(['ok' => true]);
}

function admin_hero_reset(): void
{
    $hero = hero_setting();
    if ($hero['imageKey']) {
        delete_uploads([$hero['imageKey']]);
    }
    set_setting('home.hero', ['catch' => $hero['catch'], 'sub' => $hero['sub'], 'imageKey' => null], current_admin()['email']);
    redirect('/admin/settings?notice=saved');
}

// ================================================================== Instagram 連携
function admin_instagram(?string $error = null): void
{
    $t = ig_token();
    $s = get_setting('instagram.sync', []);
    $acc = get_setting('instagram.account', []);
    $posts = db_all('SELECT * FROM instagram_posts ORDER BY posted_at DESC LIMIT 24');

    // 手動登録
    $h = '<div class="space-y-6"><form method="post" action="' . e(url('/admin/instagram/posts')) . '" enctype="multipart/form-data" data-resize-form class="' . A_CARD . ' grid gap-4 sm:grid-cols-2">' . csrf_field()
        . '<div class="sm:col-span-2"><h2 class="font-serif text-lg text-leather">投稿を登録する</h2>'
        . '<p class="mt-1 text-sm leading-6 text-muted">Instagramに投稿したら、その投稿のURLと写真を登録してください。トップページに新しい順で6件表示されます。<br>URLは、Instagramの投稿の「…」または紙飛行機マーク →「リンクをコピー」で取れます。</p></div>'
        . '<div class="sm:col-span-2">' . admin_field('投稿のURL *', '<input id="permalink" name="permalink" type="url" required maxlength="255" placeholder="https://www.instagram.com/p/…" class="' . A_INPUT . '">', 'permalink') . '</div>'
        . image_input('image', '写真 *', null, true, 'Instagramに載せた写真と同じものを選んでください（正方形に切り抜いて表示します）')
        . '<div class="space-y-4">' . admin_field('投稿日', '<input id="posted_on" name="posted_on" type="date" value="' . e(date('Y-m-d')) . '" class="' . A_INPUT . '">', 'posted_on')
        . admin_field('種類', '<select id="media_type" name="media_type" class="' . A_INPUT . '"><option value="IMAGE">写真</option><option value="CAROUSEL_ALBUM">複数枚の写真</option><option value="VIDEO">動画・リール</option></select>', 'media_type') . '</div>'
        . '<div class="sm:col-span-2">' . admin_field('写真の説明', '<input id="caption" name="caption" maxlength="200" class="' . A_INPUT . '">', 'caption', '例：キャメルのコインケース。読み上げや検索に使われます') . '</div>'
        . '<div class="flex flex-wrap items-center gap-3 sm:col-span-2"><button class="' . A_BTN_PRIMARY . '">登録する</button><p data-form-status class="text-sm text-muted" aria-live="polite"></p></div></form>';

    if ($posts) {
        $h .= '<section class="' . A_CARD . '"><h2 class="font-serif text-lg text-leather">登録済みの投稿</h2><p class="mt-1 text-xs text-muted">上から6件がトップページに出ます。</p><ul class="mt-3 grid grid-cols-3 gap-3 sm:grid-cols-6">';
        foreach ($posts as $p) {
            $h .= '<li class="text-center"><a href="' . e($p['permalink']) . '" target="_blank" rel="noopener noreferrer" class="block aspect-square overflow-hidden rounded bg-sand">'
                . ($p['image_key'] ? '<img src="' . e(upload_url($p['image_key'])) . '" alt="" class="h-full w-full object-cover" loading="lazy">' : '') . '</a>'
                . '<p class="mt-1 text-[11px] text-muted">' . e(format_date($p['posted_at'])) . '</p>'
                . '<form method="post" action="' . e(url('/admin/instagram/posts/' . rawurlencode($p['id']) . '/delete')) . '" data-confirm="この投稿をサイトから外します。よろしいですか？（Instagram側は消えません）">'
                . csrf_field() . '<button class="min-h-9 text-xs text-red-700 underline">外す</button></form></li>';
        }
        $h .= '</ul></section>';
    }

    // 自動連携（Meta側の準備ができたとき用）
    $h .= '<details class="' . A_CARD . '"' . ($t ? ' open' : '') . '><summary class="cursor-pointer font-serif text-lg text-leather">自動連携（Metaのアプリを使う場合）</summary>'
        . '<dl class="mt-3 grid grid-cols-[8rem_1fr] gap-y-2 text-sm">'
        . '<dt class="text-muted">アクセストークン</dt><dd>' . ($t ? ($t['expires_at'] ? '登録済み（有効期限 ' . e(format_datetime($t['expires_at'])) . '・自動更新）' : '登録済み（有効期限なし）') : '未登録（手動登録で運用中）') . '</dd>'
        . (!empty($acc['id']) ? '<dt class="text-muted">アカウント</dt><dd>' . e(!empty($acc['username']) ? '@' . $acc['username'] : '') . ' <span class="text-muted">ID ' . e($acc['id']) . '</span></dd>' : '')
        . '<dt class="text-muted">最終更新</dt><dd>' . (!empty($s['lastSyncAt']) ? e(format_datetime($s['lastSyncAt'])) : '—') . '</dd>'
        . (!empty($s['lastError']) ? '<dt class="text-muted">エラー</dt><dd class="text-red-700">' . e($s['lastError']) . '</dd>' : '')
        . '</dl>';
    if ($t) {
        $h .= '<div class="mt-4 flex flex-wrap gap-3"><form method="post" action="' . e(url('/admin/instagram/sync')) . '">' . csrf_field() . '<button class="' . A_BTN_PRIMARY . '">今すぐ更新</button></form>'
            . '<form method="post" action="' . e(url('/admin/instagram/token/delete')) . '" data-confirm="自動連携をやめます。登録済みの投稿は残ります。よろしいですか？">' . csrf_field() . '<button class="' . A_BTN_DANGER . '">自動連携をやめる</button></form></div>';
    }
    $h .= '<form method="post" action="' . e(url('/admin/instagram/token')) . '" class="mt-6 space-y-3 border-t border-line pt-5">' . csrf_field()
        . '<p class="text-sm leading-6 text-muted">Meta for Developers のアプリ画面「ユースケース → カスタマイズ → API setup with Unified login」で発行したアクセストークン（EAA… で始まる文字列）を貼り付けると、6時間ごとに最新の投稿を自動で取り込みます。トークンは画面には再表示しません。</p>'
        . '<input name="token" type="password" autocomplete="off" required minlength="20" class="' . A_INPUT . '" placeholder="EAA…" aria-label="アクセストークン">'
        . admin_field('InstagramアカウントID（通常は空欄）', '<input id="ig_user_id" name="ig_user_id" inputmode="numeric" pattern="[0-9]*" maxlength="30" class="' . A_INPUT . '">', 'ig_user_id', '自動で見つからないときだけ、数字のIDを入力します')
        . '<button class="' . A_BTN_GHOST . '">登録して取得する</button></form></details>';
    render_admin('Instagram', $h . '</div>', $error);
}

function admin_instagram_post_save(): void
{
    $url = post_str('permalink', 255);
    $date = post_str('posted_on', 10);
    $type = in_array($_POST['media_type'] ?? '', ['IMAGE', 'CAROUSEL_ALBUM', 'VIDEO'], true) ? $_POST['media_type'] : 'IMAGE';
    $caption = post_str('caption', 200);
    $fail = static fn (string $m) => form_fail($m, static fn ($msg) => admin_instagram($msg));
    if (!preg_match('#^https://(www\.)?instagram\.com/[A-Za-z0-9_.\-/]+/?(\?[A-Za-z0-9_=&%.\-]*)?$#', $url)) {
        $fail('InstagramのURL（https://www.instagram.com/… の形）を入力してください。');
    }
    $url = preg_replace('/\?.*$/', '', $url); // 追跡用のパラメータを外す
    if ($date !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date))) {
        $fail('投稿日を正しく入力してください。');
    }
    if (db_value('SELECT COUNT(*) FROM instagram_posts WHERE permalink = ?', [$url])) {
        $fail('この投稿は、もう登録されています。');
    }
    $img = read_uploaded_image('image');
    if (!$img) {
        $fail('写真を読み込めませんでした。写真を選び直してください。');
    }
    $id = 'm_' . bin2hex(random_bytes(8));
    $key = 'instagram/' . $id . '.' . $img['ext'];
    store_upload($key, $img['bytes']);
    $posted = ($date ?: date('Y-m-d')) . ' ' . ($date && $date !== date('Y-m-d') ? '12:00:00' : date('H:i:s'));
    db_exec('INSERT INTO instagram_posts (id, permalink, caption, media_type, posted_at, image_key, synced_at) VALUES (?, ?, ?, ?, ?, ?, NOW())',
        [$id, $url, $caption ?: null, $type, $posted, $key]);
    audit('instagram.post_add', $id, ['url' => $url]);
    form_done('/admin/instagram?notice=saved');
}

function admin_instagram_post_delete(string $id): void
{
    $p = db_one('SELECT * FROM instagram_posts WHERE id = ?', [$id]);
    if ($p) {
        db_exec('DELETE FROM instagram_posts WHERE id = ?', [$id]);
        if ($p['image_key']) {
            delete_uploads([$p['image_key']]);
        }
        audit('instagram.post_delete', $id, ['url' => $p['permalink']]);
    }
    redirect('/admin/instagram?notice=deleted');
}

function admin_instagram_token_delete(): void
{
    db_exec("DELETE FROM integration_tokens WHERE name = 'instagram'");
    set_setting('instagram.account', [], current_admin()['email']);
    set_setting('instagram.sync', [], current_admin()['email']);
    audit('instagram.token_delete');
    redirect('/admin/instagram?notice=deleted');
}

function admin_instagram_token(): void
{
    $token = trim((string) ($_POST['token'] ?? ''));
    if (strlen($token) < 20 || preg_match('/\s/', $token)) {
        redirect('/admin/instagram');
    }
    ig_save_token($token);
    $igId = preg_replace('/\D/', '', post_str('ig_user_id', 30));
    set_setting('instagram.account', $igId !== '' ? ['id' => $igId, 'username' => null, 'manual' => true] : [], current_admin()['email']);
    audit('instagram.token_set');
    $r = ig_sync();
    redirect($r['ok'] ? '/admin/instagram?notice=synced' : '/admin/instagram');
}

function admin_instagram_sync(): void
{
    $r = ig_sync();
    audit('instagram.sync', null, $r);
    redirect($r['ok'] ? '/admin/instagram?notice=synced' : '/admin/instagram');
}

// ================================================================== 操作履歴
function admin_logs(): void
{
    $rows = db_all('SELECT * FROM audit_logs ORDER BY id DESC LIMIT 200');
    $h = '<div class="' . A_CARD . ' overflow-x-auto !p-0"><table class="w-full min-w-[560px] text-left text-sm"><thead class="bg-sand/60 text-xs text-muted">'
        . '<tr><th class="px-3 py-2">日時</th><th class="px-3 py-2">操作者</th><th class="px-3 py-2">操作</th><th class="px-3 py-2">内容</th></tr></thead><tbody class="divide-y divide-line">';
    foreach ($rows as $r) {
        $h .= '<tr><td class="whitespace-nowrap px-3 py-2">' . e(format_datetime($r['created_at'])) . '</td><td class="px-3 py-2">' . e($r['actor']) . '</td>'
            . '<td class="px-3 py-2">' . e($r['action']) . '</td><td class="max-w-[24rem] truncate px-3 py-2 text-xs text-muted">' . e($r['detail'] ?? $r['target'] ?? '') . '</td></tr>';
    }
    render_admin('操作履歴', $h . '</tbody></table></div>');
}

// ================================================================== アカウント（パスワード変更）
function admin_account(?string $error = null): void
{
    $h = '<form method="post" action="' . e(url('/admin/account')) . '" class="' . A_CARD . ' max-w-md space-y-4">' . csrf_field()
        . '<p class="text-sm text-muted">ログイン中：' . e(current_admin()['email']) . '</p>'
        . admin_field('今のパスワード', '<input id="current" name="current" type="password" required autocomplete="current-password" class="' . A_INPUT . '">', 'current')
        . admin_field('新しいパスワード', '<input id="new" name="new" type="password" required autocomplete="new-password" class="' . A_INPUT . '">', 'new', '10文字以上・英字と数字を含む')
        . admin_field('新しいパスワード（確認）', '<input id="new2" name="new2" type="password" required autocomplete="new-password" class="' . A_INPUT . '">', 'new2')
        . '<button class="' . A_BTN_PRIMARY . '">変更する</button></form>';
    render_admin('アカウント', $h, $error);
}

function admin_account_save(): void
{
    $user = db_one('SELECT * FROM admin_users WHERE id = ?', [current_admin()['id']]);
    $new = (string) ($_POST['new'] ?? '');
    if (!$user || !password_verify((string) ($_POST['current'] ?? ''), $user['password_hash'])) {
        admin_account('今のパスワードが違います。');
        return;
    }
    if ($p = password_problem($new)) {
        admin_account($p);
        return;
    }
    if ($new !== (string) ($_POST['new2'] ?? '')) {
        admin_account('確認用のパスワードが一致しません。');
        return;
    }
    db_exec('UPDATE admin_users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $user['id']]);
    session_regenerate_id(true);
    audit('account.password_change');
    redirect('/admin/account?notice=password');
}
