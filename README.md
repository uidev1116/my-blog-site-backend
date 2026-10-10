# my-blog-site-backend

ブログのバックエンド (a-blog cms)。**コア(本体)は Git で管理せず**、a-blog cms の
オンライン/自動アップデートに任せています。Git で管理するのは、自分のカスタマイズと設定だけです。

## 何を管理しているか

| 区分 | 内容 | 扱い |
|---|---|---|
| コア | `ablogcms/{php,js,lang,themes/system,index.php,acms.js}`、`private/config.system.default.yaml` | **Git 管理外**(アップデートで置き換わる)。`.gitignore` 済み |
| 自分で管理 | `ablogcms/themes/develop/`、`ablogcms/extension/{acms,schema}/`、`ablogcms/config.user.php`、`ablogcms/private/config.system.yaml`、`plugins/` | **Git 管理** |
| 実行時生成・機密 | `config.server.php`、`.env`、`license.php`、`.htaccess`、`archives/`、`media/`、`storage/`、`cache/` | **Git 管理外**(サーバーごとに別) |

根拠: [アップデート](https://developer.a-blogcms.jp/document/update/) /
[ディレクトリ構造](https://developer.a-blogcms.jp/document/dir_structure.html)

- コアを直接改造しないでください。アップデートで消えます。拡張は `ablogcms/extension/` か、テーマ側の同名ファイルで行います。
- テーマの `dist/` は CI でビルドします(Git 管理外)。

## 開発環境

```bash
docker compose up -d      # compose.yaml
npm run setup             # submodule 取得・プラグインの symlink 作成
npm run fetch-core -- --init   # コアが無い環境(新しい clone など)のみ。setup の後に実行
cd ablogcms/themes/develop && npm ci && npm run dev
```

`npm run fetch-core` は公式の配布パッケージから、オンラインアップデートが置き換えるのと同じ範囲だけを取得します
(`-- --dry-run` で取得対象の確認、`-- --init` で `.htaccess` / `config.server.php` の初期配置)。
置き換え前のコアは Web ルート外の `.core-backup/backup_core_<日時>/` に退避されます。

> 既存の clone でこのブランチへ切り替える(または master にマージされたものを pull する)と、作業ツリーからコアが削除されます。
> 切り替え前に `ablogcms/` をバックアップし、切り替え後に `npm run fetch-core` で取得し直してください。

## コアのアップデート

本番・開発とも、管理画面(コンフィグ > 機能設定)のオンラインアップデート、または自動アップデート
(`ablogcms/private/config.system.yaml` の `system_auto_update: on`、パッチのみ)で行います。
実行後はメンテナンス画面から DB 更新を行ってください。

## CI / CD

- `ci.yml` : PR / master への push で、テーマの lint・フォーマット・ビルドと、ルートのフォーマットを検査
- `deploy.yml` : master への push で、テーマをビルドして **自分で管理するファイルだけ**を rsync (SSH) でサーバーへ配置
  (コアには触れません)。リポジトリ変数 `DEPLOY_ENABLED=true` を設定するまで動きません
- `dependabot.yml` : GitHub Actions / npm(ルート・テーマ)を毎週月曜に更新 PR。
  プラグインの submodule は対象外です(Dependabot はタグ/リリースに限定できず、毎コミットで PR が来るため)。
  更新するときは、リリースタグのコミットへ手動で進めてください(`git -C plugins/<name> fetch --tags && git -C plugins/<name> checkout <tag>` → 親リポジトリでコミット)

セットアップと本番移行の手順は [docs/deploy.md](docs/deploy.md) を参照してください。
**移行後、本番サーバーで `git pull` してはいけません**(コアが削除されます)。
