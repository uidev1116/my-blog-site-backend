<?php

use Acms\Services\Facades\Login;

class ACMS_POST_Member_Tfa_Unregister extends ACMS_POST_Member
{
    /**
     * 2段階認証を無効化
     *
     * @return Field_Validation
     */
    public function post(): Field_Validation
    {
        $tfaField = $this->extract('tfa');
        $this->validate($tfaField);
        $uid = SUID ?? 0; // @phpstan-ignore-line
        $this->disableTfa($uid);

        if ($this->Post->isValidAll()) {
            $this->Post->set('register', 'success');
            AcmsLogger::info('2段階認証の無効化をしました', [
                'uid' => $uid,
                'name' => ACMS_RAM::userName($uid),
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
        if (!Login::canMemberSignin()) {
            $tfaField->setMethod('tfa', 'isOperable', false);
            httpStatusCode('403 Forbidden');
        }
        if (!SUID) { // @phpstan-ignore-line
            $tfaField->setMethod('tfa', 'isOperable', false);
            httpStatusCode('403 Forbidden');
        }
        if (!Tfa::isAvailable()) {
            $tfaField->setMethod('tfa', 'isOperable', false);
            httpStatusCode('403 Forbidden');
        }
        $tfaField->validate(new ACMS_Validator());
    }

    /**
     * 2段階認証を無効化
     *
     * @param int $uid
     * @return void
     */
    protected function disableTfa(int $uid): void
    {
        $sql = SQL::newUpdate('user');
        $sql->addUpdate('user_tfa_secret', null);
        $sql->addUpdate('user_tfa_secret_iv', null);
        $sql->addUpdate('user_tfa_recovery', null);
        $sql->addWhereOpr('user_id', $uid);
        DB::query($sql->get(dsn()), 'exec');
        ACMS_RAM::user($uid, null);
    }
}
