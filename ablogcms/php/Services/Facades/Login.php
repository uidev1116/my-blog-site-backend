<?php

namespace Acms\Services\Facades;

/**
 * @method static void setConstantsAuthSystemPage(\Field $queryParameter) 認証系ページの定数をセット
 * @method static void postLoginProcessing() ログイン処理を実行
 * @method static bool accessRestricted(bool $isAdmin = true) アクセス制限を適用
 * @method static string|false getAuthSystemTemplate() 認証系テンプレートを取得
 * @method static int findUser(string $email, int $bid) ユーザーIDを検索
 * @method static int createUser(\Field_Validation $user, bool $subscribeLoginAnywhere) ユーザーを作成
 * @method static int addUserFromOauth(array $data) OAuth認証でユーザーを追加
 * @method static void updateUser(int $uid, \Field_Validation $user, bool $subscribeLoginAnywhere = false) ユーザーを更新
 * @method static string createTimedLinkParams(string $context, int $lifetime) タイムリンクパラメータを作成
 * @method static array validateTimedLinkParams(string $key, string $salt, string $context) タイムリンクパラメータを検証
 * @method static true subscriberActivation(int $uid) 特車ユーザーのアクティベーションを処理
 * @method static string[] getSinginAuth() 一般サインインできる権限を取得
 * @method static string[] getAdminLoginAuth() 管理ログインできる権限を取得
 * @method static string getLogoutRedirectUrl(int $userId) ログアウト時のリダイレクト先URLを取得
 * @method static bool checkAllowedDevice(array $user) ログインが許可されたデバイスかどうかを確認
 * @method static string userIconFromUri(string $imageUri) ユーザーアイコンのURIを取得
 * @method static never loginRedirect(array $user, string|null $fieldRedirectUrl = null) ログイン後のリダイレクト
 * @method static string|null resizeUserIcon(string $squarePath) ユーザーアイコンをリサイズ
 * @method static void addExtraLoggedInCookie(int $uid) ログインしている場合、権限のCookieを追加
 * @method static void updateSessionClientInfo(int $uid) 同時ログイン判定のための、クライアント情報を更新
 * @method static void removeExtraLoggedInCookie() ログインしている場合、権限のCookieを削除
 * @method static bool isAuthRequiredPage() 現在のページが認証が必要なページかどうか判定
 * @method static bool isLoggedIn() 現在のセッションがログイン状態かどうか判定
 * @method static bool canMemberSignin() 会員サインイン機能が有効かどうか判定
 */
class Login extends Facade
{
    protected static $instance;

    /**
     * @return string
     */
    protected static function getServiceAlias()
    {
        return 'login';
    }

    /**
     * @return bool
     */
    protected static function isCache()
    {
        return true;
    }
}
