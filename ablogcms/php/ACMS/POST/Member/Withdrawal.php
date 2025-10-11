<?php

use Acms\Services\Facades\Application;
use Acms\Services\Facades\Common;
use Acms\Services\Facades\Logger;
use Acms\Services\Facades\Webhook;
use Acms\Services\Facades\Login;
use Acms\Services\Facades\Session;

class ACMS_POST_Member_Withdrawal extends ACMS_POST_Member
{
    /**
     * Main
     * @return Field_Validation
     */
    public function post(): Field_Validation
    {
        $field = $this->extract('field', new ACMS_Validator());
        $preUser = loadUser(SUID);
        $this->validate();

        if ($this->Post->isValidAll()) {
            Logger::info('ユーザー「' . $preUser->get('name') . '」が退会しました', [
                'uid' => SUID,
                'user' => $preUser->_aryV,
                'field' => $field->_aryV,
            ]);
            Webhook::call(BID, 'user', ['user:withdrawal'], SUID);

            $field->set('updateField', 'on');
            Common::saveField('uid', SUID, $field);

            if (config('withdrawal_delete_type') === 'physical') {
                $this->physicalDelete(); // 物理削除
            } else {
                $this->logicalDelete(); // 論理削除
            }

            // セッション削除
            $session = Session::handle();
            $session->destroy();


            $url = acmsLink([
                'protocol' => (SSL_ENABLE and ('on' == config('login_ssl'))) ? 'https' : 'http',
                'bid' => BID,
                'query' => [],
            ]);
            redirect($url);
        } else {
            Logger::info('ユーザー「' . $preUser->get('name') . '」が退会に失敗しました', [
                'uid' => SUID,
                'user' => $preUser->_aryV,
                'field' => $field->_aryV,
            ]);
        }
        return $this->Post;
    }

    protected function validate(): void
    {
        $userService = Application::make('user');

        $this->Post->setMethod(
            'withdrawal',
            'auth',
            !in_array(ACMS_RAM::userAuth(SUID), Login::getAdminLoginAuth(), true)
        );
        $this->Post->setMethod('withdrawal', 'operable', !!SUID && Login::canMemberSignin()); // @phpstan-ignore-line
        $this->Post->setMethod('withdrawal', 'entryExists', !$userService->entryExists(SUID));
        $this->Post->validate(new ACMS_Validator());
    }

    /**
     * 論理削除
     *
     * @return void
     */
    protected function logicalDelete(): void
    {
        $userService = Application::make('user');
        $userService->logicalDelete(SUID);
    }

    /**
     * 物理削除
     *
     * @return void
     */
    protected function physicalDelete(): void
    {
        $userService = Application::make('user');
        $userService->physicalDelete(SUID);
    }
}
