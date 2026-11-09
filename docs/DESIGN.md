# 設計書：おっさんのレザークラフト（Nori2023）

最終更新：2026-11-08（Cloudflare 無料プラン構成へ移行、STEP 1・2 完了）

## 1. 技術スタック

| 区分 | 採用 | 理由 |
|---|---|---|
| 実行環境 | Cloudflare Workers（無料プラン） | 商用利用可・月額0円・停止なし |
| フレームワーク | Hono 4（サーバー側でHTMLを生成） | 軽量（CPU 10ms・3MiB 制限に収まる）。現在 gzip 177KiB |
| 言語 | TypeScript（JSX） | |
| スタイル | Tailwind CSS 4（CLIで事前生成） | STEP 1 のデザインをそのまま移植 |
| DB | Cloudflare D1（SQLite） | 無料5GB |
| 画像保存 | D1（既定）／R2（任意） | R2 は有効化にカード登録が必要なため既定は D1 |
| 管理者認証 | Cloudflare Access（Zero Trust 無料）＋ Worker 側で JWT 検証 | パスワード管理不要・二要素相当 |
| フォント | Google Fonts（Noto Serif JP / Noto Sans JP） | |
| 定期処理 | Cron Trigger（6時間ごと） | Instagram 最新投稿の取得 |

旧構成（Next.js＋Supabase＋Vercel）は、本番運用に月額約45ドル必要なため不採用。

## 2. 画面構成

```
公開側（サーバー描画）
  /                         トップ ..................... ✅（Instagram 最新投稿6件を含む）
  /shop  /shop?category=    商品一覧・カテゴリー絞込 ... ✅
  /shop/:slug               商品詳細 ................... ✅（スワイプ・サムネイル・拡大・SOLD OUT）
  /about                    制作者紹介 ................. ✅（本文は管理画面で編集可）
  /gallery ほか             準備中ページ ............... STEP 4〜7
  /media/*                  画像配信（D1/R2 → エッジキャッシュ）
  /robots.txt /sitemap.xml
管理画面（Cloudflare Access 必須）
  /admin                    ダッシュボード ............. ✅
  /admin/products           完成品の登録・編集・写真 ... ✅
  /admin/categories         カテゴリー ................. ✅
  /admin/shipping           送料設定 ................... ✅
  /admin/settings           トップ画像・コピー・制作者紹介 ✅
  /admin/instagram          Instagram 連携 ............. ✅
  /admin/logs               操作履歴 ................... ✅
```

## 3. データベース（migrations/）

- `0001_init.sql`：全テーブル（商品・注文・決済・受注制作・発送・ワークショップ・問い合わせ・経年変化・操作履歴）
- `0002_instagram.sql`：Instagram トークン・投稿キャッシュ
- `0003_media_objects.sql`：画像の保存先（D1）
- `0004_sold_note.sql`：SOLD OUT 後のコメント（おっさんからひとこと）
- `0005_hiroshima_copy.sql`：トップのサブコピーを広島弁に更新

### 業務ルール
- 販売中にできる写真枚数：1枚以上（`photoCountOk`、src/lib/types.ts で一元管理）
- お客様向けの文言はすべて広島弁。特定商取引法に基づく表記・プライバシーポリシー・利用規約など法定表示は、正確さを優先して標準語で作成する（STEP 7）

### 二重販売防止（STEP 3 で実装予定）
D1 は対話的トランザクションがないため、次の2段で担保する。
1. `UPDATE products SET status='reserved' WHERE id=? AND status='on_sale'` の **条件付き更新**（1文で原子的）。更新件数が1のときだけ確保成功
2. `product_reservations` の部分ユニーク索引（有効な確保は1商品1件）
確保・注文作成は `db.batch()`（原子的に実行）でまとめる。

## 4. フォルダー構成

```
src/
  index.tsx            エントリ（ルーティング・エラー処理・Cron）
  env.ts               環境変数とバインディングの型
  lib/
    db.ts              DB アクセス
    auth.ts            管理者認証（Access JWT 検証）
    media.ts           画像保存（D1/R2 切替）
    instagram.ts       Instagram 同期
    site.ts types.ts format.ts
  routes/
    public.tsx         公開ページ
    admin.tsx          管理画面
  views/
    layout.tsx components.tsx admin-layout.tsx
styles/app.css         Tailwind 入力（デザイン定義）
public/                静的ファイル（Worker を通さず無料配信）
  assets/ site.js admin.js app.css（生成）
  images/ brand/ site/ works/
migrations/            D1 スキーマ
reference-images/      公開しない参考画像（デザイン参考・確認待ちの画像）
docs/                  設計書・公開手順・管理マニュアル
```

## 5. セキュリティ

- 管理画面：Cloudflare Access（メールのワンタイムコード）＋ Worker で JWT の署名・発行元・AUD・メールアドレスを検証。未設定時は 503（誰も入れない）
- CSRF：管理画面の POST は Origin を検証（外部サイトからは 403）
- 画像アップロード：ブラウザで縮小・再エンコード（位置情報等のメタデータは消える）＋ サーバーでマジックナンバー検証（JPEG/WebP のみ）・サイズ上限
- 公開画像の配信は `products/` `site/` `instagram/` のみ許可
- セキュリティヘッダー（nosniff・Referrer-Policy・X-Frame-Options 等）、管理画面は no-store
- Instagram トークンは D1 に保存し画面に再表示しない
- 操作履歴（audit_logs）を記録
- カード番号は保持しない（決済はゼウス側で完結予定）

## 6. 未完了・注意事項

- **お客様へのメール送信には独自ドメインがほぼ必須**（STEP 3 以降）
- 入稿ロゴ（最大20MB、STEP 4）は D1 の1行2MB制限を超えるため、分割保存するか R2（カード登録）を使うかを STEP 4 で決める
- D1 に保存した画像の読み出しは CPU を使うため、エッジキャッシュで負荷を抑えている。アクセス増で表示が不安定になった場合は R2 へ切り替え
- 本番販売（`sales.enabled`）は全機能完成まで false のまま
