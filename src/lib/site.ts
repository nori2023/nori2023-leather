export const SITE = {
  name: "おっさんのレザークラフト",
  brand: "Nori2023",
  catch: "おっさんが、ひとつひとつ作っとります。",
  sub: "使やぁ使うほど、ええ味が出てくる革小物じゃけぇ。",
  description:
    "「おっさんが、ひとつひとつ作っとります。」手仕事で仕立てた1点ものの革小物と、名前やロゴを刻むヌメ革のオリジナル名刺ケース。広島のNori2023（おっさんのレザークラフト）公式サイトじゃけぇ。",
  instagram: { handle: "@nori2023_", url: "https://www.instagram.com/nori2023_/" },
} as const;

export const MAIN_NAV = [
  { href: "/shop", label: "販売中の作品" },
  { href: "/gallery", label: "SOLD OUT作品集" },
  { href: "/custom-cardcase", label: "オリジナル名刺入れ" },
  { href: "/about", label: "制作者紹介" },
  { href: "/leather-aging", label: "革の経年変化" },
  { href: "/workshop", label: "ワークショップ" },
  { href: "/contact", label: "お問い合わせ" },
] as const;

export const FOOTER_NAV = [
  { href: "/leather-care", label: "革のお手入れ" },
  { href: "/order", label: "オーダーメイド相談" },
  { href: "/legal/shipping", label: "配送・送料" },
  { href: "/legal/returns", label: "返品・交換・キャンセル" },
  { href: "/legal/tokushoho", label: "特定商取引法に基づく表記" },
  { href: "/legal/privacy", label: "プライバシーポリシー" },
  { href: "/legal/terms", label: "利用規約" },
] as const;

/** これまでの作品写真（作品紹介用。販売商品ではない） */
export const WORKS = [
  { src: "/images/works/coincase-natural-1.jpg", alt: "ナチュラルのヌメ革コインケース", caption: "コインケース（ナチュラル）" },
  { src: "/images/works/coincase-red-1.jpg", alt: "赤い革のコインケース", caption: "コインケース（レッド）" },
  { src: "/images/works/coincase-brown-open.jpg", alt: "ダークブラウンのコインケースを開いたところ", caption: "コインケース（ブラウン）" },
  { src: "/images/works/keyholder-4colors.jpg", alt: "4色の編み込みレザーキーホルダー", caption: "編み込みキーホルダー" },
  { src: "/images/works/leather-box.jpg", alt: "鋲留めのヌメ革ボックス", caption: "ヌメ革のボックス" },
  { src: "/images/works/backpack-collage.jpg", alt: "ヌメ革のリュックサックと細部", caption: "ヌメ革のリュック" },
  { src: "/images/works/wallet-open.jpg", alt: "二つ折り財布の内側", caption: "二つ折り財布" },
];
