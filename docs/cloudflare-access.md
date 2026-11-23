# Cloudflare Access でバックエンドを保護する

a-blog cms のメンテナンスモード(503)による遮断をやめ、**PHP に届く前に Cloudflare のエッジで遮断する**。
a-blog cms に脆弱性があっても、Access を通らないリクエストは PHP まで届かない。

## 全体像

| 誰が | どこへ | どう通すか |
|---|---|---|
| 自分(管理画面の操作) | `backend.uidev.me` 全体 | Access のログイン(メール OTP など)。許可リストは自分のメールだけ |
| フロントの Worker(`/api/*`、プレビュー) | `backend.uidev.me` の API | **Service Token**(`CF-Access-Client-Id` / `CF-Access-Client-Secret`) |
| Workers Builds(静的書き出しのビルド) | 同上 | 同じ Service Token |
| 訪問者のブラウザ(画像など) | `assets.uidev.me` の `/media/` `/archives/` `/storage/` | Access をかけない(DNS only のまま)。ドキュメントルートがこの 3 つへのシンボリックリンクだけなので、PHP には届かない(手順 4) |
| a-blog cms → Cloudflare Deploy Hook(Webhook) | 外向きの通信 | 影響なし |

フロント側は、`CF_ACCESS_CLIENT_ID` / `CF_ACCESS_CLIENT_SECRET` が設定されていれば、API へのリクエストに Service Token のヘッダーを自動で付ける(フロントの `app/lib/acms/index.ts`)。

## 前提の確認

1. `backend.uidev.me` の DNS レコードが **Proxied(オレンジの雲)** であること。DNS only だと、Access は効かない。
2. `assets.uidev.me` が、PHP(a-blog cms)に届かないこと。同じサーバー(Xserver)を指していても、ドキュメントルートが別で、`media` / `archives` / `storage` へのシンボリックリンクだけなら届かない(手順 4 で確認する)。
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

### 4. `assets.uidev.me` には、Access も WAF もかけない

訪問者のブラウザが画像を取るため、`assets.uidev.me` に Access はかけられない。DNS も **DNS only のまま**(Proxied にしない)でよい。

`assets.uidev.me` のドキュメントルートは、バックエンドの `media` / `archives` / `storage` へのシンボリックリンクだけで、PHP(a-blog cms)がない。そのため、`backend` を迂回する入口にはならない。確認結果(2026-11)は次のとおり。

- `/` は、Xserver 標準の静的ページ
- `/login/` `/index.php` `/php/` は、Xserver 標準の 404

これが崩れていないことを、手順 6 の `curl` で確認する。次の場合は、前提が変わるので、見直す。

- ドキュメントルートに、シンボリックリンク以外のファイル(特に `.php`)を置いた
- `assets` を、a-blog cms のドキュメントルートに向け直した
  → この場合は、Cloudflare の WAF(Security → WAF → Custom rules)で、`/media/` `/archives/` `/storage/` 以外を **Block** する(式: `http.host eq "assets.uidev.me" and not starts_with(http.request.uri.path, "/media/") and not starts_with(http.request.uri.path, "/archives/") and not starts_with(http.request.uri.path, "/storage/")`)

> `/storage/` に、公開してはいけないファイルがないか、確認する。`assets` では、`storage` の中身が、誰にでも見える。

### 5. オリジンへの直接アクセスを塞ぐ

Access はエッジで効くので、Cloudflare を通らずにサーバーへ直接届くと、迂回される。**IP 直打ち(`Host: backend.uidev.me` を付けた直接アクセス)で、Access なしに a-blog cms のログイン画面まで届く**ことを、2026-11 に確認した。メンテナンスモードを外す前に、必ず塞ぐ。

サイトのルート(`index.php` と `config.server.php` があるディレクトリ)の `.htaccess` の**一番上**に、次を追記する。ルートの `.htaccess` は環境依存なので、サーバー側で直接編集する(Deploy では同期されない)。**編集前に、元の `.htaccess` をバックアップする。**

```apache
# Cloudflare 以外からの直接アクセスを拒否する(Cloudflare Access の迂回対策)
# IP 範囲: https://www.cloudflare.com/ips/ (変更されることがあるため、定期的に見直す)
# 取得日: 2026-11-24
<RequireAny>
  Require ip 173.245.48.0/20
  Require ip 103.21.244.0/22
  Require ip 103.22.200.0/22
  Require ip 103.31.4.0/22
  Require ip 141.101.64.0/18
  Require ip 108.162.192.0/18
  Require ip 190.93.240.0/20
  Require ip 188.114.96.0/20
  Require ip 197.234.240.0/22
  Require ip 198.41.128.0/17
  Require ip 162.158.0.0/15
  Require ip 104.16.0.0/13
  Require ip 104.24.0.0/14
  Require ip 172.64.0.0/13
  Require ip 131.0.72.0/22
  Require ip 2400:cb00::/32
  Require ip 2606:4700::/32
  Require ip 2803:f800::/32
  Require ip 2405:b500::/32
  Require ip 2405:8100::/32
  Require ip 2a06:98c0::/29
  Require ip 2c0f:f248::/32
</RequireAny>
```

- 追記後、`curl --resolve backend.uidev.me:443:<サーバーのIP> https://backend.uidev.me/login/` が **403** になり、Cloudflare 経由(`https://backend.uidev.me/`)は 302(Access)のままであることを確認する。あわせて、フロントの検索 API(`/api/blog/`)が 200 のままであることも確認する
- 範囲を誤ると、`backend` 全体が 403 になり、フロントのビルドと検索が止まる。その場合はバックアップを戻す
- Cloudflare の IP 範囲は、まれに変わる。年に 1 回ほど、公式のページと見比べる(変わると、Cloudflare 経由のアクセスが 403 になる)
- Xserver の初期ドメイン(`xxxx.xsrv.jp`)は、サイトごとに別のドキュメントルートを持つ場合がある。このサイトのものか、別のサイトのものかを、中身で確認する
- より強くするなら、PHP 側で `Cf-Access-Jwt-Assertion` ヘッダーを検証する方法もあるが、コアは編集しない方針なので、`.htaccess` で足りるかをまず確認する

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

# assets: メディアは取れる。PHP には届かない(Xserver 標準の 404)
curl -sI https://assets.uidev.me/media/ | head -1
curl -s -o /dev/null -w '%{http_code}\n' https://assets.uidev.me/index.php   # 404 になること
curl -s https://assets.uidev.me/login/ | grep -c XSERVER   # 1 以上(Xserver 標準の 404 ページ)

# オリジンへの直接アクセス(IP 直打ち)は 403(サーバーの IP は dig +short backend.uidev.me の値。Proxied 後は、DNS の設定画面で確認する)
curl -s -o /dev/null -w '%{http_code}\n' -k --resolve backend.uidev.me:443:<サーバーのIP> https://backend.uidev.me/login/
```

ブラウザでも、次を確認する。

- `https://backend.uidev.me/login/` で Access のログイン → a-blog cms のログインに進める
- フロントの Workers Builds が成功する(`Retry build`)
- `blog.uidev.me` の検索(`/api/blog/`)と、プレビューが動く
- 記事を保存すると、Deploy Hook でビルドが始まる

### 7. メンテナンスモードを外す

上の確認が全部通ってから、a-blog cms のメンテナンスモードを解除する。

- 解除前に、**Access のポリシーが効いていること**(手順 6 の最初の `curl`)と、**オリジンへの直接アクセスが 403 であること**(手順 5)を確認する。先に外すと、無防備な時間ができる
- 解除後は、`backend.uidev.me` の一般公開への 503 は、Access による遮断に置き換わる(**以降、503 は障害の可能性がある**ので、見たら調べる)

## ロールバック

問題が出たら、Access のアプリケーションを無効化(または削除)し、メンテナンスモードを戻す。フロントは、`CF_ACCESS_*` が設定されたままでも、余分なヘッダーを付けるだけで害はない。

## 注意

- Service Token の Secret が漏れたら、Cloudflare で即座にローテーション(再作成)し、フロントの Secret を更新する
- Service Token を通すと、**API キー以外にも、バックエンド全体へ入れる**。管理画面に入るには、a-blog cms のログインも必要だが、Token は Git・チャットに載せない
- Access を通っても、a-blog cms 自体の更新(脆弱性への対応)は、これまで通り必要
