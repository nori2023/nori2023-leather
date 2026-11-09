# 設計書：おっさんのレザークラフト（Nori2023）

最終更新：2026-11-09（エックスサーバー PHP 版へ移行、STEP 1・2 完了）

## 1. 技術構成

| 区分 | 採用 | 備考 |
|---|---|---|
| サーバー | エックスサーバー（mpr-shop.com/nori2023） | 既存契約・追加費用なし |
| 言語 | PHP 8.1 以上（フレームワークなし） | 依存ライブラリなし。アップロードするだけで動く |
| DB | MySQL / MariaDB（utf8mb4） | 初回設定で自動作成、以後の変更は `app/migrations/` を自動適用 |
| スタイル | Tailwind CSS 4（開発時に生成済みの CSS を同梱） | |
| フォント | Google Fonts（Noto Serif JP / Noto Sans JP） | |
| 画像 | `uploads/`（ブラウザで縮小→サーバーで検証して保存） | 長辺1600px＋640pxの2サイズ |
| 管理者認証 | メール＋パスワード（bcrypt）、セッション | 5回失敗で15分ロック、CSRFトークン |
| 定期処理 | 表示時の遅延実行＋（任意）Cron | Instagram 最新投稿 |
| 反映 | GitHub Actions → SSH（rsync） | または手作業アップロード |

## 2. 画面構成（URL はすべて /nori2023 配下）

```
公開側
  /                     トップ（Instagram 最新投稿6件を含む） ✅
  /shop  ?category=     商品一覧・カテゴリー絞込 ✅
  /shop/{slug}          商品詳細（スワイプ・サムネイル・拡大・SOLD OUT・おっさんからひとこと） ✅
  /about                制作者紹介（本文は管理画面で編集） ✅
  /gallery ほか         準備中ページ（STEP 4〜7）
  /sitemap.xml
  /install              初回設定（設定後は無効）
管理画面（ログイン必須）
  /admin                ダッシュボード ✅
  /admin/products       完成品の登録・編集・写真 ✅
  /admin/categories     カテゴリー ✅
  /admin/shipping       送料設定 ✅
  /admin/settings       トップ画像・コピー・制作者紹介 ✅
  /admin/instagram      Instagram 連携 ✅
  /admin/logs           操作履歴 ✅
  /admin/account        パスワード変更 ✅
```

## 3. データベース

`app/migrations/001_init.sql` に全テーブル（商品・注文・決済・受注制作・発送・ワークショップ・問い合わせ・経年変化・Instagram・管理者・操作履歴）。

### 業務ルール
- 販売中にできる写真枚数：1枚以上（`photo_count_ok()`、app/lib/site.php）
- 売れた商品は削除不可（SOLD OUT作品集に残す）
- お客様向けの文言はすべて広島弁。特定商取引法に基づく表記・プライバシーポリシー・利用規約は正確さを優先して標準語（STEP 7）
- 本番販売（`sales.enabled`）は全機能完成まで false

### 二重販売防止（STEP 3 で実装）
InnoDB のトランザクション内で `SELECT ... FROM products WHERE id = ? FOR UPDATE` → `status = 'on_sale'` を確認 → `reserved` に更新＋`product_reservations` 登録。決済結果をサーバー側で検証してから `sold_out` に確定。

## 4. セキュリティ

- `app/` はブラウザから 403（`.htaccess`）。`config.php` は 0600
- `uploads/` は JPEG/WebP 以外を 403、PHP 等のハンドラーを無効化
- 画像：マジックナンバーと `getimagesizefromstring` で検証、保存名はサーバー側で採番
- SQL はすべてプリペアドステートメント、HTML 出力はすべてエスケープ
- 常に https で表示（http は https へ転送）。HSTS は mpr-shop.com 全体に効くため送らない
- Content-Security-Policy：スクリプトは自サイトのみ、外部は Google Fonts だけ許可（インラインスクリプト不使用）
- 管理画面：パスワード bcrypt、ログイン試行制限（同じIPから5回／同じメールアドレスへ10回の失敗で15分ロック）、セッション固定対策、Cookie は HttpOnly / SameSite=Lax / HTTPS時 Secure、全 POST で CSRF トークン＋Origin 確認、`X-Frame-Options: DENY`、`Cache-Control: no-store`
- 初回設定は設置キー必須・設定後は無効
- 操作履歴（audit_logs）

## 5. 未完了・注意事項

- エックスサーバーの PHP バージョン（mpr-shop.com 全体の設定）が 8.1 以上であること
- お客様へのメール送信（STEP 3 以降）は mpr-shop.com のメールアドレスから送る想定。会社ドメインから個人ブランドのメールを送ってよいか要確認
- 特定商取引法の表記で販売者を明記する必要あり（会社ドメインの下で個人ブランドを販売する点を含め、公開前に確認）
- 入稿ロゴ（STEP 4）は公開フォルダの外（`/home/サーバーID/nori2023_storage/` など）に保存する
