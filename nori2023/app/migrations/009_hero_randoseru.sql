-- トップページの写真を、工房のランドセル（初期画像）に切り替える。
-- 以前に管理画面でアップロードした写真の指定だけを外す（キャッチコピー・サブコピーはそのまま）。
-- このあと管理画面の「ページ設定」から写真を選べば、またそちらが表示される。
UPDATE site_settings SET value = JSON_REMOVE(value, '$.imageKey') WHERE `key` = 'home.hero' AND JSON_VALID(value) AND JSON_EXTRACT(value, '$.imageKey') IS NOT NULL
