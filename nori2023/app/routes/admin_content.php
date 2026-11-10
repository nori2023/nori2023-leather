<?php
declare(strict_types=1);

/**
 * 管理画面：ワークショップ・革の経年変化
 * 写真つきフォームは assets/admin.js がブラウザで縮小してから送る（data-resize-form）。
 */

function wants_json(): bool
{
    return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
}

/** 保存後の移動。JS送信なら JSON、通常送信ならリダイレクト */
function form_done(string $path): never
{
    if (wants_json()) {
        json_response(['redirect' => url($path)]);
    }
    redirect($path);
}

function form_fail(string $message, callable $rerender): never
{
    if (wants_json()) {
        json_response(['error' => $message], 422);
    }
    $rerender($message);
    exit;
}

function image_input(string $name, string $label, ?string $currentKey, bool $required, string $hint = ''): string
{
    $preview = $currentKey
        ? '<img src="' . e(upload_url($currentKey)) . '" alt="" class="mb-2 h-32 w-32 rounded object-cover">'
        : '';
    $control = $preview . '<input type="file" name="' . e($name) . '" accept="image/*" data-resize' . ($required && !$currentKey ? ' required' : '')
        . ' class="mt-1 block w-full text-sm">';
    return admin_field($label, $control, $name, $hint ?: ($currentKey ? '変更するときだけ選んでください' : 'スマホで撮った写真をそのまま選べます'));
}

/** 任意の写真を保存して新しいキーを返す（未選択なら null、不正なら false） */
function save_optional_image(string $field, string $prefix): string|false|null
{
    $f = $_FILES[$field] ?? null;
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $img = read_uploaded_image($field);
    if (!$img) {
        return false;
    }
    $key = 'site/' . $prefix . '-' . uuid() . '.' . $img['ext'];
    store_upload($key, $img['bytes']);
    return $key;
}

// ================================================================== ワークショップ
const WEEKDAYS = ['日', '月', '火', '水', '木', '金', '土'];

function workshop_date_label(string $date): string
{
    $t = strtotime($date);
    return $t ? date('Y年n月j日', $t) . '（' . WEEKDAYS[(int) date('w', $t)] . '）' : '';
}

function admin_workshops(): void
{
    $rows = db_all('SELECT * FROM workshop_events ORDER BY event_date DESC');
    $h = '<div class="mb-4 flex flex-wrap items-center justify-between gap-3"><p class="text-sm text-muted">サイトの「ワークショップ」ページに表示する催しを登録します。</p>'
        . '<a href="' . e(url('/admin/workshops/new')) . '" class="' . A_BTN_PRIMARY . '">＋ ワークショップを登録</a></div>';
    if (!$rows) {
        $h .= '<p class="' . A_CARD . ' text-sm text-muted">まだ登録がありません。</p>';
    }
    $h .= '<ul class="space-y-3">';
    $today = date('Y-m-d');
    foreach ($rows as $w) {
        $badge = $w['is_published'] ? '<span class="rounded bg-green-50 px-2 py-0.5 text-xs text-green-900">公開中</span>' : '<span class="rounded bg-stone-100 px-2 py-0.5 text-xs">非公開</span>';
        $past = $w['event_date'] < $today ? '<span class="rounded bg-stone-100 px-2 py-0.5 text-xs">終了</span>' : '';
        $h .= '<li><a href="' . e(url('/admin/workshops/' . $w['id'])) . '" class="' . A_CARD . ' flex items-center gap-4 hover:border-camel">'
            . ($w['image_key'] ? '<img src="' . e(upload_url($w['image_key'])) . '" alt="" class="h-16 w-16 shrink-0 rounded object-cover">' : '<div class="h-16 w-16 shrink-0 rounded bg-sand"></div>')
            . '<div class="min-w-0"><p class="text-sm text-muted">' . e(workshop_date_label($w['event_date'])) . '　' . e($w['place']) . '</p>'
            . '<p class="truncate font-medium">' . e($w['title']) . '</p><p class="mt-1 flex gap-2">' . $badge . $past . '</p></div></a></li>';
    }
    render_admin('ワークショップ', $h . '</ul>');
}

function admin_workshop_form(?string $id, ?string $error = null, ?array $input = null): void
{
    $w = $id ? db_one('SELECT * FROM workshop_events WHERE id = ?', [$id]) : null;
    if ($id && !$w) {
        admin_not_found();
        exit;
    }
    $v = $input ?? $w ?? ['accepting' => 1, 'is_published' => 0];
    $val = static fn (string $k): string => e((string) ($v[$k] ?? ''));
    $time = static fn (string $k): string => e(substr((string) ($v[$k] ?? ''), 0, 5));
    $check = static fn (string $k): string => !empty($v[$k]) ? ' checked' : '';
    $action = url($id ? '/admin/workshops/' . $id : '/admin/workshops');

    $h = '<form method="post" action="' . e($action) . '" enctype="multipart/form-data" data-resize-form class="' . A_CARD . ' grid gap-4 sm:grid-cols-2">' . csrf_field()
        . '<div class="sm:col-span-2">' . admin_field('見出し *', '<input id="title" name="title" required maxlength="150" value="' . $val('title') . '" class="' . A_INPUT . '">', 'title', '例：好きな色を選んで、世界にひとつだけのキーホルダーを作ってみんさい！') . '</div>'
        . admin_field('作るもの *', '<input id="item_name" name="item_name" required maxlength="100" value="' . $val('item_name') . '" class="' . A_INPUT . '">', 'item_name')
        . admin_field('開催日 *', '<input id="event_date" name="event_date" type="date" required value="' . $val('event_date') . '" class="' . A_INPUT . '">', 'event_date')
        . admin_field('開始時刻', '<input id="start_time" name="start_time" type="time" value="' . $time('start_time') . '" class="' . A_INPUT . '">', 'start_time')
        . admin_field('終了時刻', '<input id="end_time" name="end_time" type="time" value="' . $time('end_time') . '" class="' . A_INPUT . '">', 'end_time')
        . '<div class="sm:col-span-2">' . admin_field('場所 *', '<input id="place" name="place" required maxlength="200" value="' . $val('place') . '" class="' . A_INPUT . '">', 'place') . '</div>'
        . admin_field('参加費（税込・円）', '<input id="fee" name="fee" type="number" min="0" inputmode="numeric" value="' . $val('fee') . '" class="' . A_INPUT . '">', 'fee', '未定なら空欄')
        . admin_field('参加費の補足', '<input id="fee_note" name="fee_note" maxlength="200" value="' . $val('fee_note') . '" class="' . A_INPUT . '">', 'fee_note', '例：Instagram特典で800円（税込）')
        . admin_field('定員', '<input id="capacity" name="capacity" type="number" min="1" inputmode="numeric" value="' . $val('capacity') . '" class="' . A_INPUT . '">', 'capacity', '決まっていなければ空欄')
        . admin_field('予約ページのURL', '<input id="reserve_url" name="reserve_url" type="url" maxlength="500" value="' . $val('reserve_url') . '" placeholder="https://" class="' . A_INPUT . '">', 'reserve_url', '例：https://mpr-shop.com/workshop-te-to-te/')
        . '<div class="sm:col-span-2">' . admin_field('紹介文 *', '<textarea id="description" name="description" required rows="12" class="' . A_INPUT . '">' . $val('description') . '</textarea>', 'description', '改行はそのまま表示されます') . '</div>'
        . '<div class="sm:col-span-2">' . image_input('image', '写真', $w['image_key'] ?? null, false) . '</div>'
        . '<label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" name="accepting" value="1"' . $check('accepting') . '> 予約を受け付けている</label>'
        . '<label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" name="is_published" value="1"' . $check('is_published') . '> サイトに公開する</label>'
        . '<div class="flex flex-wrap items-center gap-3 sm:col-span-2"><button class="' . A_BTN_PRIMARY . '">保存する</button>'
        . '<a href="' . e(url('/admin/workshops')) . '" class="' . A_BTN_GHOST . '">一覧に戻る</a><p data-form-status class="text-sm text-muted" aria-live="polite"></p></div></form>';
    if ($w) {
        $h .= '<form method="post" action="' . e(url('/admin/workshops/' . $id . '/delete')) . '" class="mt-6" data-confirm="このワークショップを削除します。よろしいですか？">'
            . csrf_field() . '<button class="' . A_BTN_DANGER . '">このワークショップを削除</button></form>';
    }
    render_admin($w ? 'ワークショップの編集' : 'ワークショップの登録', $h, $error);
}

function admin_workshop_save(?string $id): void
{
    $old = $id ? db_one('SELECT * FROM workshop_events WHERE id = ?', [$id]) : null;
    if ($id && !$old) {
        admin_not_found();
        exit;
    }
    $in = [
        'title' => post_str('title', 150),
        'item_name' => post_str('item_name', 100),
        'event_date' => post_str('event_date', 10),
        'start_time' => post_str('start_time', 5),
        'end_time' => post_str('end_time', 5),
        'place' => post_str('place', 200),
        'fee' => post_str('fee', 8),
        'fee_note' => post_str('fee_note', 200),
        'capacity' => post_str('capacity', 6),
        'reserve_url' => post_str('reserve_url', 500),
        'description' => post_str('description', 5000),
        'accepting' => ($_POST['accepting'] ?? '') === '1' ? 1 : 0,
        'is_published' => ($_POST['is_published'] ?? '') === '1' ? 1 : 0,
    ];
    $fail = static fn (string $msg) => form_fail($msg, static fn ($m) => admin_workshop_form($id, $m, $in + ['image_key' => $old['image_key'] ?? null]));
    if ($in['title'] === '' || $in['item_name'] === '' || $in['place'] === '' || $in['description'] === '') {
        $fail('見出し・作るもの・場所・紹介文を入力してください。');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $in['event_date']) || !strtotime($in['event_date'])) {
        $fail('開催日を正しく入力してください。');
    }
    foreach (['start_time', 'end_time'] as $k) {
        if ($in[$k] !== '' && !preg_match('/^\d{2}:\d{2}$/', $in[$k])) {
            $fail('時刻を正しく入力してください。');
        }
    }
    foreach (['fee', 'capacity'] as $k) {
        if ($in[$k] !== '' && !preg_match('/^\d+$/', $in[$k])) {
            $fail('参加費・定員は数字で入力してください。');
        }
    }
    if ($in['reserve_url'] !== '' && !preg_match('#^https://[^\s"<>]+$#', $in['reserve_url'])) {
        $fail('予約ページのURLは https:// から始まる形で入力してください。');
    }
    $newKey = save_optional_image('image', 'workshop');
    if ($newKey === false) {
        $fail('写真を読み込めませんでした。JPEG の写真を選んでください。');
    }
    $imageKey = $newKey ?? ($old['image_key'] ?? null);
    $params = [
        $in['title'], $in['item_name'], $imageKey, $in['event_date'],
        $in['start_time'] ?: null, $in['end_time'] ?: null, $in['place'],
        $in['fee'] === '' ? null : (int) $in['fee'], $in['fee_note'] ?: null, $in['reserve_url'] ?: null,
        $in['capacity'] === '' ? null : (int) $in['capacity'],
        $in['accepting'], $in['description'], $in['is_published'],
    ];
    if ($old) {
        db_exec('UPDATE workshop_events SET title = ?, item_name = ?, image_key = ?, event_date = ?, start_time = ?, end_time = ?, place = ?,
                 fee = ?, fee_note = ?, reserve_url = ?, capacity = ?, accepting = ?, description = ?, is_published = ?, updated_at = NOW() WHERE id = ?',
            [...$params, $id]);
        if ($newKey && $old['image_key']) {
            delete_uploads([$old['image_key']]);
        }
    } else {
        $id = uuid();
        db_exec('INSERT INTO workshop_events (title, item_name, image_key, event_date, start_time, end_time, place, fee, fee_note, reserve_url,
                 capacity, accepting, description, is_published, id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [...$params, $id]);
    }
    audit($old ? 'workshop.update' : 'workshop.create', $id, ['title' => $in['title'], 'published' => $in['is_published']]);
    form_done('/admin/workshops/' . $id . '?notice=saved');
}

function admin_workshop_delete(string $id): void
{
    $w = db_one('SELECT * FROM workshop_events WHERE id = ?', [$id]);
    if ($w) {
        db_exec('DELETE FROM workshop_events WHERE id = ?', [$id]);
        if ($w['image_key']) {
            delete_uploads([$w['image_key']]);
        }
        audit('workshop.delete', $id, ['title' => $w['title']]);
    }
    redirect('/admin/workshops?notice=deleted');
}

// ================================================================== 革の経年変化
function admin_aging(): void
{
    $rows = db_all('SELECT * FROM leather_aging_photos ORDER BY sort_order, created_at');
    $h = '<div class="mb-4 flex flex-wrap items-center justify-between gap-3"><p class="text-sm text-muted">使い始めと、使い込んだあとの写真を2枚1組で登録します。実際に使った記録だけを公開できます。</p>'
        . '<a href="' . e(url('/admin/aging/new')) . '" class="' . A_BTN_PRIMARY . '">＋ 比較写真を登録</a></div>';
    if (!$rows) {
        $h .= '<p class="' . A_CARD . ' text-sm text-muted">まだ登録がありません。</p>';
    }
    $h .= '<ul class="grid gap-3 sm:grid-cols-2">';
    foreach ($rows as $a) {
        $h .= '<li><a href="' . e(url('/admin/aging/' . $a['id'])) . '" class="' . A_CARD . ' block hover:border-camel">'
            . '<div class="grid grid-cols-2 gap-2"><img src="' . e(upload_url($a['before_key'])) . '" alt="" class="aspect-square w-full rounded object-cover">'
            . '<img src="' . e(upload_url($a['after_key'])) . '" alt="" class="aspect-square w-full rounded object-cover"></div>'
            . '<p class="mt-2 font-medium">' . e($a['title']) . '</p><p class="mt-1 text-xs">'
            . ($a['is_published'] ? '<span class="rounded bg-green-50 px-2 py-0.5 text-green-900">公開中</span>' : '<span class="rounded bg-stone-100 px-2 py-0.5">非公開</span>')
            . ($a['period_label'] ? ' <span class="text-muted">' . e($a['period_label']) . '</span>' : '') . '</p></a></li>';
    }
    render_admin('革の経年変化', $h . '</ul>');
}

function admin_aging_form(?string $id, ?string $error = null, ?array $input = null): void
{
    $a = $id ? db_one('SELECT * FROM leather_aging_photos WHERE id = ?', [$id]) : null;
    if ($id && !$a) {
        admin_not_found();
        exit;
    }
    $v = $input ?? $a ?? ['sort_order' => 0];
    $val = static fn (string $k): string => e((string) ($v[$k] ?? ''));
    $check = static fn (string $k): string => !empty($v[$k]) ? ' checked' : '';
    $h = '<form method="post" action="' . e(url($id ? '/admin/aging/' . $id : '/admin/aging')) . '" enctype="multipart/form-data" data-resize-form class="' . A_CARD . ' grid gap-4 sm:grid-cols-2">' . csrf_field()
        . admin_field('タイトル *', '<input id="title" name="title" required maxlength="150" value="' . $val('title') . '" class="' . A_INPUT . '">', 'title', '例：ヌメ革のコインケース（ナチュラル）')
        . admin_field('使った期間', '<input id="period_label" name="period_label" maxlength="50" value="' . $val('period_label') . '" class="' . A_INPUT . '">', 'period_label', '例：約1年')
        . image_input('before', '使い始めの写真 *', $a['before_key'] ?? null, true)
        . image_input('after', '使い込んだあとの写真 *', $a['after_key'] ?? null, true)
        . '<div class="sm:col-span-2">' . admin_field('ひとこと', '<textarea id="description" name="description" rows="4" maxlength="1000" class="' . A_INPUT . '">' . $val('description') . '</textarea>', 'description', '例：毎日ポケットに入れて使うたら、こがぁな色になりました') . '</div>'
        . admin_field('並び順', '<input id="sort_order" name="sort_order" type="number" value="' . $val('sort_order') . '" class="' . A_INPUT . '">', 'sort_order', '小さい数字ほど上に表示')
        . '<div class="space-y-1"><label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" name="is_real_record" value="1"' . $check('is_real_record') . '> 実際に使って撮った写真です</label>'
        . '<label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" name="is_published" value="1"' . $check('is_published') . '> サイトに公開する</label>'
        . '<p class="text-xs text-muted">公開できるのは、実際に使って撮った写真だけです。</p></div>'
        . '<div class="flex flex-wrap items-center gap-3 sm:col-span-2"><button class="' . A_BTN_PRIMARY . '">保存する</button>'
        . '<a href="' . e(url('/admin/aging')) . '" class="' . A_BTN_GHOST . '">一覧に戻る</a><p data-form-status class="text-sm text-muted" aria-live="polite"></p></div></form>';
    if ($a) {
        $h .= '<form method="post" action="' . e(url('/admin/aging/' . $id . '/delete')) . '" class="mt-6" data-confirm="この比較写真を削除します。よろしいですか？">'
            . csrf_field() . '<button class="' . A_BTN_DANGER . '">この比較写真を削除</button></form>';
    }
    render_admin($a ? '比較写真の編集' : '比較写真の登録', $h, $error);
}

function admin_aging_save(?string $id): void
{
    $old = $id ? db_one('SELECT * FROM leather_aging_photos WHERE id = ?', [$id]) : null;
    if ($id && !$old) {
        admin_not_found();
        exit;
    }
    $in = [
        'title' => post_str('title', 150),
        'period_label' => post_str('period_label', 50),
        'description' => post_str('description', 1000),
        'sort_order' => post_str('sort_order', 6),
        'is_real_record' => ($_POST['is_real_record'] ?? '') === '1' ? 1 : 0,
        'is_published' => ($_POST['is_published'] ?? '') === '1' ? 1 : 0,
    ];
    $keep = ['before_key' => $old['before_key'] ?? null, 'after_key' => $old['after_key'] ?? null];
    $fail = static fn (string $msg) => form_fail($msg, static fn ($m) => admin_aging_form($id, $m, $in + $keep));
    if ($in['title'] === '') {
        $fail('タイトルを入力してください。');
    }
    if ($in['is_published'] && !$in['is_real_record']) {
        $fail('公開できるのは、実際に使って撮った写真だけです。「実際に使って撮った写真です」にチェックを入れてください。');
    }
    $before = save_optional_image('before', 'aging');
    $after = save_optional_image('after', 'aging');
    if ($before === false || $after === false) {
        delete_uploads(array_filter([$before, $after], 'is_string'));
        $fail('写真を読み込めませんでした。JPEG の写真を選んでください。');
    }
    $beforeKey = $before ?? $keep['before_key'];
    $afterKey = $after ?? $keep['after_key'];
    if (!$beforeKey || !$afterKey) {
        delete_uploads(array_filter([$before, $after], 'is_string'));
        $fail('使い始めと、使い込んだあとの写真を両方選んでください。');
    }
    $sort = preg_match('/^-?\d+$/', $in['sort_order']) ? (int) $in['sort_order'] : 0;
    $params = [$in['title'], $beforeKey, $afterKey, $in['period_label'] ?: null, $in['description'] ?: null, $in['is_real_record'], $in['is_published'], $sort];
    if ($old) {
        db_exec('UPDATE leather_aging_photos SET title = ?, before_key = ?, after_key = ?, period_label = ?, description = ?, is_real_record = ?, is_published = ?, sort_order = ? WHERE id = ?', [...$params, $id]);
        delete_uploads(array_filter([$before ? $old['before_key'] : null, $after ? $old['after_key'] : null]));
    } else {
        $id = uuid();
        db_exec('INSERT INTO leather_aging_photos (title, before_key, after_key, period_label, description, is_real_record, is_published, sort_order, id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [...$params, $id]);
    }
    audit($old ? 'aging.update' : 'aging.create', $id, ['title' => $in['title'], 'published' => $in['is_published']]);
    form_done('/admin/aging/' . $id . '?notice=saved');
}

function admin_aging_delete(string $id): void
{
    $a = db_one('SELECT * FROM leather_aging_photos WHERE id = ?', [$id]);
    if ($a) {
        db_exec('DELETE FROM leather_aging_photos WHERE id = ?', [$id]);
        delete_uploads([$a['before_key'], $a['after_key']]);
        audit('aging.delete', $id, ['title' => $a['title']]);
    }
    redirect('/admin/aging?notice=deleted');
}

// ================================================================== これまでに作ったもん（トップページの写真）
function admin_works(?string $error = null): void
{
    $rows = db_all('SELECT * FROM works_photos ORDER BY sort_order, created_at');
    $h = '<form method="post" action="' . e(url('/admin/works')) . '" enctype="multipart/form-data" data-resize-form class="' . A_CARD . ' grid gap-4 sm:grid-cols-2">' . csrf_field()
        . '<div class="sm:col-span-2"><h2 class="font-serif text-lg text-leather">写真を追加する</h2>'
        . '<p class="mt-1 text-sm leading-6 text-muted">トップページの「これまでに作ったもんより」に並ぶ写真です。売り物ではない作品も載せられます。</p></div>'
        . image_input('image', '写真 *', null, true, '縦長（4:5）に切り抜いて表示します')
        . '<div class="space-y-4">' . admin_field('作品名 *', '<input id="caption" name="caption" required maxlength="100" class="' . A_INPUT . '">', 'caption', '例：コインケース（キャメル）')
        . admin_field('写真の説明', '<input id="alt" name="alt" maxlength="200" class="' . A_INPUT . '">', 'alt', '例：キャメル色のコインケースを開いたところ（読み上げ用）') . '</div>'
        . '<label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" name="first" value="1" checked> いちばん左（先頭）に出す</label>'
        . '<div class="flex flex-wrap items-center gap-3 sm:col-span-2"><button class="' . A_BTN_PRIMARY . '">追加する</button><p data-form-status class="text-sm text-muted" aria-live="polite"></p></div></form>';

    $h .= '<section class="mt-6"><h2 class="font-serif text-lg text-leather">登録済みの写真（' . count($rows) . '枚）</h2>'
        . '<p class="mt-1 text-xs text-muted">上から順に、トップページの左から並びます。</p><ul class="mt-3 space-y-3">';
    $last = count($rows) - 1;
    foreach ($rows as $i => $w) {
        $src = $w['image_key'] ? upload_url($w['image_key']) : url($w['static_path']);
        $btn = static fn (string $act, string $label, string $aria, bool $disabled = false): string => '<form method="post" action="' . e(url('/admin/works/' . $w['id'] . '/' . $act)) . '">' . csrf_field()
            . '<button class="inline-flex min-h-10 min-w-10 items-center justify-center rounded border border-line bg-white px-2 text-sm disabled:opacity-30" aria-label="' . e($aria) . '"' . ($disabled ? ' disabled' : '') . '>' . $label . '</button></form>';
        $h .= '<li class="' . A_CARD . ' flex gap-3 !p-3' . ($w['is_published'] ? '' : ' opacity-60') . '">'
            . '<img src="' . e($src) . '" alt="" class="h-24 w-20 shrink-0 rounded object-cover" loading="lazy">'
            . '<div class="min-w-0 flex-1 space-y-2"><form method="post" action="' . e(url('/admin/works/' . $w['id'])) . '" class="space-y-2">' . csrf_field()
            . '<input name="caption" value="' . e($w['caption']) . '" maxlength="100" required aria-label="作品名" class="' . A_INPUT . ' !mt-0">'
            . '<div class="flex items-center gap-3"><label class="flex min-h-10 items-center gap-1 text-sm"><input type="checkbox" name="is_published" value="1"' . ($w['is_published'] ? ' checked' : '') . '> 公開</label>'
            . '<button class="' . A_BTN_GHOST . ' !min-h-10 !px-3 text-sm">保存</button></div></form>'
            . '<div class="flex flex-wrap gap-2">' . $btn('up', '↑', 'ひとつ上へ', $i === 0) . $btn('down', '↓', 'ひとつ下へ', $i === $last)
            . '<form method="post" action="' . e(url('/admin/works/' . $w['id'] . '/delete')) . '" data-confirm="この写真をトップページから削除します。よろしいですか？" class="ml-auto">' . csrf_field()
            . '<button class="min-h-10 px-2 text-sm text-red-700 underline">削除</button></form></div></div></li>';
    }
    $h .= '</ul></section>';
    render_admin('これまでの作品', $h, $error);
}

function admin_works_add(): void
{
    $caption = post_str('caption', 100);
    $alt = post_str('alt', 200);
    $fail = static fn (string $m) => form_fail($m, static fn ($msg) => admin_works($msg));
    if ($caption === '') {
        $fail('作品名を入力してください。');
    }
    $key = save_optional_image('image', 'works');
    if (!$key) {
        $fail('写真を選んでください。');
    }
    $sort = ($_POST['first'] ?? '') === '1'
        ? (int) db_value('SELECT COALESCE(MIN(sort_order), 10) FROM works_photos') - 10
        : (int) db_value('SELECT COALESCE(MAX(sort_order), 0) FROM works_photos') + 10;
    $id = uuid();
    db_exec('INSERT INTO works_photos (id, image_key, caption, alt, sort_order, is_published) VALUES (?, ?, ?, ?, ?, 1)', [$id, $key, $caption, $alt ?: null, $sort]);
    audit('works.add', $id, ['caption' => $caption]);
    form_done('/admin/works?notice=saved');
}

function admin_works_update(string $id): void
{
    $caption = post_str('caption', 100);
    if ($caption !== '') {
        db_exec('UPDATE works_photos SET caption = ?, is_published = ? WHERE id = ?', [$caption, ($_POST['is_published'] ?? '') === '1' ? 1 : 0, $id]);
        audit('works.update', $id, ['caption' => $caption]);
    }
    redirect('/admin/works?notice=saved');
}

function admin_works_move(string $id, int $dir): void
{
    $rows = db_all('SELECT id FROM works_photos ORDER BY sort_order, created_at');
    $ids = array_column($rows, 'id');
    $i = array_search($id, $ids, true);
    $j = $i === false ? false : $i + $dir;
    if ($i !== false && $j >= 0 && $j < count($ids)) {
        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        foreach ($ids as $n => $rid) {
            db_exec('UPDATE works_photos SET sort_order = ? WHERE id = ?', [($n + 1) * 10, $rid]);
        }
    }
    redirect('/admin/works');
}

function admin_works_delete(string $id): void
{
    $w = db_one('SELECT * FROM works_photos WHERE id = ?', [$id]);
    if ($w) {
        db_exec('DELETE FROM works_photos WHERE id = ?', [$id]);
        if ($w['image_key']) {
            delete_uploads([$w['image_key']]);
        }
        audit('works.delete', $id, ['caption' => $w['caption']]);
    }
    redirect('/admin/works?notice=deleted');
}
