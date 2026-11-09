<?php
declare(strict_types=1);

const SITE_NAME = 'おっさんのレザークラフト';
const SITE_BRAND = 'Nori2023';
const SITE_CATCH = 'おっさんが、ひとつひとつ作っとります。';
const SITE_SUB = '使やぁ使うほど、ええ味が出てくる革小物じゃけぇ。';
const SITE_DESCRIPTION = '「おっさんが、ひとつひとつ作っとります。」手仕事で仕立てた1点ものの革小物と、名前やロゴを刻むヌメ革のオリジナル名刺ケース。広島のNori2023（おっさんのレザークラフト）公式サイトじゃけぇ。';
const INSTAGRAM_HANDLE = '@nori2023_';
const INSTAGRAM_URL = 'https://www.instagram.com/nori2023_/';

const MAIN_NAV = [
    ['/shop', '販売中の作品'],
    ['/gallery', 'SOLD OUT作品集'],
    ['/custom-cardcase', 'オリジナル名刺入れ'],
    ['/about', '制作者紹介'],
    ['/leather-aging', '革の経年変化'],
    ['/workshop', 'ワークショップ'],
    ['/contact', 'お問い合わせ'],
];

const FOOTER_NAV = [
    ['/leather-care', '革のお手入れ'],
    ['/order', 'オーダーメイド相談'],
    ['/legal/shipping', '配送・送料'],
    ['/legal/returns', '返品・交換・キャンセル'],
    ['/legal/tokushoho', '特定商取引法に基づく表記'],
    ['/legal/privacy', 'プライバシーポリシー'],
    ['/legal/terms', '利用規約'],
];

/** これまでの作品写真（作品紹介用。販売商品ではない） */
const WORKS = [
    ['/images/works/coincase-natural-1.jpg', 'ナチュラルのヌメ革コインケース', 'コインケース（ナチュラル）'],
    ['/images/works/coincase-red-1.jpg', '赤い革のコインケース', 'コインケース（レッド）'],
    ['/images/works/coincase-brown-open.jpg', 'ダークブラウンのコインケースを開いたところ', 'コインケース（ブラウン）'],
    ['/images/works/keyholder-4colors.jpg', '4色の編み込みレザーキーホルダー', '編み込みキーホルダー'],
    ['/images/works/leather-box.jpg', '鋲留めのヌメ革ボックス', 'ヌメ革のボックス'],
    ['/images/works/backpack-collage.jpg', 'ヌメ革のリュックサックと細部', 'ヌメ革のリュック'],
    ['/images/works/wallet-open.jpg', '二つ折り財布の内側', '二つ折り財布'],
];

const STATUS_LABEL = [
    'draft' => '非公開',
    'on_sale' => '販売中',
    'reserved' => '購入手続き中',
    'sold_out' => 'SOLD OUT',
    'suspended' => '販売停止',
];
const PUBLIC_STATUSES = ['on_sale', 'reserved', 'sold_out'];

/** 販売中にできる写真の枚数ルール：1枚以上（偶数・奇数は問わない） */
function photo_count_ok(int $n): bool
{
    return $n >= 1;
}
const PHOTO_RULE_TEXT = '販売中にするには、写真が1枚以上必要です';
