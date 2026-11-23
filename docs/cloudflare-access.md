# Cloudflare Access でバックエンドを保護する

a-blog cms のメンテナンスモード(503)による遮断をやめ、**PHP に届く前に Cloudflare のエッジで遮断する**。
a-blog cms に脆弱性があっても、Access を通らないリクエストは PHP まで届かない。

## 全体像

| 誰が | どこへ | どう通すか |
|---|---|---|
| 自分(管理画面の操作) | `backend.uidev.me` 全体 | Access のログイン(メール OTP など)。許可リストは自分のメールだけ |
| フロントの Worker(`/api/*`、プレビュー) | `backend.uidev.me` の API | **Service Token**(`CF-Access-Client-Id` / `CF-Access-Client-Secret`) |
| Workers Builds(静的書き出しのビルド) | 同上 | 同じ Service Token |
| 訪問者のブラウザ(画像など) | `assets.uidev.me` の `/media/` `/archives/` `/storage/` | Access をかけない。ただし、**これ以外のパスは遮断する**(後述) |
| a-blog cms → Cloudflare Deploy Hook(Webhook) | 外向きの通信 | 影響なし |

フロント側は、`CF_ACCESS_CLIENT_ID` / `CF_ACCESS_CLIENT_SECRET` が設定されていれば、API へのリクエストに Service Token のヘッダーを自動で付ける(フロントの `app/lib/acms/index.ts`)。

## 前提の確認

1. `backend.uidev.me` の DNS レコードが **Proxied(オレンジの雲)** であること。DNS only だと、Access は効かない。
2. `assets.uidev.me` が、同じサーバー(Xserver)を指しているか。**同じオリジンなら、`assets` 側のホスト名からも PHP(a-blog cms)に届いてしまう**ので、手順 4 を必ず行う。
3. Xserver の初期ドメイン(`xxxx.xsrv.jp` など)や、サーバーの IP から、同じサイトが見えないこと。見えるなら、Access を迂回される(手順 5)。

## 手順

### 1. Access のアプリケーションを作る(Zero Trust ダッシュボード)

Zero Trust → Access → Applications → Add an application → **Self-hosted**

- Application domain: `backend.uidev.me`(パスは空 = 全体を保護)
- Session duration: 24 時間など(管理画面を長時間開くなら長めに)
- Identity providers: One-time PIN(メール)か、Google など。まずは One-time PIN が簡単

### 2. ポリシーを 2 つ作る(順序が大事)

| 順 | 名前 | Action | 条件 |
|---|---|---|---|
| 1 | Admin | **Allow** | Emails: 自分のメールアドレス |
| 2 | Frontend | **Service Auth** | Include: Service Token(手順 3 で作るもの) |

> Service Token を使うポリシーの Action は **Service Auth**(Allow ではない)。Allow にすると、トークンが通らない。

### 3. Service Token を作る

Zero Trust → Access → Service Auth → Create Service Token

- 名前: `my-blog-site-frontend`
- Duration: 既定は 1 年。**期限が切れると、ビルドと Worker が突然 API を取れなくなる**。期限の 1 か月前に、カレンダーで更新を忘れないようにする
- `Client ID` と `Client Secret` を控える(**Secret は、この画面でしか表示されない**。チャットや Git に載せない)

フロントに登録する(詳細は、フロントの `docs/cloudflare-workers.md`)。

- Workers Builds の **Build variables and secrets**: `CF_ACCESS_CLIENT_ID`、`CF_ACCESS_CLIENT_SECRET`(Secret)
- Worker の Secret(自分のターミナルで入力):

  ```bash
  printf '%s' "$CF_ACCESS_CLIENT_ID" | npx wrangler secret put CF_ACCESS_CLIENT_ID
  printf '%s' "$CF_ACCESS_CLIENT_SECRET" | npx wrangler secret put CF_ACCESS_CLIENT_SECRET
  ```

### 4. `assets.uidev.me` は、メディア系のパスだけを通す

訪問者のブラウザが画像を取るため、`assets.uidev.me` に Access はかけられない。代わりに、Cloudflare の WAF(Security → WAF → Custom rules)で、**メディア以外を遮断**する。

- 式: `(http.host eq "assets.uidev.me" and not starts_with(http.request.uri.path, "/media/") and not starts_with(http.request.uri.path, "/archives/") and not starts_with(http.request.uri.path, "/storage/"))`
- Action: **Block**

これで、`assets.uidev.me/login/` や `/index.php` のような PHP への入口は、エッジで止まる。

> `/storage/` に公開してはいけないファイルがないか、事前に確認する。

### 5. オリジンへの直接アクセスを塞ぐ

Access はエッジで効くので、Cloudflare を通らずにサーバーへ直接届くと、迂回される。

- Xserver の初期ドメイン / IP 直打ちで、サイトが表示されないことを確認する
- 表示されるなら、ルートの `.htaccess` で、Cloudflare の IP 範囲(<https://www.cloudflare.com/ips/>)以外を 403 にする(ルートの `.htaccess` は環境依存なので、サーバー側で直接編集する。Deploy では同期されない)
- より強くするなら、PHP 側で `Cf-Access-Jwt-Assertion` ヘッダーの有無を見る方法もあるが、コアは編集しない方針なので、`.htaccess` で足りるかをまず確認する

### 6. 動作確認(メンテナンスモードを外す前)

```bash
# Access のログインへ飛ばされる(302 / 認証ページ)。PHP には届いていない
curl -sI https://backend.uidev.me/ | head -5

# Service Token があれば通る(API)。値は自分のシェルで設定しておく
curl -s -o /dev/null -w '%{http_code}\n' \
  -H "CF-Access-Client-Id: $CF_ACCESS_CLIENT_ID" \
  -H "CF-Access-Client-Secret: $CF_ACCESS_CLIENT_SECRET" \
  -H "X-API-KEY: $ACMS_API_KEY" \
  'https://backend.uidev.me/api/summary_top_index/'

# assets: メディアは取れる、それ以外は遮断される
curl -sI https://assets.uidev.me/media/ | head -1
curl -sI https://assets.uidev.me/login/ | head -1   # 403 になること
```

ブラウザでも、次を確認する。

- `https://backend.uidev.me/login/` で Access のログイン → a-blog cms のログインに進める
- フロントの Workers Builds が成功する(`Retry build`)
- `blog.uidev.me` の検索(`/api/blog/`)と、プレビューが動く
- 記事を保存すると、Deploy Hook でビルドが始まる

### 7. メンテナンスモードを外す

上の確認が全部通ってから、a-blog cms のメンテナンスモードを解除する。

- 解除前に、**Access のポリシーが効いていること**(手順 6 の最初の `curl`)を確認する。先に外すと、無防備な時間ができる
- 解除後は、`backend.uidev.me` の一般公開への 503 は、Access による遮断に置き換わる(**以降、503 は障害の可能性がある**ので、見たら調べる)

## ロールバック

問題が出たら、Access のアプリケーションを無効化(または削除)し、メンテナンスモードを戻す。フロントは、`CF_ACCESS_*` が設定されたままでも、余分なヘッダーを付けるだけで害はない。

## 注意

- Service Token の Secret が漏れたら、Cloudflare で即座にローテーション(再作成)し、フロントの Secret を更新する
- Service Token を通すと、**API キー以外にも、バックエンド全体へ入れる**。管理画面に入るには、a-blog cms のログインも必要だが、Token は Git・チャットに載せない
- Access を通っても、a-blog cms 自体の更新(脆弱性への対応)は、これまで通り必要
