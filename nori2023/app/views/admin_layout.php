<?php
declare(strict_types=1);

const ADMIN_NAV = [
    ['/admin', 'ダッシュボード'],
    ['/admin/products', '完成品（1点もの）'],
    ['/admin/categories', 'カテゴリー'],
    ['/admin/shipping', '送料設定'],
    ['/admin/workshops', 'ワークショップ'],
    ['/admin/aging', '経年変化'],
    ['/admin/settings', 'ページ設定'],
    ['/admin/instagram', 'Instagram連携'],
    ['/admin/logs', '操作履歴'],
    ['/admin/account', 'アカウント'],
];

const ADMIN_NOTICES = [
    'saved' => '保存しました。',
    'created' => '登録しました。',
    'deleted' => '削除しました。',
    'synced' => 'Instagramを更新しました。',
    'password' => 'パスワードを変更しました。',
];

const A_CARD = 'rounded-md border border-line bg-white p-4 sm:p-6';
const A_INPUT = 'mt-1 block w-full rounded border border-line bg-white px-3 py-2.5 text-[16px] focus:border-camel focus:outline-none';
const A_BTN = 'inline-flex min-h-11 items-center justify-center rounded px-5 text-sm font-medium';
const A_BTN_PRIMARY = A_BTN . ' bg-leather text-ivory hover:bg-charcoal';
const A_BTN_GHOST = A_BTN . ' border border-line bg-white text-charcoal hover:border-camel';
const A_BTN_DANGER = A_BTN . ' border border-red-300 bg-white text-red-700 hover:bg-red-50';

function admin_field(string $title, string $control, string $name = '', string $hint = ''): string
{
    return '<div><label class="block text-sm font-medium text-charcoal"' . ($name ? ' for="' . e($name) . '"' : '') . '>' . e($title) . '</label>'
        . $control . ($hint ? '<p class="mt-1 text-xs text-muted">' . e($hint) . '</p>' : '') . '</div>';
}

function render_admin(string $title, string $body, ?string $error = null, bool $bare = false): void
{
    send_security_headers(true);
    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex, nofollow');
    $path = request_path();
    $notice = ADMIN_NOTICES[query_str('notice', 20)] ?? null;
    $admin = current_admin();
    ?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="base-path" content="<?= e(base_path()) ?>">
<?php if ($admin): ?><meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<?php endif ?>
<title><?= e($title) ?>｜管理画面｜<?= e(SITE_NAME) ?></title>
<link rel="icon" href="<?= e(url('/favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(asset('assets/app.css')) ?>">
</head>
<body class="admin min-h-screen bg-paper text-[15px] text-charcoal">
<header class="sticky top-0 z-30 border-b border-line bg-charcoal text-ivory">
  <div class="mx-auto flex h-14 max-w-6xl items-center justify-between px-4">
    <a href="<?= e(url('/admin')) ?>" class="font-serif tracking-[0.1em]">管理画面</a>
    <div class="flex items-center gap-3 text-xs text-ivory/70">
      <?php if ($admin): ?><span class="hidden sm:inline"><?= e($admin['email']) ?></span><?php endif ?>
      <a href="<?= e(url('/')) ?>" target="_blank" class="rounded border border-ivory/30 px-2 py-1 text-ivory">サイトを見る ↗</a>
      <?php if ($admin): ?>
      <form method="post" action="<?= e(url('/admin/logout')) ?>"><?= csrf_field() ?><button class="rounded border border-ivory/30 px-2 py-1 text-ivory">ログアウト</button></form>
      <?php endif ?>
    </div>
  </div>
  <?php if ($admin && !$bare): ?>
  <nav class="no-scrollbar overflow-x-auto border-t border-ivory/10" aria-label="管理メニュー">
    <ul class="mx-auto flex w-max max-w-6xl gap-1 px-2">
      <?php foreach (ADMIN_NAV as [$p, $label]):
          $active = $p === '/admin' ? $path === '/admin' : str_starts_with($path, $p); ?>
      <li><a href="<?= e(url($p)) ?>"<?= $active ? ' aria-current="page"' : '' ?> class="inline-flex min-h-11 items-center whitespace-nowrap px-3 text-sm <?= $active ? 'border-b-2 border-gold text-gold' : 'text-ivory/80' ?>"><?= e($label) ?></a></li>
      <?php endforeach ?>
    </ul>
  </nav>
  <?php endif ?>
</header>
<main class="mx-auto max-w-6xl px-4 pb-24 pt-6">
  <h1 class="mb-5 font-serif text-xl text-leather"><?= e($title) ?></h1>
  <?php if ($notice): ?><p role="status" class="mb-5 rounded border border-green-700/30 bg-green-50 px-4 py-3 text-sm text-green-900"><?= e($notice) ?></p><?php endif ?>
  <?php if ($error): ?><p role="alert" class="mb-5 rounded border border-red-700/30 bg-red-50 px-4 py-3 text-sm text-red-900"><?= e($error) ?></p><?php endif ?>
  <?= $body ?>
</main>
<script src="<?= e(asset('assets/admin.js')) ?>" defer></script>
</body>
</html>
<?php
}

function status_badge(string $status): string
{
    $color = [
        'draft' => 'bg-gray-100 text-gray-700',
        'on_sale' => 'bg-green-100 text-green-800',
        'reserved' => 'bg-amber-100 text-amber-900',
        'sold_out' => 'bg-charcoal text-ivory',
        'suspended' => 'bg-red-100 text-red-800',
    ][$status] ?? '';
    return '<span class="shrink-0 rounded px-2 py-1 text-xs ' . $color . '">' . e(STATUS_LABEL[$status] ?? $status) . '</span>';
}
