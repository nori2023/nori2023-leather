-- ワークショップ：予約先URLと料金の補足（Instagram特典など）を追加し、定員を任意にする
ALTER TABLE workshop_events
  ADD COLUMN fee_note VARCHAR(200) NULL AFTER fee,
  ADD COLUMN reserve_url VARCHAR(500) NULL AFTER fee_note,
  MODIFY capacity INT NULL;

-- 2026年12月12日 ゆめテラス祇園のワークショップ（写真は管理画面から追加）
INSERT IGNORE INTO workshop_events
  (id, title, item_name, image_key, event_date, start_time, end_time, place, fee, fee_note, reserve_url, capacity, accepting, description, is_published)
VALUES
  ('3f6c2a1e-0d4b-4c51-9a77-20261212a001',
   '好きな色を選んで、世界にひとつだけのキーホルダーを作ってみんさい！',
   '縫わずに作れるオリジナルレザーキーホルダー',
   NULL, '2026-12-12', '10:00:00', '16:00:00', 'ゆめテラス祇園 3階', 1000,
   'Instagram特典で800円（税込）', 'https://mpr-shop.com/workshop-te-to-te/', NULL, 1,
   '今回は、縫わずに作れるオリジナルのレザーキーホルダーです。革のパーツを組み合わせて、編み込むだけ！\n\nナチュラル、ブラウン、ネイビー、ワインなど、いろんな色を用意しとるけぇ、好きな色を選んでみてね。単色でシンプルに仕上げるのもよし！いろんな色を組み合わせるのもよし！同じ形でも、色が違えば雰囲気もガラッと変わるんよね。\n\nお子さんから大人まで楽しめるけぇ、親子での参加も大歓迎です！\n\n【Instagram投稿でお得に】\n通常参加費1,000円のところ……\n① @nori2023_ をフォロー\n② 完成した作品を撮影\n③ @nori2023_ をタグ付けして、Instagramに投稿してもろうたら……参加費が800円になります！\n\n事前予約ができるけぇ、ぜひ予約して遊びに来てくださいね。\n\nそして、実はちょっと心配なことが……前日は会社の忘年会なんよね。翌日は朝からワークショップじゃけぇ、飲みすぎんようにせんといけん！……まあ、毎年そう思いながら飲んでしまうんじゃけど（笑）当日は元気いっぱいでお待ちしとります！たぶん。\n\n自分で作ったキーホルダーは、きっと愛着もひとしお！ぜひ気軽に遊びに来てくださいね。\n\n開催場所：ゆめテラス祇園（@youmeterrace_gion）\nワークショップイベント：te to te（@workshop_te_to_te）',
   1)
