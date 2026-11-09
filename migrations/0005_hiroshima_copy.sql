-- サイトの表現を広島弁にそろえる：トップのサブコピー（未変更の場合のみ更新）
UPDATE site_settings
   SET value = json_set(value, '$.sub', '使やぁ使うほど、ええ味が出てくる革小物じゃけぇ。'),
       updated_at = strftime('%Y-%m-%dT%H:%M:%fZ','now')
 WHERE key = 'home.hero'
   AND json_extract(value, '$.sub') = '使うほどに、ええ味が出てくる革小物。';
