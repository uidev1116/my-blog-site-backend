<?php

namespace Acms\Services\Login;

use LengthException;
use Acms\Services\Facades\Login;
use Acms\Services\Facades\Common;
use Acms\Services\Facades\Logger as AcmsLogger;
use Acms\Services\Facades\Database as DB;
use Acms\Services\Login\Enums\TfaAuthResult;
use phpseclib3\Exception\BadDecryptionException;
use RobThree\Auth\TwoFactorAuthException;
use RobThree\Auth\TwoFactorAuth;
use SQL;

class Tfa
{
    /**
     * @var \RobThree\Auth\TwoFactorAuth
     */
    protected $tfa;

    /**
     * Constructor
     * @param string|null $appName アプリケーション名（QRコードのアカウント名に使われる）
     */
    public function __construct($appName)
    {
        $this->tfa = new TwoFactorAuth($appName);
    }

    /**
     * @return bool
     */
    public function isAvailable()
    {
        if (config('two_factor_auth') !== 'on') {
            return false;
        }
        return true;
    }

    /**
     * 秘密鍵を作成
     *
     * @return string
     */
    public function createSecret()
    {
        return $this->tfa->createSecret(160);
    }

    /**
     * 秘密鍵のQRコード画像を取得
     *
     * @param string $secret 秘密鍵
     * @param string $label ラベル
     * @return string data:image
     */
    public function getSecretForQRCode($secret, $label)
    {
        return $this->tfa->getQRCodeImageAsDataUri($label, $secret);
    }

    /**
     * 秘密鍵を表示
     *
     * @param string $secret 秘密鍵
     * @return string
     */
    public function getSecretForManual($secret)
    {
        return chunk_split($secret, 4, ' ');
    }

    /**
     * 一時トークンが正しいかチェック
     *
     * @param string $secret 秘密鍵
     * @param string $code 一時トークン
     * @return boolean
     */
    public function verifyCode($secret, $code)
    {
        return $this->tfa->verifyCode($secret, $code);
    }

    /**
     * サーバー時間が正しいかチェック
     *
     * @return bool
     */
    public function checkCorrectTime()
    {
        try {
            $this->tfa->ensureCorrectTime();
        } catch (\RobThree\Auth\TwoFactorAuthException $e) {
            return false;
        }
        return true;
    }

    /**
     * 秘密鍵を取得する。
     *
     * 取得結果は「成功」「未登録」「復号失敗」の3状態を持つ Result で返す。
     * 例外を投げないので呼び出し側で try/catch する必要はない。
     *
     * 外部からは hasValidSecretKey() / authenticate() / isAvailableAccount() を使用すること。
     *
     * @param int $uid
     * @return TfaSecretKeyResult
     */
    private function getSecretKey(int $uid): TfaSecretKeyResult
    {
        $sql = SQL::newSelect('user');
        $sql->addSelect('user_tfa_secret');
        $sql->addSelect('user_tfa_secret_iv');
        $sql->addWhereOpr('user_id', $uid);
        $row = DB::query($sql->get(dsn()), 'row');

        /** @var string $cipher */
        $cipher = is_array($row) ? ($row['user_tfa_secret'] ?? '') : '';
        /** @var string $iv */
        $iv = is_array($row) ? ($row['user_tfa_secret_iv'] ?? '') : '';

        if ($cipher === '') {
            return TfaSecretKeyResult::notRegistered();
        }
        if ($iv === '') {
            return TfaSecretKeyResult::decryptFailed();
        }

        try {
            $secret = Common::decrypt($cipher, base64_decode($iv)); // @phpstan-ignore-line
        } catch (BadDecryptionException | LengthException $e) {
            return TfaSecretKeyResult::decryptFailed();
        }

        if ($secret === '') {
            return TfaSecretKeyResult::decryptFailed();
        }
        return TfaSecretKeyResult::success($secret);
    }

    /**
     * 秘密鍵が登録済みかつ復号可能な状態か。
     *
     * 登録画面で「再登録 UI を出すか／登録済み表示にするか」の判定に使う。
     *
     * @param int $uid
     * @return bool
     */
    public function hasValidSecretKey(int $uid): bool
    {
        return $this->getSecretKey($uid)->isSuccess();
    }

    /**
     * 2段階認証を要求すべきアカウントか判定する。
     *
     * 復号失敗でも「未登録ではない」ので true を返す。
     * これにより復号失敗ユーザーがID/パスワードのみで通過するのを防ぐ。
     *
     * @param int $uid
     * @return bool
     */
    public function isAvailableAccount($uid)
    {
        if (!$this->isAvailable()) {
            return false;
        }
        if ($this->getSecretKey($uid)->isNotRegistered()) {
            return false;
        }
        return true;
    }

    /**
     * 2段階認証コードを検証する
     *
     * @param int $uid
     * @param string $code
     * @return TfaAuthResult
     */
    public function authenticate(int $uid, string $code): TfaAuthResult
    {
        $result = $this->getSecretKey($uid);
        if ($result->isNotRegistered()) {
            return TfaAuthResult::NOT_REGISTERED;
        }
        if ($result->isDecryptFailed()) {
            return TfaAuthResult::DECRYPT_FAILED;
        }
        try {
            if ($this->verifyCode($result->getValue(), $code)) {
                return TfaAuthResult::SUCCESS;
            }
        } catch (TwoFactorAuthException $e) {
            // 復号は通ったが secret が base32 として不正で verifyCode が拒否したケース
            AcmsLogger::notice('秘密鍵の形式が不正です', Common::exceptionArray($e, [
                'uid' => $uid,
            ]));
            return TfaAuthResult::DECRYPT_FAILED;
        }
        return TfaAuthResult::INVALID_CODE;
    }

    /**
     * @return bool
     */
    public function checkAuthority()
    {
        // ２段階認証機能が有効でない
        if (!$this->isAvailable()) {
            return false;
        }
        // ログインしていない OR ユーザページでない
        if (!UID || !Login::isLoggedIn()) {
            return false;
        }
        // 自分自身でない（ただしスーパーユーザーは除く）
        if (!(RBID === SBID && sessionWithAdministration(BID)) && UID !== SUID) {
            return false;
        }
        return true;
    }
}
