-- 送料設定の備考を、お客さん向けの言葉にする（管理画面で書いた「配達時 …」の備考だけを置きかえる）
UPDATE shipping_profiles SET note = '郵便受けにお届け（対面での受け取りはありません）'
 WHERE name LIKE 'レターパックライト%' AND (note IS NULL OR note LIKE '配達時%');
UPDATE shipping_profiles SET note = '対面でお届け（受け取りのサインが必要です）'
 WHERE name LIKE 'レターパックプラス%' AND (note IS NULL OR note LIKE '配達時%')
