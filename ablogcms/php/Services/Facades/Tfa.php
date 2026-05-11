<?php

namespace Acms\Services\Facades;

use Acms\Services\Login\Enums\TfaAuthResult;

/**
 * @method static bool isAvailable() 2段階認証が有効か判定
 * @method static string createSecret() 秘密鍵を作成
 * @method static string getSecretForQRCode(string $secret, string $label) 秘密鍵のQRコード画像を取得
 * @method static string getSecretForManual(string $secret) 秘密鍵の手動入力用文字列を取得
 * @method static bool verifyCode(string $secret, string $code) コードを検証
 * @method static bool checkCorrectTime() サーバー時間が正しいかチェック
 * @method static bool hasValidSecretKey(int $uid) 秘密鍵が登録済みかつ復号可能か判定
 * @method static TfaAuthResult authenticate(int $uid, string $code) 認証結果を取得
 * @method static bool isAvailableAccount(int $uid) 2段階認証が有効なアカウントか判定
 * @method static bool checkAuthority() 権限をチェック
 */
class Tfa extends Facade
{
    protected static $instance;

    /**
     * @return string
     */
    protected static function getServiceAlias()
    {
        return 'login.tfa';
    }

    /**
     * @return bool
     */
    protected static function isCache()
    {
        return true;
    }
}
