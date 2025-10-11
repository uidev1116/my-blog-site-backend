<?php

class ACMS_POST_Member_Tfa_Register extends ACMS_POST_Member
{
    /**
     * 2段階認証を有効化
     *
     * @return Field_Validation
     */
    function post(): Field_Validation
    {
        $tfaField = $this->extract('tfa');
        $this->validate($tfaField);

        if ($this->Post->isValidAll()) {
            $recoveryCode = Common::genPass(16);
            $this->enableTfa(SUID, $tfaField->get('secret'), $recoveryCode);
            $this->Post->set('recoveryCode', $recoveryCode);
            $this->Post->set('register', 'success');

            AcmsLogger::info('2段階認証の設定を行いました', [
                'uid' => SUID,
                'name' => ACMS_RAM::userName(SUID),
            ]);
        } else {
            AcmsLogger::info('2段階認証の設定に失敗しました', [
                'tfa' => $tfaField->_aryV,
            ]);
        }
        return $this->Post;
    }

    /**
     * 2段階認証有効化のバリデーション
     *
     * @param Field_Validation $tfaField
     * @return void
     */
    protected function validate(Field_Validation $tfaField): void
    {
        if (!SUID) {
            $tfaField->setMethod('tfa', 'isOperable', false);
            httpStatusCode('403 Forbidden');
        }
        if (!Tfa::isAvailable()) {
            $tfaField->setMethod('tfa', 'isOperable', false);
            httpStatusCode('403 Forbidden');
        }
        $tfaField->setMethod('code', 'required');
        $tfaField->setMethod('secret', 'required');
        $tfaField->setMethod('code', 'disagreement', Tfa::verifyCode($tfaField->get('secret'), $tfaField->get('code')));
        $tfaField->validate(new ACMS_Validator());
    }

    /**
     * 2段階認証を有効化
     *
     * @param int $uid
     * @param string $secret
     * @param string $recoveryCode
     * @return void
     */
    protected function enableTfa(int $uid, string $secret, string $recoveryCode): void
    {
        $iv = Common::getEncryptIv();

        $sql = SQL::newUpdate('user');
        $sql->addUpdate('user_tfa_secret', Common::encrypt($secret, $iv));
        $sql->addUpdate('user_tfa_secret_iv', base64_encode($iv));
        $sql->addUpdate('user_tfa_recovery', acmsUserPasswordHash($recoveryCode));
        $sql->addWhereOpr('user_id', $uid);
        DB::query($sql->get(dsn()), 'exec');
        ACMS_RAM::user($uid, null);
    }
}
