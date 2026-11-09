<?php
/**
 * 定期実行用（任意）。エックスサーバーの「Cron設定」に登録すると6時間ごとに Instagram を更新する。
 * 例：0 0,6,12,18 * * * /usr/bin/php8.2 /home/（サーバーID）/mpr-shop.com/public_html/nori2023/app/cron.php
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/bootstrap.php';
if (!is_installed()) {
    exit(0);
}
$r = ig_sync();
echo date('c') . ' instagram: ' . $r['message'] . PHP_EOL;
