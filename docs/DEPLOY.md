# 公開手順（Cloudflare 無料プラン）

この手順で、月額0円・クレジットカード登録なしで公開できます。
所要時間の目安：初回 30〜45 分（すべてブラウザ操作。スマホでも可）

---

## 全体像

```
お客様 ──▶ Cloudflare Workers（無料）
              ├─ 静的ファイル（CSS・JS・ブランド画像）… 無料・無制限
              ├─ D1 データベース（商品・設定・注文・商品写真）… 無料枠 5GB
              └─ Cloudflare Access（管理画面のログイン）… 無料枠 50人
GitHub ──（更新を自動反映）──▶ Cloudflare
```

| 無料枠 | 上限 | このサイトでの目安 |
|---|---|---|
| Workers リクエスト | 1日10万回 | 1日数千アクセスまで十分 |
| Workers CPU | 1回10ミリ秒 | 画面描画は数ミリ秒（軽量設計） |
| D1 保存容量 | 5GB | 商品写真 約1万枚分 |
| D1 読み込み | 1日500万行 | 十分 |
| プログラム容量 | 3MiB | 現在 約0.18MiB |

上限を超えても請求は発生せず、その日の残りは表示が止まります（翌日 9:00 JST に回復）。

---

## 1. アカウントを作る

1. **GitHub**（https://github.com ）のアカウントを作る（無料）
2. **Cloudflare**（https://dash.cloudflare.com/sign-up ）のアカウントを作る（無料・カード不要）

## 2. プログラムを GitHub に置く

- 新しい **非公開（Private）リポジトリ** `nori2023-leather` を作り、このフォルダーの中身をアップロードします。
- Claude に GitHub を接続していただければ、アップロードは Claude が行えます。

## 3. データベース（D1）を作る

1. Cloudflare ダッシュボード → **ストレージとデータベース → D1** → **作成**
2. 名前：`nori2023-db`（場所は自動で可）
3. 作成後に表示される **データベースID** をコピー
4. GitHub 上で `wrangler.jsonc` を開き、`database_id` の `00000000-...` をコピーしたIDに書き換えて保存

## 4. Workers と GitHub をつなぐ

1. Cloudflare ダッシュボード → **Workers & Pages → 作成 → リポジトリをインポート**（Import a repository）
2. GitHub を接続し、`nori2023-leather` を選ぶ
3. 設定
   - **ビルドコマンド**：`npm run build`
   - **デプロイコマンド**：`npx wrangler d1 migrations apply DB --remote && npx wrangler deploy`
4. 「保存してデプロイ」
5. 完了すると `https://nori2023-leather.<アカウント名>.workers.dev` のようなURLが表示されます。これが公開URLです。

以後は GitHub のファイルを更新するたびに、自動で反映されます。

## 5. 公開URLを設定する

GitHub 上で `wrangler.jsonc` の `SITE_URL` を、手順4で表示された公開URLに書き換えます（OGP・サイトマップに使います）。

## 6. 管理画面のログイン（Cloudflare Access）を設定する

管理画面 `/admin` は、この設定をするまで **誰も入れない** 状態になっています（安全のため）。

1. Cloudflare ダッシュボード → **Zero Trust**（初回はチーム名を決める。例：`nori2023`。無料プランを選択）
2. **Access → アプリケーション → 追加 → セルフホスト**
   - アプリケーション名：`管理画面`
   - ドメイン：手順4の公開URLのホスト名、パス：`admin`
   - ログイン方法：**ワンタイムPIN（メールでコードが届く）**
3. ポリシー：**許可（Allow）** → ルール「メールアドレス」に管理者のメールアドレスを入力
4. 保存後、アプリケーションの **概要** にある **アプリケーション対象者（AUD）タグ** をコピー
5. GitHub 上で `wrangler.jsonc` の `vars` を設定して保存
   ```jsonc
   "ACCESS_TEAM_DOMAIN": "nori2023.cloudflareaccess.com",   // チーム名.cloudflareaccess.com
   "ACCESS_AUD": "コピーしたAUDタグ",
   "ADMIN_EMAILS": "管理者のメールアドレス"
   ```
6. 反映後、`公開URL/admin` を開く → メールアドレスを入力 → 届いたコードを入力 → 管理画面に入れます

> Access のログインと、プログラム側のチェック（AUD・メールアドレス）の二重で守っています。

## 7. Instagram 連携（任意）

手順は `docs/ADMIN_MANUAL.md` の「Instagram 連携」を参照してください。設定しない場合、トップページには Instagram へのリンクだけが表示されます。

---

## あとから有料・オプションに切り替える場合

| やりたいこと | 方法 | 費用 |
|---|---|---|
| 独自ドメインで公開（例：nori2023.jp） | ドメインを取得し Cloudflare に追加 → Workers の「カスタムドメイン」に設定 | 年1,500円前後 |
| 画像を R2 に保存 | R2 を有効化（**カード登録が必要**、10GBまで無料）→ バケット `nori2023-media` を作成 → `wrangler.jsonc` の `r2_buckets` のコメントを外す | 10GBまで0円 |
| アクセスが多くなった | Workers Paid プラン | 月5ドル |

## お客様へのメール送信について（STEP 3 以降で必要）

注文確認メールなどをお客様へ送るには、送信元となる **独自ドメイン** がほぼ必須です（無料のメール送信サービスも、迷惑メール扱いを避けるためドメイン認証を求めます）。販売開始までに独自ドメイン（年1,500円前後）の取得をおすすめします。管理者への通知メールだけなら、ドメインなしでも Cloudflare の機能で無料で送れます。

## ローカルで動かす（開発者向け）

```bash
npm install
cp .dev.vars.example .dev.vars        # localhost では管理画面の認証を省略
npm run db:migrate:local
npm run dev                            # http://localhost:8787
```
