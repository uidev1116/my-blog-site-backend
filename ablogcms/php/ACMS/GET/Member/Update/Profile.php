<?php

use Acms\Services\Facades\Login;

class ACMS_GET_Member_Update_Profile extends ACMS_GET_Member
{
    /**
     * 初期処理
     *
     * @return void
     */
    protected function init(): void
    {
        if (!Login::isLoggedIn()) {
            page404();
        }
    }

    /**
     * テンプレート組み立て
     *
     * @param Template $tpl
     * @return void
     */
    protected function buildTpl(Template $tpl): void
    {
        $vars = [];
        $user = loadUser(SUID);

        $user->delete('pass');
        $user->delete('status');
        $user->delete('sort');
        $user->delete('pass_reset');
        $user->delete('pass_generation');
        $user->delete('tfa_secret');
        $user->delete('tfa_secret_iv');
        $user->delete('tfa_secret_recovery');
        $user->delete('confirmation_token');
        $user->delete('reset_password_token');
        $user->delete('auth');
        $user->delete('indexing');
        $user->delete('login_anywhere');
        $user->delete('global_auth');
        $user->delete('login_expire');
        $user->delete('login_terminal_restriction');

        $inputUserField = $this->Post->getChild('user');
        $inputField = $this->Post->getChild('field');
        $geoField = $this->Post->getChild('geometry');
        if (!$inputUserField->get('icon')) {
            $inputUserField->delete('icon');
        }
        $user->overload($inputUserField);

        $geoField->overload(loadGeometry('uid', SUID));

        $this->Post->addChild('user', $user);
        if ($inputField->isNull()) {
            $inputField->overload(loadUserField(SUID));
        }
        if ($this->Post->isValidAll()) {
            if ($this->Post->get('updated') === 'success') {
                $tpl->add('success');
            }
        } elseif (!$this->Post->isNull()) {
            $tpl->add('notSuccessful');
        }
        $vars += $this->buildField($this->Post, $tpl);
        $tpl->add(null, $vars);
    }
}
