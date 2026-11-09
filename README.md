# おっさんのレザークラフト（Nori2023）公式サイト＆EC

エックスサーバー（PHP＋MySQL）で動く、1点もの革小物の販売サイトと管理画面です。
公開URL：**https://mpr-shop.com/nori2023/**

- 設置手順：[docs/SETUP_XSERVER.md](docs/SETUP_XSERVER.md)
- 管理画面マニュアル：[docs/ADMIN_MANUAL.md](docs/ADMIN_MANUAL.md)
- 設計：[docs/DESIGN.md](docs/DESIGN.md)

## フォルダ構成

```
nori2023/          ← この中身をサーバーの public_html/nori2023/ に置く
  index.php        入口（すべてのURLをここで振り分け）
  .htaccess        URL書き換え・非公開フォルダの保護
  .user.ini        PHP設定（アップロード上限など）
  assets/          CSS・JavaScript（app.css は生成済み）
  images/          ブランド画像・作品写真
  uploads/         管理画面からアップロードした画像（サーバー上で増える）
  app/             プログラム本体（ブラウザからは見えない）
    config.php     初回設定で自動作成（DB接続情報。リポジトリには入れない）
    migrations/    データベースの構造（自動で適用）
    routes/ views/ lib/
styles/app.css     デザイン定義（Tailwind CSS の元ファイル）
docs/              手順書・マニュアル
.github/workflows/ GitHub → エックスサーバー 自動アップロード
```

## 開発状況

| STEP | 内容 | 状況 |
|---|---|---|
| 1 | 基本構築・デザイン・トップ・商品一覧・商品詳細・制作者紹介 | ✅ 完了 |
| 2 | 管理画面（ログイン・商品・写真・カテゴリー・送料・ページ設定・操作履歴）＋ Instagram 最新投稿 | ✅ 完了 |
| 3 | 1点もの販売（在庫確保・購入フォーム・決済準備・注文／発送管理） | 未着手（DB設計済み） |
| 4 | 名刺入れ専用LP・注文フォーム・ロゴ入稿 | 未着手（準備中ページ） |
| 5 | 受注制作フロー（制作イメージ承認・決済案内） | 未着手（DB設計済み） |
| 6 | 経年変化・お手入れ・作品集・ワークショップ・お問い合わせ | 未着手（準備中ページ） |
| 7 | テスト・公開準備・法定表示 | 未着手 |

**本番販売は無効です。** すべての機能が完成するまで購入ボタンは押せません。

## デザインを変更したとき（開発者向け）

```bash
npm install
npm run css     # nori2023/assets/app.css を作り直す（サーバーに Node.js は不要）
```

以前の Cloudflare 版は `cloudflare-version` ブランチに残しています。
