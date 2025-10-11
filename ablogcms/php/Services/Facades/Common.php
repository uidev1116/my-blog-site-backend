<?php

namespace Acms\Services\Facades;

/**
 * Class Common
 *
 * @method static string getEncryptIv() 暗号化用のIVを取得する
 * @method static string encrypt(string $string, string $iv) 文字列を暗号化する
 * @method static string decrypt(string $cipherText, string $iv) 暗号化された文字列を復号化する
 * @method static string parseMarkdown(string $txt) Markdownを解析する
 * @method static void backgroundRedirect(string $url) すぐにリダイレクトし、同一プロセスのバックグラウンドで処理を実行
 * @method static void addSecurityHeader() セキュリティヘッダーを追加する
 * @method static string createCsrfToken() CSRFトークンを作成する
 * @method static string addCsrfToken(string $tpl) CSRFトークンをテンプレートに追加する
 * @method static bool csrfTokenExists() CSRFトークンがセッションに存在するか確認する
 * @method static bool checkCsrfToken(string $token) CSRFトークンが有効か確認する
 * @method static string getHttpHeader(string $name) HTTPヘッダーを取得する
 * @method static bool isAuthorizedAjaxRequest(int $level = 1) Ajaxアクセスが許可されているか確認する
 * @method static string fixAliasPath(string $txt) 管理画面でテンプレート直で書かれているパスを、エイリアスを含んだURLに修正
 * @method static string getDeleteField() extract()後の削除フィールドを取得
 * @method static string getMailTxt(string $path, \Field $field, ?string $charset = null) メールテキストを取得する
 * @method static string getMailTxtFromTxt(string $txt, \Field $field) テキストからメールテキストを取得する
 * @method static non-empty-array<'additional_headers'|'mail_from'|'sendmail_path'|'smtp-google'|'smtp-google-user'|'smtp-host'|'smtp-pass'|'smtp-verify-peer'|'smtp-port'|'smtp-user', string> mailConfig(array{smtp-host?: string, smtp-port?: string, smtp-user?: string, smtp-pass?: string, smtp-verify-peer?: string, mail_from?: string, sendmail_path?: string, additional_headers?: string, smtp-google?: string, smtp-google-user?: string} $argConfig = []) メール設定の取得
 * @method static string genPass(int $len) パスワードを生成する
 * @method static string[] getTagsFromString(string $string, bool $checkReserved = true) 文字列からタグを取得する
 * @method static string loadEntryFulltext(int $eid) エントリーのフルテキストを取得する
 * @method static string loadUserFulltext(int $uid) ユーザーのフルテキストを取得する
 * @method static string loadCategoryFulltext(int $cid) カテゴリーのフルテキストを取得する
 * @method static string loadBlogFulltext(int $bid) ブログのフルテキストを取得する
 * @method static void saveFulltext(string $type, int $id, string $fulltext = null, ?int $targetBid = null) フルテキストを保存する
 * @method static never download(string $path, string $fileName, string|bool $extension = false, bool $remove = false, \Acms\Services\Storage\Contracts\Filesystem $storage = null) ファイルをダウンロードする
 * @method static void deleteFieldCache(string $type, int $id, ?int $rvid = null) フィールドキャッシュを削除する
 * @method static void flushCache() フィールドキャッシュをフラッシュする
 * @method static void deleteField(string $type, int $id, ?int $rvid = null, ?int $blogId = null) カスタムフィールドを削除する
 * @method static \Field loadField(?int $bid = null, ?int $uid = null, ?int $cid = null, ?int $mid = null, ?int $eid = null, ?string $unitId = null, ?int $rvid = null, bool $rewrite = false) カスタムフィールドを取得する
 * @method static bool saveField(string $type, ($type is 'unit_id' ? string : int) $id, ?\Field $Field = null, ?\Field $deleteField = null, ?int $rvid = null, ?int $targetBid = null) カスタムフィールドを保存する
 * @method static \Field getUriObject(\Field $Post) フォームのURIオブジェクトを取得する
 * @method static \Field extract(string $scp = 'field', ?\ACMS_Validator $V = null, ?\Field $deleteField = null) フィールドを取得する
 * @method static array getJsModules() acms.js のクエリを取得する
 * @method static bool isSafeUrl(string $url) a-blog cms で管理しているドメインのURLかチェックする
 * @method static never responseJson(array $data) JSON形式のレスポンスを返す
 * @method static void logLockPost(string $lockKey) ロックポストをログに記録する
 * @method static bool validateLockPost(string $lockKey, int $trialTime = 5, int $trialNumber = 5, int $lockTime = 15, bool $remoteAddr = true) ロックポストを検証する
 * @method static string camelize(string $str) 文字列をキャメルケースに変換する
 * @method static void clientCacheHeader(bool $noCache = false) クライアントキャッシュヘッダーを設定する
 * @method static void saveCache(string $chid, string $contents, string $mime) キャッシュを保存する
 * @method static array exceptionArray(\Throwable $th, array $add = []) エラー情報を配列に変換する
 * @method static string[] getInheritedThemes(string $theme) 指定されたテーマの継承テーマ・システムテーマすべてのテーマの配列を取得する
 * @method static void validateFileUpload(string $name) ファイルアップロードを検証する
 * @method static string|false getMimeType(string $path) ファイルのMIMEタイプを取得する
 * @method static bool isLocalPrivateStorage() プライベートストレージの設定がローカルかどうか
 * @method static bool isLocalPublicStorage() パブリックストレージの設定がローカルかどうか
 * @method static void uploadAssetDirectory(string $from, string $to, bool $isPublic) アセットディレクトリをアップロードする
 * @method static string getCurrentSalt() 現在のソルトを取得
 * @method static string getPreviousSalt() 1つ前のソルトを取得
 * @method static string getAppSalt() アプリケーションのソルトを取得
 * @method static string replaceDeliveryUrl(string $url) メディアの配信先URLを書き換え
 * @method static string replaceDeliveryUrlAll(string $txt) メディアの配信先URLを書き換え（全て）
 * @method static string toAbsoluteUrl(string $path, string $offset = '', bool $strict) 絶対URLに変換する
 * @method static string resolveUrl(string $path, string $offset = '') V2モジュール、V2APIの場合は絶対URLに変換する
 * @method static string convertRelativeUrlsToAbsolute(string $html, string $baseUrl) HTML内の相対URLを絶対URLに変換
 * @method static bool isV2Module() V2モジュールとして実行中か判定
 * @method static void setV2Module(bool $isV2Module) V2モジュールとして実行中か設定
 * @method static bool isForceV1Build() 強制的にV1ビルドを行うか判定
 * @method static void setForceV1Build(bool $isForceV1Build) 強制的にV1ビルドを行うか設定
 * @method static void setSafeHeadersWithoutCache(int $code = 200, string $mime = 'text/html') 安全なヘッダーを設定し、クライアントキャッシュを無効化するヘッダーを設定する
 */
class Common extends Facade
{
    protected static $instance;

    /**
     * @return string
     */
    protected static function getServiceAlias()
    {
        return 'common';
    }

    /**
     * @return bool
     */
    protected static function isCache()
    {
        return true;
    }
}
