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
   | Variable | `DEPLOY_ENABLED` | 準備ができたら `true`(手動実行が可能になる) |
   | Variable | `DEPLOY_ON_PUSH` | `true` にすると、master への push でも自動デプロイする(既定は未設定=手動実行のみ) |

   Environment `production` に Required reviewers を付けると、本番反映前に承認を挟めます。
   プラグインの submodule が private の場合は、`deploy.yml` の submodule 取得にトークンを設定してください。
5. **ドライラン**: `DEPLOY_ENABLED=true` にして、Actions > Deploy > Run workflow で `dry_run` を ON のまま実行する
   - ログの `*deleting` 行と、`config.system.yaml` などの上書き対象を確認する。想定外の削除があれば中止する
   - **サーバーの `extension/plugins/<name>`(特に `ApiPreview`)が symlink だと、Deploy は事前チェックで止まります**。rsync は転送先の途中にある symlink へは書き込めないためです(以前 symlink ごと Git で追跡していた名残)。手順 1 のバックアップ後に、symlink を実ディレクトリに置き換えてください(例: `unlink extension/plugins/ApiPreview && mkdir extension/plugins/ApiPreview`。`unlink` はリンクだけを外し、リンク先 `plugins/ApiPreview/src` は残る)
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

rsync は 1 回だけ実行し、許可リスト(`.github/scripts/deploy-rsync.sh`)にあるパスだけを送信・削除します。リストにないものは、サーバー側に何があっても `--delete` の対象になりません。

| 触る(--delete はそれぞれのディレクトリ内のみ) | 触らない |
|---|---|
| `themes/develop/` / `extension/acms/` / `extension/schema/` / `extension/plugins/<plugin>/` / `config.user.php` / `private/config.system.yaml` / `{archives,cache,cron,media,private,storage}/.htaccess`(ファイル単体、`--delete` なし) | コア全般、`config.server.php`、`.env`、`license.php`、ルートの `.htaccess`、`archives`、`media`、`storage`、`cache`(`cache/.htaccess` 以外)、`backup_*` |

## E. Dependabot の自動マージ

`dependabot-auto-merge.yml` が、安全なものだけに自動マージを設定します。

| 自動マージする | 手動のまま |
|---|---|
| GitHub Actions のマイナー/パッチ、npm の devDependencies と間接依存のマイナー/パッチ | メジャー更新、npm の本番依存(本番のバンドルが変わるため)、プラグインの submodule(サーバーに配置されるため) |

**使うには、リポジトリ側の設定が必要です。** これがないと、CI の結果を待たずにマージされます。

1. Settings > General > Pull Requests で **Allow auto-merge** を ON にする
2. `master` にブランチ保護(または Ruleset)を作り、**Require status checks to pass** で次を必須にする
   - `Theme (lint / format / build)`
   - `Root tooling (format)`

`DEPLOY_ENABLED` が `true` の間は、master へのマージが本番デプロイにつながります。自動マージを有効にする前に、Deploy の運用(Environment の承認など)を決めてください。
