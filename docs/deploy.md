# デプロイ / 本番移行手順

## 重要: 本番で `git pull` をしない

コアを Git の追跡から外したコミットを、本番サーバーで `git pull` すると、
**サーバー上のコア(`php/`、`js/`、`themes/system` など)が削除されます**。
本番の更新は GitHub Actions の rsync に切り替え、サーバー側の `git pull` は廃止します。

## A. 初回の移行手順

> すべて実施してから master に反映します。この手順を終えるまで、`DEPLOY_ENABLED` は設定しないでください。

1. **バックアップ(サーバー)**
   - サーバー上のサイトディレクトリ全体を `tar czf` で退避する(`archives` / `media` / `storage` / `config.server.php` / `.htaccess` / `license.php` を含む)
   - DB をダンプする(管理画面のバックアップ、または `mysqldump`)
2. **サーバーの現状確認**(読み取りのみ)
   - `git status` と `git log -1` を控える。サーバー上で直接編集された差分があれば、先にリポジトリ側に取り込む
   - サイトのルート(`index.php` と `config.server.php` があるディレクトリ)の絶対パスを控える → `DEPLOY_PATH`
3. **サーバーの `.git` を無効化**: 誤って pull しないよう、`mv .git .git.disabled` のように改名する(削除はしない)
   - どうしても pull を続ける場合は、事前に `git fetch && git reset --mixed origin/master` で index だけを更新する。作業ツリーのファイルは変わらない。ただしテーマの `dist/` は CI でしか作れないため、pull 運用は成立しない
4. **GitHub の設定**(Settings > Secrets and variables > Actions。Secret / Variable は Environment `production` に置いてよいが、**`DEPLOY_ENABLED` だけはリポジトリレベルの Variable** にする)

   | 種別 | 名前 | 内容 |
   |---|---|---|
   | Secret | `DEPLOY_SSH_KEY` | デプロイ専用の秘密鍵(公開鍵はサーバー側で登録) |
   | Secret | `DEPLOY_KNOWN_HOSTS` | `ssh-keyscan -p <port> <host>` の出力。**手元で取得して指紋を確認してから登録** |
   | Variable | `DEPLOY_HOST` / `DEPLOY_USER` / `DEPLOY_PORT` | 接続先 (Xserver の SSH 設定を確認) |
   | Variable | `DEPLOY_PATH` | 手順 2 のサイトルート(**絶対パス**。`~` は不可) |
   | Variable | `DEPLOY_ENABLED` | 準備ができたら `true` |

   Environment `production` に Required reviewers を付けると、本番反映前に承認を挟めます。
   プラグインの submodule が private の場合は、`deploy.yml` の submodule 取得にトークンを設定してください。
5. **ドライラン**: `DEPLOY_ENABLED=true` にして、Actions > Deploy > Run workflow で `dry_run` を ON のまま実行する
   - ログの `*deleting` 行と、`config.system.yaml` などの上書き対象を確認する。想定外の削除があれば中止する
   - `extension/plugins/<name>` がサーバー上で symlink の場合、リンク先(`<サイトルートの親>/plugins/<name>/src`)が実在することも確認する。リンク切れだと rsync がその時点で失敗し、先に同期したテーマ等だけが反映された状態になる
   - (補足)`extension/plugins/<name>` がサーバー上で symlink の場合、実体ディレクトリとして用意するか、辿った先への書き込みで問題ないかを確認する
   - テーマ配下にサーバー側で作られたファイル(テンプレートエディタ等)があると `--delete` で消えるので、ドライランで必ず確認する
   - サーバー側の `config.system.yaml` に本番固有の設定がある場合は、先にリポジトリへ反映する(デプロイで上書きされるため)
6. **本番反映**: `dry_run` を OFF にして実行し、サイトの表示と管理画面を確認する
7. **自動アップデートの有効化**: リポジトリの `ablogcms/private/config.system.yaml` に `system_auto_update: on` を設定してデプロイ
   (このファイルはデプロイで上書きされるため、サーバー上で直接変えない)

## B. 通常の運用

- master へ push(または PR マージ)→ CI → Deploy が自動実行される
- コアのアップデートはサーバー側の自動/オンラインアップデートが行う。Deploy はコアに触れない
- テーマ・拡張・プラグインの変更は PR で行う

## C. ロールバック

- 反映済みの変更: 該当コミットを revert して master に push する(Deploy が再実行される)
- サーバーの状態そのもの: 手順 1 のバックアップから復元する
- Deploy を止めたいとき: リポジトリ変数 `DEPLOY_ENABLED` を `false` にする

## D. Deploy が触るもの / 触らないもの

| 触る(--delete はそれぞれのディレクトリ内のみ) | 触らない |
|---|---|
| `themes/develop/` / `extension/acms/` / `extension/schema/` / `extension/plugins/<plugin>/` / `config.user.php` / `private/config.system.yaml` | コア全般、`config.server.php`、`.env`、`license.php`、`.htaccess`、`archives`、`media`、`storage`、`cache`、`backup_*` |
