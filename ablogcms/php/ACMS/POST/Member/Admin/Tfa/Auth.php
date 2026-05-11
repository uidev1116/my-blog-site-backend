<?php

use Acms\Services\Login\Enums\TfaAuthResult;

class ACMS_POST_Member_Admin_Tfa_Auth extends ACMS_POST_Member_Admin_Login
{
    /**
     * 正常なルートからのPOSTかどうかをチェック
     *
     * @inheritDoc
     */
    protected function isValidPostRoute(): bool
    {
        return Login::canLoginPage(BID, LOGIN_SEGMENT);
    }

    /**
     * 2段階認証のアクション
     * 戻り値が true だと、そこで処理をやめる
     *
     * @param Field_Validation $loginField
     * @param int $uid
     * @return bool
     */
    protected function checkTowFactorAuthAction(Field_Validation $loginField, int $uid): bool
    {
        $inputCode = preg_replace("/(\s|　)/", "", $loginField->get('code'));
        $result = Tfa::authenticate($uid, $inputCode);
        if ($result === TfaAuthResult::SUCCESS) {
            AcmsLogger::info('2段階認証に成功しました', [
                'uid' => $uid,
            ]);
            return false;
        }
        if ($result === TfaAuthResult::DECRYPT_FAILED) {
            $loginField->setMethod('code', 'secretKey', false);
            AcmsLogger::warning('秘密鍵の復号に失敗したため2段階認証を中断しました', [
                'uid' => $uid,
            ]);
        } else {
            $loginField->setMethod('code', 'auth', false);
            AcmsLogger::info('2段階認証に失敗しました', [
                'uid' => $uid,
            ]);
        }
        $loginField->validate(new ACMS_Validator());
        return true;
    }
}
