# おっさんのレザークラフト（Nori2023）公式サイト＆EC

Cloudflare 無料プラン（Workers ＋ D1 ＋ Access）で動く、1点もの革小物の販売サイトと管理画面です。

- 公開手順：[docs/DEPLOY.md](docs/DEPLOY.md)
- 管理画面マニュアル：[docs/ADMIN_MANUAL.md](docs/ADMIN_MANUAL.md)
- 設計：[docs/DESIGN.md](docs/DESIGN.md)

## 開発状況

| STEP | 内容 | 状況 |
|---|---|---|
| 1 | 基本構築・デザイン・トップ・商品一覧・商品詳細・制作者紹介 | ✅ 完了 |
| 2 | 管理画面（認証・商品・画像・カテゴリー・送料・ページ設定・操作履歴）＋ Instagram 最新投稿 | ✅ 完了 |
| 3 | 1点もの販売（在庫確保・購入フォーム・決済準備・注文／発送管理） | 未着手（DB設計済み） |
| 4 | 名刺入れ専用LP・注文フォーム・ロゴ入稿 | 未着手（準備中ページ） |
| 5 | 受注制作フロー（制作イメージ承認・決済案内） | 未着手（DB設計済み） |
| 6 | 経年変化・お手入れ・作品集・ワークショップ・お問い合わせ | 未着手（準備中ページ） |
| 7 | テスト・公開準備・法定表示 | 未着手 |

**本番販売は無効です。** すべての機能が完成するまで購入ボタンは押せません。

## 開発

```bash
npm install
cp .dev.vars.example .dev.vars
npm run db:migrate:local
npm run dev        # http://localhost:8787 （/admin は localhost のみ認証省略）
```
