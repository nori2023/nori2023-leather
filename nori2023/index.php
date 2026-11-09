<?php
/**
 * おっさんのレザークラフト（Nori2023）
 * すべてのページの入口。URL を見て、公開ページ・管理画面・初回設定に振り分ける。
 * ※ このファイルだけは古い PHP でも読めるように書いてある（バージョン確認のため）
 */
if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    header('Content-Type: text/plain; charset=utf-8', true, 500);
    echo 'このサイトは PHP 8.1 以上が必要です（現在 ' . PHP_VERSION . '）。サーバーパネルの「PHP Ver.切替」で変更してください。';
    exit;
}

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/router.php';
