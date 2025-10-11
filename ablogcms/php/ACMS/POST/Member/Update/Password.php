<?php

use Acms\Services\Facades\Login;

class ACMS_POST_Member_Update_Password extends ACMS_POST_Member
{
    public function post()
    {
        $userField = $this->extract('user');
        $this->validate($userField);

        if ($this->Post->isValidAll()) {
            $this->updatePassword($userField);
            $this->Post->set('updated', 'success');

            AcmsLogger::info('パスワードを変更しました');
        } else {
            if (!$userField->isValid('pass', 'required')) {
                AcmsLogger::info('パスワードが入力されていないので、パスワード変更に失敗しました');
            }
            if (!$userField->isValid('pass', 'password')) {
                AcmsLogger::info('不正なパスワードのため、パスワード変更に失敗しました');
            }
            if (!$userField->isValid('user', 'operable')) {
                AcmsLogger::notice('ログアウトしているため、パスワード変更を中断しました');
            }
        }
        return $this->Post;
    }

    protected function validate($userField)
    {
        if (!Login::canMemberSignin()) {
            $userField->setMethod('updatePassword', 'operable', false);
            httpStatusCode('403 Forbidden');
        }
        $userField->setMethod('pass', 'required');
        $userField->setMethod('pass', 'password');
        $userField->setMethod('user', 'operable', !!SUID);
        $userField->validate(new ACMS_Validator());
    }

    protected function updatePassword($userField)
    {
        $passwordHash = acmsUserPasswordHash($userField->get('pass'));
        $sql = SQL::newUpdate('user');
        $sql->addUpdate('user_pass', $passwordHash);
        $sql->addUpdate('user_pass_generation', PASSWORD_ALGORITHM_GENERATION);
        $sql->addWhereOpr('user_id', SUID);
        DB::query($sql->get(dsn()), 'exec');
        ACMS_RAM::user(UID, null);
    }
}
